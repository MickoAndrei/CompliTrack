<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$name = 'ct_perf_storage_'.bin2hex(random_bytes(6));
$connection = config('database.connections.mongodb');
$connection['database'] = $name;
config(['database.connections.performance' => $connection,
    'session.driver' => 'mongodb', 'session.connection' => 'performance',
    'cache.stores.performance' => ['driver' => 'mongodb', 'connection' => 'performance', 'table' => 'cache', 'prefix' => 'diagnostic']]);
$database = \Illuminate\Support\Facades\DB::connection('performance')->getMongoDB();
if ($database->getDatabaseName() !== $name || !str_starts_with($name, 'ct_perf_storage_')) { throw new \RuntimeException('Unsafe test database.'); }
$directory = storage_path('framework/testing/'.$name);
mkdir($directory, 0700, true);
$files = new \Illuminate\Filesystem\Filesystem();
$fileHandler = new \Illuminate\Session\FileSessionHandler($files, $directory, 120);
$mongoHandler = app('session')->driver()->getHandler();
$fileCache = new \Illuminate\Cache\FileStore($files, $directory.'/cache');
$mongoCache = app('cache')->store('performance');
$id = bin2hex(random_bytes(20));
$key = 'diagnostic-'.bin2hex(random_bytes(8));
$measure = function (callable $callback): float { $start = microtime(true); $callback(); return round((microtime(true) - $start) * 1000, 2); };
$result = ['isolatedDatabase' => $name, 'sessions' => [], 'cache' => []];
try {
    $result['initialConnectionMs'] = $measure(fn () => $database->command(['ping' => 1])->toArray());
    foreach (['mongodb' => $mongoHandler, 'file' => $fileHandler] as $driver => $handler) {
        $result['sessions'][$driver]['writeMs'] = $measure(fn () => $handler->write($id, 'diagnostic-only-payload'));
        for ($i = 0; $i < 3; $i++) { $result['sessions'][$driver]['readMs'][] = $measure(function () use ($handler, $id) {
            if ($handler->read($id) !== 'diagnostic-only-payload') { throw new \RuntimeException('Session payload mismatch.'); }
        }); }
    }
    foreach (['mongodb' => $mongoCache, 'file' => $fileCache] as $driver => $cache) {
        $result['cache'][$driver]['writeMs'] = $measure(fn () => $cache->put($key, 'diagnostic-only-value', 60));
        for ($i = 0; $i < 3; $i++) { $result['cache'][$driver]['readMs'][] = $measure(function () use ($cache, $key) {
            if ($cache->get($key) !== 'diagnostic-only-value') { throw new \RuntimeException('Cache payload mismatch.'); }
        }); }
    }
    file_put_contents(storage_path('framework/testing/phase2-storage.json'), json_encode($result, JSON_PRETTY_PRINT));
    echo json_encode($result), PHP_EOL;
} finally {
    $fileHandler->destroy($id);
    $fileCache->forget($key);
    if ($database->getDatabaseName() === $name && str_starts_with($name, 'ct_perf_storage_')) { $database->drop(); }
}
