<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
    if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('This command only targets the main complitrack database.'); }
    $leaders = App\Models\User::where('roleId', 1)->where('isActive', true)->get()
        ->filter(fn ($user) => app(App\Services\QmsArchiveAccess::class)->isAuditTeamLeader($user));
    if ($leaders->count() !== 1) { throw new RuntimeException('Exactly one active assigned Audit Team Leader is required for this import.'); }
    $actor = $leaders->first();
    $report = app(App\Services\MockArchiveImporter::class)->run(base_path('dataset_files/CompliTrack_CET_Demo_Kit-3'), $actor, in_array('--apply', $argv, true));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
