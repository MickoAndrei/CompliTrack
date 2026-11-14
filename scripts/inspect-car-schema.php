<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
foreach ($db->listCollectionNames() as $name) {
    if (preg_match('/car|corrective|compliance|finding/', $name)) {
        $fields = []; $count = 0;
        foreach ($db->selectCollection($name)->find([], ['limit' => 500]) as $row) {
            $count++; foreach ($row as $key => $value) { $fields[$key] = get_debug_type($value); }
            if (isset($row['sourceMetadata'])) { $fields['sourceMetadata.keys'] = array_keys((array) $row['sourceMetadata']); }
        }
        echo json_encode(['collection' => $name, 'sampled' => $count, 'fields' => $fields], JSON_PRETTY_PRINT).PHP_EOL;
    }
}
