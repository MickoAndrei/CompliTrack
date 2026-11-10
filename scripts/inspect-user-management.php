<?php
// Read-only schema inspection. Never prints account identities, hashes, or tokens.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
$report = ['database' => $db->getDatabaseName(), 'collections' => iterator_to_array($db->listCollectionNames()),
    'userCount' => $db->users->countDocuments(),
    'roles' => $db->roles->find([], ['projection' => ['_id' => 0, 'roleId' => 1, 'roleName' => 1, 'permissions' => 1]])->toArray(),
    'usersByRole' => $db->users->aggregate([['$group' => ['_id' => '$roleId', 'count' => ['$sum' => 1]]]])->toArray(),
    'usersByResponsibility' => $db->users->aggregate([['$group' => ['_id' => '$archiveResponsibility', 'count' => ['$sum' => 1]]]])->toArray(),
    'userIndexes' => array_map(fn ($index) => ['name' => $index->getName(), 'key' => $index->getKey()], iterator_to_array($db->users->listIndexes())),
    'units' => $db->departments->find([], ['projection' => ['_id' => 0, 'departmentCode' => 1, 'departmentName' => 1, 'unitType' => 1, 'ownershipVerified' => 1, 'isActive' => 1]])->toArray()];
echo json_encode($report, JSON_PRETTY_PRINT), PHP_EOL;
