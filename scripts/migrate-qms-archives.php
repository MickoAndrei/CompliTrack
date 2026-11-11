<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('This script only targets the main complitrack development database.'); }
echo json_encode(app(App\Services\QmsArchiveMigration::class)->run(in_array('--apply', $argv, true)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
