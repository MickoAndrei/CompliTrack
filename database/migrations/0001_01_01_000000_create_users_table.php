<?php

use Illuminate\Database\Migrations\Migration;

// Users now live in MongoDB. Relational framework support tables are managed in 0001_01_01_000000_create_framework_support_tables.php.
return new class extends Migration
{
    public function up(): void {}
    public function down(): void {}
};
