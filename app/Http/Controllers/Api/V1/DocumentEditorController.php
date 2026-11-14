<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ArchiveDocument, User};
use App\Services\{DocumentEditorToken, QmsArchiveAccess};
use App\Support\MongoId;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{Cache, Http};

class DocumentEditorController extends Controller
{
    private function cache() { return Cache::store('file'); }

    private function session(string $id): array
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id), 404);
        $session = $this->cache()->get('document-editor:'.$id);
        abort_unless($session, 410, 'This editing session has expired.');
        return $session;
    }

    private function actorAndDocument(array $session): array
    {
        $actor = User::findOrFail(MongoId::parse($session['actor']));
        $document = ArchiveDocument::findOrFail(MongoId::parse($session['current']));
        abort_unless($actor->isActive && app(QmsArchiveAccess::class)->canManage($actor, $document), 403);
        return [$actor, $document];
    }

    public function start(Request $request, string $id, DocumentEditorToken $tokens)
    {
        abort_unless(config('documents.url') && config('documents.secret'), 503, 'Start the local document server before editing files.');
        $document = app(QmsArchiveAccess::class)->documents($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        abort_unless(app(QmsArchiveAccess::class)->canManage($request->user(), $document), 403);
        $extension = strtolower(pathinfo($document->originalName, PATHINFO_EXTENSION));
        abort_unless(($document->recordType ?: 'file') === 'file' && in_array($extension, ['pdf', 'docx'], true), 422, 'Only PDF and DOCX files can be edited.');
        $sessionId = bin2hex(random_bytes(32));
        $key = 'ct-'.$sessionId;
        $session = ['actor' => (string) $request->user()->getKey(), 'source' => $id, 'current' => $id,
            'key' => $key, 'extension' => $extension, 'checksum' => $document->checksum, 'savedVersions' => []];
        $this->cache()->put('document-editor:'.$sessionId, $session, now()->addHours(8));
        $base = config('documents.app_url').'/api/document-editor/'.$sessionId;
        $config = ['documentType' => $extension === 'pdf' ? 'pdf' : 'word', 'width' => '100%', 'height' => '100%',
            'document' => ['fileType' => $extension, 'key' => $key, 'title' => $document->originalName, 'url' => $base.'/file',
                'permissions' => ['edit' => true, 'download' => true, 'print' => true]],
            'editorConfig' => ['mode' => 'edit', 'callbackUrl' => $base.'/callback',
                'user' => ['id' => (string) $request->user()->getKey(), 'name' => $request->user()->fullName],
                'customization' => ['forcesave' => true]]];
        $config['token'] = $tokens->sign($config);
        return response()->json(['sessionId' => $sessionId, 'scriptUrl' => config('documents.url').'/web-apps/apps/api/documents/api.js', 'config' => $config]);
    }

    public function file(Request $request, string $session)
    {
        $state = $this->session($session);
        [$actor] = $this->actorAndDocument($state);
        $request->setUserResolver(fn () => $actor);
        return app(ArchiveController::class)->download($request, $state['source']);
    }

    public function status(Request $request, string $session)
    {
        $state = $this->session($session);
        abort_unless($state['actor'] === (string) $request->user()->getKey(), 403);
        $this->actorAndDocument($state);
        return response()->json(['currentId' => $state['current'], 'savedVersions' => $state['savedVersions']]);
    }

    public function callback(Request $request, string $session, DocumentEditorToken $tokens)
    {
        $claims = $tokens->verify($request->bearerToken() ?: (string) $request->input('token', ''));
        $payload = $claims['payload'] ?? $claims;
        $state = $this->session($session);
        abort_unless(($payload['key'] ?? '') === $state['key'], 403);
        $status = (int) ($payload['status'] ?? 0);
        if (in_array($status, [3, 7], true)) return response()->json(['error' => 1]);
        if (!in_array($status, [2, 6], true)) return response()->json(['error' => 0]);
        return $this->cache()->lock('document-editor-lock:'.$session, 120)->block(5, function () use ($session, $payload, $tokens) {
            $state = $this->session($session);
            [$actor, $previous] = $this->actorAndDocument($state);
            abort_unless(($payload['filetype'] ?? $state['extension']) === $state['extension'], 422, 'Preserve the original file format.');
            $url = $tokens->internalDownloadUrl((string) ($payload['url'] ?? ''));
            $response = Http::timeout(60)->withOptions(['allow_redirects' => false, 'progress' => function ($total, $downloaded) {
                if ($total > 20 * 1024 * 1024 || $downloaded > 20 * 1024 * 1024) throw new \RuntimeException('Edited file exceeds 20 MB.');
            }])->get($url);
            if (!$response->successful()) return response()->json(['error' => 1]);
            $bytes = $response->body();
            abort_if(strlen($bytes) > 20 * 1024 * 1024, 422);
            $checksum = hash('sha256', $bytes);
            if (hash_equals($state['checksum'], $checksum)) return response()->json(['error' => 0]);
            $temporary = tempnam(sys_get_temp_dir(), 'ct-editor-');
            try {
                file_put_contents($temporary, $bytes);
                $save = Request::create('/api/v1/archives/'.$state['current'].'/versions', 'POST', [
                    'unitId' => (string) $previous->departmentId, 'categoryId' => (string) $previous->categoryId,
                    'title' => $previous->title ?: $previous->originalName, 'description' => $previous->description,
                    'recordType' => 'file', 'visibility' => $previous->visibility ?: 'owner', 'copyAuthorized' => '1',
                ]);
                $save->files->set('file', new UploadedFile($temporary, $previous->originalName, null, null, true));
                $save->setUserResolver(fn () => $actor);
                $result = app(ArchiveController::class)->version($save, $state['current']);
                $saved = $result->getData(true)['data'];
                $state['current'] = $saved['_id']; $state['checksum'] = $checksum;
                $state['savedVersions'][] = ['_id' => $saved['_id'], 'versionNumber' => $saved['versionNumber']];
                $this->cache()->put('document-editor:'.$session, $state, now()->addHours(8));
                return response()->json(['error' => 0]);
            } finally { if (is_file($temporary)) unlink($temporary); }
        });
    }
}
