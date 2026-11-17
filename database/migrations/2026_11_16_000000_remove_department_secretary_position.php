<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();
        // Keep identities and ownership references; do not promote obsolete positions to heads.
        $db->users->updateMany(['archiveResponsibility' => 'department_secretary'], ['$set' => [
            'archiveResponsibility' => 'faculty_staff',
            'archiveUnitIds' => [],
            'archiveCollegeIds' => [],
            'archiveCluster' => null,
        ]]);
        foreach ($db->users->find(['mockSeedKey' => ['$exists' => true], 'fullName' => ['$regex' => '^\\[MOCK\\].* Department Secretary$']]) as $user) {
            $db->users->updateOne(['_id' => $user['_id']], ['$set' => [
                'fullName' => preg_replace('/Department Secretary$/', 'Faculty Staff', $user['fullName']),
            ]]);
        }
        $db->archive_documents->updateMany([
            'mockSeedKey' => ['$exists' => true],
            'title' => '[MOCK] IT syllabus uploaded by Department Secretary',
        ], ['$set' => ['title' => '[MOCK] IT syllabus']]);
    }

    public function down(): void
    {
        // The removed institutional position must not be restored on rollback.
    }
};
