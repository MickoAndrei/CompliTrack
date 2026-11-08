<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (Illuminate\Support\Facades\DB::connection('mongodb')->getDatabaseName() !== 'complitrack') { throw new RuntimeException('This command targets only complitrack development data.'); }
    $email = null; foreach ($argv as $arg) { if (str_starts_with($arg, '--atl-email=')) { $email = substr($arg, 12); } }
    $report = app(App\Services\QmsDevelopmentData::class)->run(in_array('--apply', $argv, true), $email);
    if (in_array('--summary', $argv, true)) { $report = array_intersect_key($report, array_flip(['database', 'applied', 'alreadySeeded', 'atl', 'createdAccounts', 'reusedAccounts', 'counts', 'credentialsFile', 'carClosurePolicy'])); }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL); exit(1); }
