<?php

// Public root is still public/. Diagnostics require a random per-process secret header.
$root = dirname(__DIR__, 2);
$token = getenv('COMPLITRACK_DIAGNOSTIC_TOKEN');
$profiled = $token && hash_equals($token, $_SERVER['HTTP_X_PERFORMANCE_TOKEN'] ?? '')
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!$profiled) { return require $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'; }
if ($path === '/_dev/runtime') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['opcache' => (bool) (function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false)),
        'validateTimestamps' => ini_get('opcache.validate_timestamps'), 'revalidateFrequency' => ini_get('opcache.revalidate_freq'),
        'sapi' => PHP_SAPI, 'pid' => getmypid()]);
    return;
}
if ($path !== '/' && is_file(getcwd().urldecode($path))) { return false; }
define('LARAVEL_START', microtime(true));
require $root.'/vendor/autoload.php';
require __DIR__.'/RequestProfile.php';
$app = require $root.'/bootstrap/app.php';
$request = \Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$bootstrapMs = (microtime(true) - LARAVEL_START) * 1000;
$profile = new \CompliTrack\Diagnostics\RequestProfile();
$request->attributes->set('performance.profile', $profile);
$app['events']->listen(\Illuminate\Database\Events\ConnectionEstablished::class, function () use ($profile) {
    $profile->connectionCreatedAt ??= microtime(true);
});
$app['events']->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
    $event->route->middleware(\CompliTrack\Diagnostics\ActionTimer::class);
});
\MongoDB\Driver\Monitoring\addSubscriber($profile);
$session = $app['session']->driver();
if (in_array(config('session.driver'), ['mongodb', 'database', 'file'], true)) {
    $session->setHandler(new \CompliTrack\Diagnostics\TimedSessionHandler($session->getHandler(), $profile));
}
$start = microtime(true);
try {
    $response = $kernel->handle($request);
    $totalMs = (microtime(true) - $start) * 1000;
    $metrics = ['bootstrap' => $bootstrapMs, 'app' => $totalMs, 'action' => $profile->actionMs,
        'middleware' => max(0, $totalMs - $profile->actionMs),
        'session_read' => $profile->sessionMs['read'] ?? 0,
        'session_write' => $profile->sessionMs['write'] ?? 0,
        'mongo_commands' => array_sum($profile->durations),
        // Upper bound: includes PHP work between constructing the connection and its first command.
        'first_mongo_wait' => $profile->connectionCreatedAt && $profile->firstCommandAt
            ? ($profile->firstCommandAt - $profile->connectionCreatedAt) * 1000 : 0];
    $response->headers->set('Server-Timing', implode(', ', array_map(fn ($name, $ms) => $name.';dur='.round($ms, 2), array_keys($metrics), $metrics)));
    $response->headers->set('X-Performance-Counts', json_encode($profile->counts));
    $response->headers->set('X-Development-OPcache', function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false) ? 'enabled' : 'disabled');
    $response->send();
    $kernel->terminate($request, $response);
} finally { \MongoDB\Driver\Monitoring\removeSubscriber($profile); }
