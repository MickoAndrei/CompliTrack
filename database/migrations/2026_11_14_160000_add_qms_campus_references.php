<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void { app(\App\Services\QmsCampusReferences::class)->migrate(); }
    public function down(): void { /* Persistent organizational references are retained on rollback. */ }
};
