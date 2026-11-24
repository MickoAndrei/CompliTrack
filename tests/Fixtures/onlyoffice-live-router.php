<?php
// Dedicated CLI test server only. This file is never included by normal Laravel routes.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$public = realpath(__DIR__.'/../../public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($public.'/'.ltrim($path, '/'));
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) { return false; }
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::capture());
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(storage_path('framework/testing/onlyoffice-live.json')), true);
if (!preg_match('/^ct_oo_live_[a-f0-9]{10}$/', $fixture['database'])) { throw new RuntimeException('Invalid isolated database.'); }
$directory = storage_path('framework/testing/'.$fixture['database']);
config(['app.env' => 'testing', 'app.debug' => false, 'app.url' => 'http://127.0.0.1:8017', 'session.secure' => false,
    'sanctum.stateful' => ['127.0.0.1:8017'],
    'database.connections.mongodb.database' => $fixture['database'], 'filesystems.disks.local.root' => $directory,
    'session.driver' => 'file', 'session.files' => $directory.'/sessions', 'session.cookie' => 'ct_onlyoffice_test',
    'services.complitrack.encryption_key' => base64_encode(str_repeat('l', 32)), 'documents.url' => 'http://127.0.0.1:8082',
    'documents.app_url' => 'http://host.docker.internal:8017']);
Illuminate\Support\Facades\DB::purge('mongodb');
Illuminate\Support\Facades\Route::middleware('web')->get('/__onlyoffice_fixture_login', function (Illuminate\Http\Request $request) use ($fixture) {
    abort_unless(hash_equals($fixture['secret'], (string) $request->header('X-Fixture-Secret')), 403);
    $actor = $fixture['users'][$request->query('actor', 'chair')] ?? null; abort_unless($actor, 404);
    Illuminate\Support\Facades\Auth::guard('web')->loginUsingId($actor);
    $request->session()->regenerate();
    return response()->json(['csrf' => csrf_token()]);
});
$app->handleRequest(Illuminate\Http\Request::capture());
