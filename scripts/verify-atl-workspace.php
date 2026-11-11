<?php
// Read-only live verification and private browser fixtures. No seeding, logins or writes to MongoDB.
try {
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$admins = App\Models\User::where('roleId', 1)->where('isActive', true)->get();
if ($admins->count() !== 1) { throw new RuntimeException('Select the intended existing Admin account before live verification.'); }
$actor = $admins->first();
Illuminate\Support\Facades\Auth::setUser($actor);
$workspace = app(App\Services\AtlWorkspace::class);
$data = $workspace->dashboard($actor);
$directory = storage_path('framework/testing/atl-live');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
file_put_contents($directory.'/dashboard.json', json_encode(['data' => $data], JSON_THROW_ON_ERROR));
Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag());
app(Illuminate\Foundation\Vite::class)->useHotFile($directory.'/no-hot-file');
$requestFor = function (string $uri) use ($app, $actor) {
    $request = Illuminate\Http\Request::create($uri);
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);
    return $request;
};
$writeView = function (string $name, $view) use ($directory) {
    $html = $view->render();
    $html = preg_replace('/<link[^>]+(?:fonts\.googleapis|fonts\.gstatic)[^>]*>/', '', $html);
    file_put_contents($directory.'/'.$name.'.html', str_replace('http://localhost', '', $html));
};
foreach (['dashboard', 'users', 'departments', 'archives', 'reports', 'notifications', 'compliance-records'] as $page) {
    $requestFor('/admin/'.$page);
    $writeView($page, view('admin.'.$page));
}
$request = $requestFor('/audits');
$writeView('assignments', app(App\Http\Controllers\Web\AuditController::class)->index($request));
$request = $requestFor('/admin/compliance-records?monitor=1');
$writeView('monitor', app(App\Http\Controllers\Web\AtlWorkspaceController::class)->records($request, $workspace));
foreach ([
    'users' => [App\Http\Controllers\Api\V1\UserController::class, 'index', '/api/v1/users'],
    'departments' => [App\Http\Controllers\Api\V1\DepartmentController::class, 'index', '/api/v1/departments'],
    'checks' => [App\Http\Controllers\Api\V1\ComplianceCheckController::class, 'index', '/api/v1/compliance-checks'],
    'notifications' => [App\Http\Controllers\Api\V1\NotificationController::class, 'index', '/api/v1/notifications'],
    'tree' => [App\Http\Controllers\Api\V1\QmsArchiveController::class, 'tree', '/api/v1/archives/tree'],
    'archives' => [App\Http\Controllers\Api\V1\ArchiveController::class, 'index', '/api/v1/archives'],
    'reports' => [App\Http\Controllers\Api\V1\ReportController::class, 'generate', '/api/v1/reports'],
] as $name => [$controller, $method, $uri]) {
    $request = $requestFor($uri.'?perPage=10');
    file_put_contents($directory.'/'.$name.'.json', app($controller)->$method($request)->getContent());
}
$audit = $workspace->audits($actor)->where('status', 'submitted')->first();
$hasReview = false;
if ($audit) {
    $request = $requestFor('/audits/'.$audit->getKey().'?review=1');
    $response = app(App\Http\Controllers\Web\AuditController::class)->show($request, (string) $audit->getKey());
    file_put_contents($directory.'/review.html', $response->getContent());
    file_put_contents($directory.'/review.json', json_encode(['id' => (string) $audit->getKey(), 'version' => $audit->version]));
    $hasReview = true;
} else {
    Illuminate\Support\Facades\File::delete([$directory.'/review.html', $directory.'/review.json']);
}
echo json_encode(['stats' => $data['stats'], 'people' => $data['people'], 'cetDepartments' => $data['departmentCompliance']->count(),
    'assignments' => $data['assignments']->count(), 'reviewFixtureAvailable' => $hasReview,
    'databaseWrites' => 0], JSON_PRETTY_PRINT), PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Read-only verification failed. Check MongoDB connectivity; no connection credentials are printed.'.PHP_EOL);
    exit(1);
}
