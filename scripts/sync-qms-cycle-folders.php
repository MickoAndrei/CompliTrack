<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'Created '.app(App\Services\QmsCycleRepository::class)->sync()." QMS cycle folders. Existing records were retained.\n";
