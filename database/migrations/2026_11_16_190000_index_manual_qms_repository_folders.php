<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $folders = DB::connection('mongodb')->getMongoDB()->selectCollection('qms_categories');
        $folders->createIndex(['collegeId' => 1, 'parentId' => 1, 'normalizedName' => 1], [
            'name' => 'qms_repository_siblings', 'unique' => true,
            'partialFilterExpression' => ['folderPolicy' => ['$exists' => true], 'isActive' => true],
        ]);
        $folders->createIndex(['auditAssignmentId' => 1, 'auditorId' => 1, 'isActive' => 1], ['name' => 'qms_repository_assignment']);
    }

    public function down(): void
    {
        $folders = DB::connection('mongodb')->getMongoDB()->selectCollection('qms_categories');
        $folders->dropIndex('qms_repository_siblings');
        $folders->dropIndex('qms_repository_assignment');
    }
};
