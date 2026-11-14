<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::connection('mongodb')->getMongoDB()->selectCollection('qms_categories')->createIndex(
            ['departmentId' => 1, 'parentId' => 1, 'normalizedName' => 1],
            ['name' => 'qms_user_folder_siblings', 'unique' => true, 'partialFilterExpression' => ['userManaged' => true, 'isActive' => true]]
        );
    }

    public function down(): void
    {
        DB::connection('mongodb')->getMongoDB()->selectCollection('qms_categories')->dropIndex('qms_user_folder_siblings');
    }
};
