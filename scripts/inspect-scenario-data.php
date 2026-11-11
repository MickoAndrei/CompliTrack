<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
echo 'Database: '.$db->getDatabaseName().PHP_EOL;
foreach ($db->users->find([], ['projection' => ['fullName' => 1, 'email' => 1, 'roleId' => 1, 'departmentId' => 1, 'assignedDepartmentIds' => 1, 'archiveResponsibility' => 1, 'archiveCollegeIds' => 1, 'archiveUnitIds' => 1, 'isActive' => 1]]) as $user) { echo json_encode($user).PHP_EOL; }
echo 'Roles: '.json_encode($db->roles->find()->toArray()).PHP_EOL;
foreach (['audits', 'compliance_checks', 'notifications', 'archive_documents'] as $name) { echo $name.': '.$db->$name->countDocuments().PHP_EOL; }
