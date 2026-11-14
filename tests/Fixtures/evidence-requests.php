<?php
// Render deterministic browser fixtures with the production views; no database writes.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$user = new App\Models\User(['fullName' => 'Browser Test', 'roleId' => 3, 'isActive' => true]);
$user->setAttribute('_id', new MongoDB\BSON\ObjectId());
Illuminate\Support\Facades\Auth::setUser($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$audit = new App\Models\Audit(['auditCode' => 'BROWSER-AUDIT', 'title' => 'Department evidence review',
    'departmentName' => 'Engineering and Technology', 'status' => 'ready', 'version' => 1]);
$audit->setAttribute('_id', new MongoDB\BSON\ObjectId('0123456789abcdef01234567'));
$entry = ['id' => '11111111-1111-4111-8111-111111111111', 'title' => 'Authorized department records',
    'description' => str_repeat('Provide a permitted reference or present the original record for inspection. ', 5),
    'referenceStandard' => 'ISO9001-9.2', 'status' => 'requested', 'allowUpload' => true, 'dueDate' => '2026-12-20',
    'responses' => [['method' => 'physical-original', 'notes' => 'Original available for inspection',
        'at' => '2026-11-08', 'availability' => 'available', 'review' => ['feedback' => 'Please provide the complete original', 'signature' => 'missing-incomplete']]]];
$service = app(App\Services\AuditeeEvidenceService::class);
$records = collect(['requested', 'response_submitted', 'reviewed'])->map(function ($status, $i) use ($audit, $entry, $service) {
    return $service->requestCard($audit, array_merge($entry, ['id' => sprintf('11111111-1111-4111-8111-%012d', $i + 1), 'status' => $status]), 'Assigned Auditor');
});
$summary = ['active' => 2, 'awaiting' => 1, 'submitted' => 1, 'completed' => 1];
$directory = storage_path('framework/testing/evidence-browser');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
$bundle = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/js/app.js']['file']));
foreach (['populated', 'empty'] as $state) {
    $items = $state === 'populated' ? $records : collect();
    $html = view('auditee.evidence-requests', ['requests' => new Illuminate\Pagination\LengthAwarePaginator($items, $items->count(), 12),
        'summary' => $state === 'populated' ? $summary : array_fill_keys(array_keys($summary), 0)])->render();
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
    $html = preg_replace('/<link[^>]+(?:fonts\.googleapis|fonts\.gstatic)[^>]*>/', '', $html);
    $html = str_replace([rtrim(asset('/'), '/'), 'http://localhost'], 'file:///'.str_replace('\\', '/', public_path()), $html);
    $html = str_replace('</body>', '<script type="module" src="'.$bundle.'"></script></body>', $html);
    file_put_contents($directory.'/'.$state.'.html', $html);
}
$record = $service->requestCard($audit, $entry, 'Assigned Auditor');
foreach (['detail', 'locked'] as $state) {
    if ($state === 'locked') { $record['canRespond'] = false; $record['status'] = 'response_submitted'; $record['statusLabel'] = 'Submitted'; }
    file_put_contents($directory.'/'.$state.'.html', view('auditee.evidence-request-detail', ['record' => $record, 'entry' => $entry, 'version' => 1, 'downloadIds' => []])->render());
}
echo "Evidence browser fixtures rendered.\n";
