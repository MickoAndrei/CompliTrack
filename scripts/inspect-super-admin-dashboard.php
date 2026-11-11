<?php
// Read-only verification against existing authorized records. Never seeds or updates data.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$actor = App\Models\User::where('roleId', 0)->where('isActive', true)->firstOrFail();
$summary = app(App\Services\SuperAdminDashboard::class)->summary($actor);
$health = app(App\Http\Controllers\Api\V1\SuperAdminDashboardController::class)->health()->getData(true);
echo json_encode(['dashboard' => $summary, 'health' => $health['data']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
