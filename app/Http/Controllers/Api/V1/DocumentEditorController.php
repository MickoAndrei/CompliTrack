<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ArchiveDocument, User};
use App\Services\{AuditLogService, DocumentEditorAccess, DocumentEditorToken, QmsArchiveAccess, QmsAuditArchive};
use App\Support\MongoId;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{Cache, DB, Http};

class DocumentEditorController extends Controller
{
    private function cache() { return Cache::store('file'); }

    private function session(string $id): array
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id), 404);
        $session = $this->cache()->get('document-editor:'.$id);
        abort_unless($session, 410, 'This document session has expired. Reopen the document.');
        return $session;
    }

    private function workspace(array $session): array
    {
        $anchor = ArchiveDocument::findOrFail(MongoId::parse($session['anchor']));
        if ($session['mode'] === 'view' && isset($session['viewState'])) { return [$anchor, $session['viewState']]; }
        $state = $anchor->onlyofficeWorkspace ?? [];
        abort_unless(($state['id'] ?? '') === $session['workspace'] && ($state['expires'] ?? 0) > time(), 410, 'This document session has expired.');
        return [$anchor, $state];
    }

    private function actorAndDocument(array $session, array $state, bool $editing = false): array
    {
        $actor = User::findOrFail(MongoId::parse($session['actor']));
        $source = ArchiveDocument::findOrFail(MongoId::parse($session['source']));
        $access = app(DocumentEditorAccess::class);
        abort_unless($access->visible($actor, $source, $session['context']), 403, 'Document permission has changed.');
        $document = ArchiveDocument::findOrFail(MongoId::parse($state['current']));
        abort_unless((string) $source->departmentId === (string) $document->departmentId && (string) $source->collegeId === (string) $document->collegeId, 409);
        if ($editing) {
            abort_unless($session['mode'] === 'edit' && $access->canEdit($actor, $source) && $access->canEdit($actor, $document), 403, 'Editing permission has changed.');
        }
        return [$actor, $document];
    }

    private function versions(ArchiveDocument $document)
    {
        $group = MongoId::parse((string) ($document->versionGroupId ?? $document->getKey()));
        return ArchiveDocument::where(fn ($query) => $query->where('versionGroupId', $group)->orWhere('_id', $group));
    }

    public function start(Request $request, string $id, DocumentEditorToken $tokens)
    {
        abort_unless(config('documents.url') && strlen((string) config('documents.secret')) >= 32, 503, 'ONLYOFFICE is not configured. Run the existing document service startup script.');
        $request->validate(['mode' => 'sometimes|in:edit,view']);
        $document = app(QmsAuditArchive::class)->applyContext(app(QmsArchiveAccess::class)->documents($request->user()), $request->user(), $request)
            ->where('_id', MongoId::parse($id))->firstOrFail();
        $access = app(DocumentEditorAccess::class);
        $context = $request->only(array_merge(QmsAuditArchive::PATH, ['auditId']));
        abort_unless($access->visible($request->user(), $document, $context), 403);
        $mode = $request->input('mode', 'edit');
        abort_if($mode === 'edit' && !$access->canEdit($request->user(), $document), 403, 'You have read-only access to this document.');
        $extension = strtolower(pathinfo($document->originalName, PATHINFO_EXTENSION));
        abort_unless(($document->recordType ?: 'file') === 'file' && isset(DocumentEditorAccess::TYPES[$extension]), 422, 'Open a PDF, DOCX, XLSX or PPTX file.');
        $anchor = $this->versions($document)->orderBy('versionNumber')->orderBy('createdAt')->firstOrFail();
        if ($mode === 'view') {
            // Immutable revision viewers must not reserve or replace a live editing workspace.
            $state = ['id' => bin2hex(random_bytes(32)), 'key' => 'ct-view-'.hash('sha256', (string) $document->getKey().$document->checksum),
                'source' => (string) $document->getKey(), 'current' => (string) $document->getKey(), 'checksum' => $document->checksum,
                'savedVersions' => [], 'processed' => [], 'expires' => time() + 28800, 'closed' => true, 'lastStatus' => 0, 'lastError' => null];
        } else {
        [$anchor, $state] = DB::connection('mongodb')->transaction(function () use ($anchor, $document, $mode, $request) {
            ArchiveDocument::where('_id', $anchor->getKey())->increment('onlyofficeRevision');
            $anchor->refresh(); $state = $anchor->onlyofficeWorkspace ?? [];
            $latest = $this->versions($document)->orderBy('versionNumber', 'desc')->firstOrFail();
            $hasEditors = in_array('edit', $state['participants'] ?? [], true);
            $joining = $hasEditors && ($state['expires'] ?? 0) > time() && !($state['closed'] ?? true)
                && in_array((string) $document->getKey(), [$state['source'] ?? '', $state['current'] ?? ''], true)
                && (string) $latest->getKey() === ($state['current'] ?? '');
            abort_if($mode === 'edit' && !$joining && (string) $latest->getKey() !== (string) $document->getKey(), 409, 'Open the latest version before editing.');
            if (!$joining) {
                abort_if($hasEditors && ($state['expires'] ?? 0) > time() && !($state['closed'] ?? true), 409, 'Another revision is open. Preview this historical version instead.');
                $workspace = bin2hex(random_bytes(32));
                $state = ['id' => $workspace, 'key' => 'ct-'.hash('sha256', $workspace.(string) $document->getKey().$document->checksum),
                    'source' => (string) $document->getKey(), 'current' => (string) $document->getKey(), 'checksum' => $document->checksum,
                    'savedVersions' => [], 'processed' => [], 'expires' => time() + 28800, 'closed' => false, 'lastStatus' => 0, 'lastError' => null];
            }
            $actorId = (string) $request->user()->getKey();
            $state['participants'][$actorId] = $mode === 'edit' ? 'edit' : ($state['participants'][$actorId] ?? 'view');
            $anchor->forceFill(['onlyofficeWorkspace' => $state])->save();
            return [$anchor, $state];
        }, 3);
        }
        $sessionId = bin2hex(random_bytes(32));
        $session = ['actor' => (string) $request->user()->getKey(), 'anchor' => (string) $anchor->getKey(), 'workspace' => $state['id'],
            'source' => (string) $document->getKey(), 'extension' => $extension, 'mode' => $mode, 'context' => $context];
        if ($mode === 'view') { $session['viewState'] = $state; }
        $this->cache()->put('document-editor:'.$sessionId, $session, now()->addHours(8));
        $base = config('documents.app_url').'/api/document-editor/'.$sessionId;
        $permissions = ['edit' => $mode === 'edit', 'download' => true, 'print' => true, 'review' => false, 'comment' => $mode === 'edit', 'fillForms' => $mode === 'edit'];
        $config = ['documentType' => DocumentEditorAccess::TYPES[$extension], 'width' => '100%', 'height' => '100%',
            'document' => ['fileType' => $extension, 'key' => $state['key'], 'title' => $document->originalName, 'url' => $base.'/file', 'permissions' => $permissions],
            'editorConfig' => ['mode' => $mode, 'callbackUrl' => $base.'/callback',
                'user' => ['id' => (string) $request->user()->getKey(), 'name' => $request->user()->fullName],
                'customization' => ['forcesave' => $mode === 'edit']]];
        $config['token'] = $tokens->sign($config);
        app(AuditLogService::class)->record($request, $mode === 'edit' ? 'Opened ONLYOFFICE editor' : 'Opened ONLYOFFICE read-only view', 'archive_document', $document->getKey());
        $browserUrl = config('documents.url');
        if (!$request->isSecure() && in_array(strtolower($request->getHost()), ['localhost', '127.0.0.1', '[::1]', '::1'], true) && config('documents.local_url')) {
            $browserUrl = config('documents.local_url');
        }
        return response()->json(['sessionId' => $sessionId, 'scriptUrl' => $browserUrl.'/web-apps/apps/api/documents/api.js', 'config' => $config])->header('Cache-Control', 'private, no-store');
    }

    public function file(Request $request, string $session, DocumentEditorToken $tokens)
    {
        $participant = $this->session($session); [, $state] = $this->workspace($participant);
        $claims = $tokens->verify($request->bearerToken() ?: '');
        $payload = $claims['payload'] ?? $claims;
        abort_unless(is_array($payload), 403);
        abort_unless(($payload['url'] ?? '') === config('documents.app_url').'/api/document-editor/'.$session.'/file', 403);
        [$actor] = $this->actorAndDocument($participant, $state);
        $request->setUserResolver(fn () => $actor);
        $request->query->add($participant['context']);
        return app(ArchiveController::class)->download($request, $state['source']);
    }

    public function status(Request $request, string $session)
    {
        $participant = $this->session($session); [, $state] = $this->workspace($participant);
        abort_unless($participant['actor'] === (string) $request->user()->getKey(), 403);
        $this->actorAndDocument($participant, $state);
        return response()->json(\Illuminate\Support\Arr::only($state, ['savedVersions', 'lastStatus', 'lastError', 'closed']) + ['currentId' => $state['current']])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $session, DocumentEditorToken $tokens)
    {
        $participant = $this->session($session); [, $state] = $this->workspace($participant);
        abort_unless($participant['actor'] === (string) $request->user()->getKey(), 403);
        $this->actorAndDocument($participant, $state, true);
        abort_if($state['closed'] ?? true, 409, 'Reopen the editor before saving.');
        $command = ['c' => 'forcesave', 'key' => $state['key']];
        try {
            $response = Http::connectTimeout(5)->timeout(20)->withoutRedirecting()
                ->post(config('documents.internal_url').'/command', $command + ['token' => $tokens->sign($command)]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            abort(503, 'The document server could not be reached. Keep the editor open and retry Save.');
        }
        abort_unless($response->successful(), 502, 'The document server could not accept Save. Keep the editor open and retry.');
        $code = $response->json('error');
        abort_unless(in_array($code, [0, 4], true), 502, 'ONLYOFFICE could not accept Save. Keep the editor open and retry.');
        return response()->json(['message' => $code === 4
            ? 'ONLYOFFICE reports no new changes. Stored revisions are confirmed separately.'
            : 'Save requested. Waiting for CompliTrack to store the revision.'])->header('Cache-Control', 'private, no-store');
    }

    public function callback(Request $request, string $session, DocumentEditorToken $tokens)
    {
        $jwt = $request->bearerToken() ?: $request->input('token', '');
        abort_unless(is_string($jwt), 403);
        $claims = $tokens->verify($jwt);
        $payload = $claims['payload'] ?? $claims;
        abort_unless(is_array($payload), 403);
        \Illuminate\Support\Facades\Validator::make($payload, ['key' => 'required|string|max:128', 'status' => 'required|integer|in:1,2,3,4,6,7',
            'users' => 'sometimes|array|max:100', 'users.*' => 'required|string|regex:/^[a-f0-9]{24}$/', 'url' => 'sometimes|string|max:8192',
            'filetype' => 'sometimes|string|max:10'])->validate();
        $participant = $this->session($session); [$anchor, $state] = $this->workspace($participant);
        abort_unless(($payload['key'] ?? '') === $state['key'], 403);
        $status = (int) ($payload['status'] ?? 0);
        abort_unless(in_array($status, [1, 2, 3, 4, 6, 7], true), 422);
        if ($participant['mode'] === 'view') {
            $this->actorAndDocument($participant, $state);
            abort_if(in_array($status, [2, 6], true), 403, 'Read-only sessions cannot save document revisions.');
            return response()->json(['error' => 0]);
        }
        return $this->cache()->lock('document-editor-lock:'.$state['id'], 120)->block(5, function () use ($request, $participant, $payload, $tokens, $status, $anchor) {
            [, $state] = $this->workspace($participant);
            if (!in_array($status, [2, 6], true)) {
                return DB::connection('mongodb')->transaction(function () use ($participant, $anchor, $status, $request) {
                ArchiveDocument::where('_id', $anchor->getKey())->increment('onlyofficeRevision');
                [$anchor, $state] = $this->workspace($participant);
                [$actor] = $this->actorAndDocument($participant, $state);
                if ($status === 1 && $state['lastStatus'] === 4) { $state['closed'] = false; }
                $state['lastStatus'] = $status;
                $state['lastError'] = in_array($status, [3, 7], true) ? 'ONLYOFFICE could not save this document. Keep the editor open and retry.' : null;
                if ($status === 4) { $state['closed'] = true; }
                $anchor->forceFill(['onlyofficeWorkspace' => $state])->save();
                if (in_array($status, [3, 7], true)) { app(AuditLogService::class)->record($request, 'ONLYOFFICE reported a save error', 'archive_document', $anchor->getKey(), 'failure', $actor); }
                return response()->json(['error' => 0]);
                }, 3);
            }
            $this->actorAndDocument($participant, $state, true);
            foreach ($payload['users'] ?? [$participant['actor']] as $userId) {
                $writer = User::find(MongoId::parse((string) $userId));
                $source = ArchiveDocument::findOrFail(MongoId::parse($participant['source']));
                abort_unless(($state['participants'][(string) $userId] ?? '') === 'edit' && $writer && app(DocumentEditorAccess::class)->canEdit($writer, $source), 403, 'The contributing editor no longer has editing permission.');
            }
            abort_unless(($payload['filetype'] ?? $participant['extension']) === $participant['extension'], 422, 'Preserve the original file format.');
            $url = $tokens->internalDownloadUrl((string) ($payload['url'] ?? ''));
            try {
                $createdRevision = null;
                $response = Http::timeout(60)->withOptions(['allow_redirects' => false, 'progress' => function ($total, $downloaded) {
                    if ($total > 20 * 1024 * 1024 || $downloaded > 20 * 1024 * 1024) { throw new \RuntimeException('Edited file exceeds 20 MB.'); }
                }])->get($url);
                if (!$response->successful()) { throw new \RuntimeException('ONLYOFFICE returned an unavailable edited file.'); }
                $bytes = $response->body();
                if (strlen($bytes) > 20 * 1024 * 1024 || $bytes === '') { throw new \RuntimeException('The edited file is empty or exceeds 20 MB.'); }
                $checksum = hash('sha256', $bytes);
                $eventId = hash('sha256', json_encode([$checksum, $payload['url'], $status, $payload['forcesavetype'] ?? null, $payload['userdata'] ?? null, $payload['history'] ?? null]));
                $temporary = tempnam(sys_get_temp_dir(), 'ct-editor-');
                try {
                    file_put_contents($temporary, $bytes);
                    return DB::connection('mongodb')->transaction(function () use ($request, $participant, $status, $anchor, $checksum, $eventId, $temporary, &$createdRevision) {
                        ArchiveDocument::where('_id', $anchor->getKey())->increment('onlyofficeRevision');
                        [$anchor, $state] = $this->workspace($participant);
                        [$actor, $previous] = $this->actorAndDocument($participant, $state, true);
                        abort_unless(hash_equals((string) $previous->checksum, $state['checksum']), 409, 'The stored source revision changed. Reopen the document.');
                        if (!in_array($eventId, $state['processed'], true) && !hash_equals($state['checksum'], $checksum)) {
                            abort_unless(!$state['closed'] && (string) $this->versions($previous)->orderBy('versionNumber', 'desc')->firstOrFail()->getKey() === $state['current'], 409, 'A newer revision exists. Reopen it before editing.');
                            $save = Request::create('/api/v1/documents/'.$state['current'].'/versions', 'POST', [
                                'unitId' => (string) $previous->departmentId, 'categoryId' => (string) $previous->categoryId,
                                'title' => $previous->title ?: $previous->originalName, 'description' => $previous->description,
                                'recordType' => 'file', 'visibility' => $previous->visibility ?: 'owner', 'copyAuthorized' => '1']);
                            $save->attributes->set('onlyofficeSave', true);
                            $save->files->set('file', new UploadedFile($temporary, $previous->originalName, null, null, true));
                            $save->setUserResolver(fn () => $actor);
                            $saved = app(ArchiveController::class)->version($save, $state['current'])->getData(true)['data'];
                            $revision = ArchiveDocument::findOrFail(MongoId::parse($saved['_id']));
                            $createdRevision = $revision;
                            $revision->forceFill(['onlyofficeSave' => ['workspace' => $state['id'], 'checksum' => $checksum, 'status' => $status, 'actorId' => $actor->getKey()],
                                'referenceStandard' => $previous->referenceStandard, 'tags' => $previous->tags, 'auditId' => $previous->auditId, 'requestId' => $previous->requestId])->save();
                            $state['current'] = $saved['_id']; $state['checksum'] = $checksum;
                            $state['savedVersions'][] = ['_id' => $saved['_id'], 'versionNumber' => $saved['versionNumber']];
                            app(AuditLogService::class)->record($request, 'Saved ONLYOFFICE document version', 'archive_document', $revision->getKey(), 'success', $actor);
                        }
                        if (!in_array($eventId, $state['processed'], true)) { $state['processed'][] = $eventId; }
                        $state['lastStatus'] = $status; $state['lastError'] = null;
                        if ($status === 2) { $state['closed'] = true; }
                        $anchor->forceFill(['onlyofficeWorkspace' => $state])->save();
                        return response()->json(['error' => 0]);
                    }, 1);
                } finally { if (is_file($temporary)) { unlink($temporary); } }
            } catch (\Throwable $exception) {
                if ($createdRevision && !ArchiveDocument::where('_id', $createdRevision->getKey())->exists()
                    && preg_match('~^archives/[A-Za-z0-9._-]+\.enc$~', $createdRevision->encryptedPath)) {
                    $orphan = rtrim(config('filesystems.disks.local.root'), '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $createdRevision->encryptedPath);
                    if (is_file($orphan)) { unlink($orphan); }
                }
                report($exception);
                \Illuminate\Support\Facades\Log::warning('ONLYOFFICE revision was not persisted', ['reason' => $exception instanceof \Illuminate\Validation\ValidationException ? $exception->errors() : get_class($exception),
                    'validation' => $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException ? $exception->getMessage() : null]);
                [$anchor, $state] = $this->workspace($participant);
                $state['lastError'] = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $exception->getStatusCode() === 409
                    ? 'A newer or changed revision exists. Check version history and reopen the latest document before editing.'
                    : 'CompliTrack could not persist this revision. Keep the editor open and retry; check version history.';
                ArchiveDocument::where('_id', $anchor->getKey())->where('onlyofficeWorkspace.id', $participant['workspace'])
                    ->update(['onlyofficeWorkspace.lastError' => $state['lastError']]);
                app(AuditLogService::class)->record($request, 'ONLYOFFICE save failed', 'archive_document', $anchor->getKey(), 'failure', User::find(MongoId::parse($participant['actor'])));
                return response()->json(['error' => 1]);
            }
        });
    }
}
