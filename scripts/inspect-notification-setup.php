<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
$report = ['database' => $db->getDatabaseName(), 'demoITCollege' => \App\Models\Department::where('departmentCode', 'DEMO-IT')->value('collegeName'),
    'auditCount' => \App\Models\Audit::count()];
foreach (['audits', 'notifications'] as $collection) {
    $report[$collection.'Indexes'] = array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($db->selectCollection($collection)->listIndexes()));
}
echo json_encode($report, JSON_PRETTY_PRINT), PHP_EOL;
