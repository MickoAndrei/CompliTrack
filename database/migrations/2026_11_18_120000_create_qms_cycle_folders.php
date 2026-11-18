<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void { app(\App\Services\QmsCycleRepository::class)->sync(); }
    public function down(): void { /* Folders may contain uploaded records and must be retained. */ }
};
