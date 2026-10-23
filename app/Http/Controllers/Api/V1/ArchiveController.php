<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchiveDocument;
use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use App\Services\ArchiveEncryptionService;
use App\Services\AuditLogService;
use App\Services\CounterService;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ArchiveController extends Controller
{
    private const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public function __construct(
        private readonly RoleScopeService $scopes,
        private readonly CounterService $counters,
        private readonly ArchiveEncryptionService $encryption,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function index(Request $request)
    {
        $query = $this->scopes->archives($request->user());
        if ($request->filled('department')) {
            $departmentCode = (string) $request->query('department');
            $department = Department::where('departmentCode', $departmentCode)->first();
            if (! $department) {
                return response()->json(['data' => [], 'meta' => ['total' => 0]]);
            }
            $query->where('departmentId', $department->getKey());
        }
        if ($request->filled('referenceStandard')) {
            $query->where('referenceStandard', $request->query('referenceStandard'));
        }
        if ($request->filled('search')) {
            $search = '/'.preg_quote(trim((string) $request->query('search')), '/').'/i';
            $query->where(function ($query) use ($search) {
                $query->where('originalName', 'regex', $search)
                    ->orWhere('fileCode', 'regex', $search);
            });
        }
        if ($request->filled('dateFilter')) {
            if ($request->query('dateFilter') === 'Last 30 Days') {
                $query->where('createdAt', '>=', now()->utc()->subDays(30));
            } elseif ($request->query('dateFilter') === 'This Quarter') {
                $query->where('createdAt', '>=', now()->utc()->startOfQuarter());
            }
        }

        $page = $query->orderBy('createdAt', 'desc')
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn (ArchiveDocument $archive) => $this->serialize($archive)),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'checkId' => ['required', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'referenceStandard' => ['required', 'in:ARTA-3days,ARTA-7days,ARTA-20days,ISO9001-9.2'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = self::MIME_TYPES[$extension] ?? null;
        $contents = $file->getContent();
        if (! $mimeType || ! $this->matchesFileSignature($extension, $contents)) {
            throw ValidationException::withMessages([
                'file' => 'The file extension and detected MIME type do not match an allowed format.',
            ]);
        }

        $actor = $request->user();
        $check = ComplianceCheck::find(MongoId::parse($data['checkId'], 'checkId'));
        if (! $check || $check->referenceStandard !== $data['referenceStandard']) {
            throw ValidationException::withMessages([
                'checkId' => 'The selected check does not match the selected standard.',
            ]);
        }
        $this->authorizeCheckUpload($actor, $check);

        $departmentId = MongoId::parse((string) $check->departmentId, 'departmentId');
        $plaintext = $contents;
        $checksum = hash('sha256', $plaintext);
        $encrypted = $this->encryption->encrypt($plaintext);
        $fileCode = $this->counters->next('ARCH', yearly: true);
        $path = "archives/{$fileCode}.enc";
        if (! $this->writeEncryptedFile($path, $encrypted['ciphertext'])) {
            throw new \RuntimeException('Unable to write the encrypted archive.');
        }

        $archive = null;
        try {
            $archive = ArchiveDocument::create([
                'fileCode' => $fileCode,
                'originalName' => basename($file->getClientOriginalName()),
                'encryptedPath' => $path,
                'mimeType' => $mimeType,
                'fileSize' => $file->getSize(),
                'checksum' => $checksum,
                'departmentId' => $departmentId,
                'uploadedByUserId' => $actor->getKey(),
                'referenceStandard' => $data['referenceStandard'],
                'tags' => $data['tags'] ?? [],
                'isEncrypted' => true,
                'encryptionIv' => $encrypted['iv'],
                'encryptionTag' => $encrypted['tag'],
            ]);

            $linked = $check->getConnection()->getMongoDB()
                ->selectCollection($check->getTable())
                ->updateOne(
                    ['_id' => $check->getKey(), 'result' => 'non_compliant'],
                    ['$addToSet' => ['linkedArchiveIds' => $archive->getKey()]],
                );
            if ($linked->getMatchedCount() !== 1) {
                abort(409, 'The selected check is no longer eligible for evidence.');
            }

            if ((int) $actor->roleId === 3) {
                Notification::create([
                    'recipientUserId' => $check->auditorId,
                    'senderUserId' => $actor->getKey(),
                    'type' => 'evidence_uploaded',
                    'title' => 'Evidence uploaded',
                    'message' => "{$actor->fullName} uploaded evidence for {$check->checkCode}.",
                    'relatedCheckId' => $check->getKey(),
                    'isRead' => false,
                    'fcmSent' => false,
                ]);
            }

            $this->auditLogs->record($request, 'Uploaded archive evidence', 'archive_document', $archive->getKey());
        } catch (Throwable $exception) {
            if ($archive) {
                $archive->delete();
            }
            $this->deleteEncryptedFile($path);
            throw $exception;
        }

        return response()->json(['data' => $this->serialize($archive)], 201);
    }

    public function download(Request $request, string $id)
    {
        $archive = $this->findInScope($request, $id);
        $plaintext = $this->decryptAndVerify($archive);

        return response($plaintext, 200, [
            'Content-Type' => $archive->mimeType,
            'Content-Disposition' => 'attachment; filename="'.addslashes($archive->originalName).'"',
        ]);
    }

    public function verifyChecksum(Request $request, string $id)
    {
        $archive = $this->findInScope($request, $id);
        $this->decryptAndVerify($archive);

        return response()->json(['isVerified' => true]);
    }

    private function findInScope(Request $request, string $id): ArchiveDocument
    {
        return $this->scopes->archives($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
    }

    private function authorizeCheckUpload(User $actor, ComplianceCheck $check): void
    {
        if ($check->result !== 'non_compliant') {
            abort(403, 'Evidence can only be linked to a non-compliant check.');
        }

        if ((int) $actor->roleId === 2) {
            abort_unless(
                (string) $check->auditorId === (string) $actor->getKey()
                    && $this->scopes->canAccessDepartment($actor, MongoId::parse((string) $check->departmentId)),
                403,
            );

            return;
        }

        abort_unless(
            (int) $actor->roleId === 3
                && (string) $check->auditeeId === (string) $actor->getKey(),
            403,
        );
    }

    private function decryptAndVerify(ArchiveDocument $archive): string
    {
        $ciphertext = $this->readEncryptedFile($archive->encryptedPath);
        if ($ciphertext === null) {
            abort(404, 'The encrypted archive file is missing.');
        }
        $plaintext = $this->encryption->decrypt(
            $ciphertext,
            $archive->encryptionIv,
            $archive->encryptionTag,
        );
        if (! hash_equals($archive->checksum, hash('sha256', $plaintext))) {
            throw new \RuntimeException('Archive checksum verification failed.');
        }

        return $plaintext;
    }

    private function writeEncryptedFile(string $relativePath, string $contents): bool
    {
        $path = $this->encryptedFilePath($relativePath);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $contents, LOCK_EX) !== false;
    }

    private function readEncryptedFile(string $relativePath): ?string
    {
        $path = $this->encryptedFilePath($relativePath);
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function deleteEncryptedFile(string $relativePath): void
    {
        $path = $this->encryptedFilePath($relativePath);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function encryptedFilePath(string $relativePath): string
    {
        abort_unless(
            preg_match('/^archives\\/[A-Za-z0-9._-]+\\.enc$/', $relativePath) === 1,
            500,
            'The encrypted archive path is invalid.',
        );

        return rtrim(config('filesystems.disks.local.root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function serialize(ArchiveDocument $archive): array
    {
        $data = $archive->toArray();
        $data['_id'] = (string) $archive->getKey();
        unset($data['id'], $data['encryptedPath'], $data['encryptionIv'], $data['encryptionTag']);
        $data['departmentCode'] = Department::find($archive->departmentId)?->departmentCode;
        $data['uploadedBy'] = User::find($archive->uploadedByUserId)?->fullName;

        return $data;
    }

    private function matchesFileSignature(string $extension, string $contents): bool
    {
        return match ($extension) {
            'pdf' => str_starts_with($contents, '%PDF-'),
            'png' => str_starts_with($contents, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($contents, "\xff\xd8\xff"),
            // DOCX is a ZIP package; verify its ZIP signature without requiring fileinfo.
            'docx' => str_starts_with($contents, "PK\x03\x04")
                || str_starts_with($contents, "PK\x05\x06")
                || str_starts_with($contents, "PK\x07\x08"),
            default => false,
        };
    }
}
