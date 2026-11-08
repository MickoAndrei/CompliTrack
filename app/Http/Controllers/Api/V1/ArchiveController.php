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
        $request->validate(['search' => 'nullable|string|max:120', 'collegeId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i', 'unitId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i',
            'categoryId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i', 'recordType' => 'nullable|in:file,digital-reference,physical-original']);
        $query = $this->scopes->archives($request->user());
        if ($request->filled('collegeId')) { $query->where('collegeId', MongoId::parse($request->query('collegeId'))); }
        if ($request->filled('unitId')) { $query->whereIn('departmentId', [MongoId::parse($request->query('unitId')), $request->query('unitId')]); }
        if ($request->filled('categoryId')) {
            $category = \App\Models\QmsCategory::find(MongoId::parse($request->query('categoryId')));
            $ids = $category ? app(\App\Services\QmsArchiveAccess::class)->ids(\App\Models\QmsCategory::where('parentId', MongoId::parse((string) $category->getKey()))->get(['_id'])->map(fn ($child) => $child->getKey())->push($category->getKey())->all()) : [];
            $query->whereIn('categoryId', $ids);
        }
        if ($request->filled('recordType')) {
            if ($request->query('recordType') === 'file') { $query->where(fn ($q) => $q->where('recordType', 'file')->orWhereNull('recordType')); }
            else { $query->where('recordType', $request->query('recordType')); }
        }
        if ($request->filled('department')) {
            $departmentCode = (string) $request->query('department');
            $department = Department::where('departmentCode', $departmentCode)->first();
            if (! $department) {
                return response()->json(['data' => [], 'meta' => ['total' => 0]]);
            }
            $query->where('departmentId', MongoId::parse((string) $department->getKey()));
        }
        if ($request->filled('referenceStandard')) {
            $query->where('referenceStandard', $request->query('referenceStandard'));
        }
        if ($request->filled('search')) {
            $search = '/'.preg_quote(trim((string) $request->query('search')), '/').'/i';
            $query->where(function ($query) use ($search) {
                $query->where('originalName', 'regex', $search)
                    ->orWhere('fileCode', 'regex', $search)->orWhere('title', 'regex', $search)->orWhere('documentReference', 'regex', $search);
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
        if ($request->input('purpose') === 'qms') { return $this->storeQms($request); }
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'checkId' => ['required_without:auditId', 'nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'auditId' => ['required_without:checkId', 'nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'requestId' => ['required_with:auditId', 'string', 'uuid'],
            'copyAuthorized' => ['sometimes', 'accepted'],
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
        $check = null; $audit = null;
        if (!empty($data['auditId'])) {
            $request->validate(['copyAuthorized' => 'required|accepted']);
            abort_unless((int) $actor->roleId === 3, 403);
            $workflow = app(\App\Services\AuditeeEvidenceService::class);
            $audit = $workflow->audit($actor, $data['auditId']);
            $index = $workflow->requestIndex($audit, $data['requestId'], $actor);
            $evidenceRequest = $audit->evidenceRequests[$index];
            abort_unless(in_array($audit->status, ['draft', 'ready', 'returned'], true)
                && in_array($evidenceRequest['status'], ['requested', 'needs_information'], true)
                && ($evidenceRequest['allowUpload'] ?? false)
                && $evidenceRequest['referenceStandard'] === $data['referenceStandard'], 403);
        } else {
        $check = ComplianceCheck::find(MongoId::parse($data['checkId'], 'checkId'));
        if (! $check || $check->referenceStandard !== $data['referenceStandard']) {
            throw ValidationException::withMessages([
                'checkId' => 'The selected check does not match the selected standard.',
            ]);
        }
        $this->authorizeCheckUpload($actor, $check);
        }

        $departmentId = MongoId::parse((string) ($audit ?? $check)->departmentId, 'departmentId');
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
                'ownershipVerified' => Department::find($departmentId)?->ownershipVerified === true,
                'collegeId' => Department::find($departmentId)?->collegeId,
                'uploadedByUserId' => $actor->getKey(),
                'referenceStandard' => $data['referenceStandard'],
                'tags' => $data['tags'] ?? [],
                'isEncrypted' => true,
                'encryptionIv' => $encrypted['iv'],
                'encryptionTag' => $encrypted['tag'],
                'auditId' => $audit ? (string) $audit->getKey() : null,
                'requestId' => $data['requestId'] ?? null,
            ]);

            if ($check) {
            $linked = $check->getConnection()->getMongoDB()
                ->selectCollection($check->getTable())
                ->updateOne(
                    ['_id' => MongoId::parse((string) $check->getKey()), 'result' => 'non_compliant', 'correctiveStatus' => ['$in' => ['open', 'in_progress']]],
                    ['$addToSet' => ['linkedArchiveIds' => $archive->getKey()]],
                );
            if ($linked->getMatchedCount() !== 1) {
                abort(409, 'The selected check is no longer eligible for evidence.');
            }
            }

            if ($check && (int) $actor->roleId === 3) {
                Notification::create([
                    'recipientUserId' => $check->auditorId,
                    'senderUserId' => $actor->getKey(),
                    'type' => 'evidence_uploaded',
                    'title' => 'Evidence uploaded',
                    'message' => "{$actor->fullName} uploaded evidence for {$check->checkCode}.",
                    'relatedCheckId' => $check->getKey(),
                    'isRead' => false,
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
        $archive = app(\App\Services\QmsArchiveAccess::class)->documents($request->user(), true)->where('_id', MongoId::parse($id))->firstOrFail();
        $plaintext = $this->decryptAndVerify($archive);
        $this->auditLogs->record($request, 'Accessed authorized archive file', 'archive_document', $archive->getKey());

        return response($plaintext, 200, [
            'Content-Type' => $archive->mimeType,
            'Content-Disposition' => 'attachment; filename="'.addslashes($archive->originalName).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verifyChecksum(Request $request, string $id)
    {
        $archive = app(\App\Services\QmsArchiveAccess::class)->documents($request->user(), true)->where('_id', MongoId::parse($id))->firstOrFail();
        $this->decryptAndVerify($archive);

        return response()->json(['isVerified' => true]);
    }

    public function version(Request $request, string $id)
    {
        $previous = $this->findInScope($request, $id);
        abort_unless(app(\App\Services\QmsArchiveAccess::class)->canManage($request->user(), $previous), 403);
        return $this->storeQms($request, $previous);
    }

    private function storeQms(Request $request, ?ArchiveDocument $previous = null)
    {
        $data = $request->validate([
            'unitId' => 'required|string|regex:/^[a-f0-9]{24}$/i', 'categoryId' => 'required|string|regex:/^[a-f0-9]{24}$/i',
            'title' => 'required|string|max:255', 'description' => 'nullable|string|max:3000',
            'recordType' => 'required|in:file,digital-reference,physical-original',
            'documentReference' => 'required_if:recordType,digital-reference|nullable|string|max:500',
            'physicalLocation' => 'required_if:recordType,physical-original|nullable|string|max:500',
            'visibility' => 'required|in:owner,department,college,restricted', 'downloadAllowed' => 'sometimes|boolean',
            'accessUserIds' => 'sometimes|array|max:100', 'accessUserIds.*' => 'string|regex:/^[a-f0-9]{24}$/i|distinct',
        ]);
        $access = app(\App\Services\QmsArchiveAccess::class);
        $unit = Department::findOrFail(MongoId::parse($data['unitId']));
        abort_unless($access->canCreate($request->user(), $unit), 403);
        $category = \App\Models\QmsCategory::where('_id', MongoId::parse($data['categoryId']))->where('isActive', true)
            ->where('collegeId', MongoId::parse((string) $unit->collegeId))->where('unitType', $unit->unitType)->firstOrFail();
        if ($previous) {
            abort_unless((string) $previous->departmentId === (string) $unit->getKey() && ($previous->recordType ?: 'file') === $data['recordType'], 422);
        }
        $path = null;
        $fileData = [];
        $code = $this->counters->next('ARCH', yearly: true);
        if ($data['recordType'] === 'file') {
            $request->validate(['file' => 'required|file|max:20480', 'copyAuthorized' => 'required|accepted']);
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            abort_unless(isset(self::MIME_TYPES[$extension]) && $this->matchesFileSignature($extension, $file->getContent()), 422, 'The file does not match an allowed PDF, DOCX, PNG or JPG format.');
            $encrypted = $this->encryption->encrypt($file->getContent());
            $path = 'archives/'.$code.'.enc';
            if (!$this->writeEncryptedFile($path, $encrypted['ciphertext'])) { throw new \RuntimeException('Unable to save encrypted archive.'); }
            $fileData = ['originalName' => basename($file->getClientOriginalName()), 'encryptedPath' => $path,
                'mimeType' => self::MIME_TYPES[$extension], 'fileSize' => $file->getSize(), 'checksum' => hash('sha256', $file->getContent()),
                'isEncrypted' => true, 'encryptionIv' => $encrypted['iv'], 'encryptionTag' => $encrypted['tag'], 'copyAuthorized' => true];
        }
        $metadata = [
            'fileCode' => $code, 'title' => $data['title'], 'originalName' => $data['title'], 'description' => $data['description'] ?? null,
            'departmentId' => $unit->getKey(), 'collegeId' => $unit->collegeId, 'categoryId' => $category->getKey(), 'ownershipVerified' => true,
            'uploadedByUserId' => $previous?->uploadedByUserId ?? $request->user()->getKey(), 'recordType' => $data['recordType'],
            'documentReference' => $data['documentReference'] ?? null, 'physicalLocation' => $data['physicalLocation'] ?? null,
            'signatureRequired' => $data['recordType'] === 'physical-original', 'signatureStatus' => $data['recordType'] === 'physical-original' ? 'not_verified' : 'not_required',
            'visibility' => $previous ? ($previous->visibility ?: 'owner') : $data['visibility'],
            'accessUserIds' => $previous ? ($previous->accessUserIds ?? []) : $access->ids($data['accessUserIds'] ?? []),
            'downloadAllowed' => $previous ? $previous->downloadAllowed !== false : $request->boolean('downloadAllowed', true),
            'sharedAuditIds' => [], 'versionGroupId' => $previous ? ($previous->versionGroupId ?? $previous->getKey()) : new \MongoDB\BSON\ObjectId(),
            'versionNumber' => $previous ? ($previous->versionNumber ?: 1) + 1 : 1, 'previousVersionId' => $previous?->getKey(), 'qmsSchemaVersion' => 1,
        ];
        try { $document = ArchiveDocument::create(array_merge($metadata, $fileData)); }
        catch (Throwable $exception) {
            if ($path) { $this->deleteEncryptedFile($path); }
            if ((int) $exception->getCode() === 11000) { abort(409, 'This version already exists. Refresh before creating another version.'); }
            throw $exception;
        }
        $this->auditLogs->record($request, $previous ? 'Created QMS document version' : 'Created QMS archive document', 'archive_document', $document->getKey());
        return response()->json(['data' => $this->serialize($document)], 201);
    }

    private function findInScope(Request $request, string $id): ArchiveDocument
    {
        return $this->scopes->archives($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
    }

    private function authorizeCheckUpload(User $actor, ComplianceCheck $check): void
    {
        if ($check->result !== 'non_compliant' || !in_array($check->correctiveStatus, ['open', 'in_progress'], true)) {
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
                && (string) $check->auditeeId === (string) $actor->getKey()
                && (string) $check->departmentId === (string) $actor->departmentId,
                // Uploads never grant cross-department access.
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
        if (request()->user()) { return app(\App\Services\QmsArchivePresenter::class)->document($archive, request()->user()); }
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
            'docx' => $this->isDocx($contents),
            default => false,
        };
    }

    private function isDocx(string $contents): bool
    {
        if (!class_exists(\ZipArchive::class)) { return false; }
        $path = tempnam(sys_get_temp_dir(), 'ct-docx-');
        try {
            file_put_contents($path, $contents);
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) { return false; }
            $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false;
            $zip->close(); return $valid;
        } finally { if (is_file($path)) { unlink($path); } }
    }
}
