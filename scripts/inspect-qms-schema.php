<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
echo 'Database: '.$db->getDatabaseName().PHP_EOL;
echo 'Collections: '.implode(', ', iterator_to_array($db->listCollectionNames())).PHP_EOL;
foreach ($db->departments->find([], ['projection'=>['departmentName'=>1,'departmentCode'=>1,'collegeName'=>1,'campusName'=>1,'unitType'=>1,'collegeId'=>1,'isActive'=>1]]) as $unit) {
    $id=$unit['_id']; $refs=[];
    foreach (['users','audits','compliance_checks','archive_documents'] as $name) { $refs[$name]=$db->$name->countDocuments(['departmentId'=>['$in'=>[$id,(string)$id]]]); }
    echo json_encode(['unit'=>$unit,'references'=>$refs], JSON_UNESCAPED_SLASHES).PHP_EOL;
}
echo 'User organizational fields: '.json_encode($db->users->aggregate([['$project'=>['roleId'=>1,'responsibilities'=>1,'organizationalResponsibilities'=>1,'collegeId'=>1,'assignedCollegeIds'=>1]],['$limit'=>30]])->toArray()).PHP_EOL;
echo 'Archive field names: '.json_encode($db->archive_documents->aggregate([['$project'=>['keys'=>['$map'=>['input'=>['$objectToArray'=>'$$ROOT'],'as'=>'field','in'=>'$$field.k']]]],['$limit'=>3]])->toArray()).PHP_EOL;
echo 'Audit evidence shape: '.json_encode($db->audits->aggregate([['$project'=>['status'=>1,'requestCount'=>['$size'=>['$ifNull'=>['$evidenceRequests',[]]]],'itemCount'=>['$size'=>['$ifNull'=>['$items',[]]]]]],['$limit'=>5]])->toArray()).PHP_EOL;
