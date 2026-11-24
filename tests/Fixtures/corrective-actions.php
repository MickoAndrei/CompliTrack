<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'app.url' => 'http://fixture.local']);
Illuminate\Support\Facades\URL::forceRootUrl('http://fixture.local');
app(Illuminate\Foundation\Vite::class)->useHotFile(storage_path('framework/testing/car-no-hot'));
view()->share('errors', new Illuminate\Support\ViewErrorBag);
$user = new App\Models\User(['fullName' => 'Assigned Auditor', 'roleId' => 2, 'isActive' => true]);
$user->setAttribute('_id', new MongoDB\BSON\ObjectId('0123456789abcdef01234567'));
Illuminate\Support\Facades\Auth::setUser($user);
$directory = storage_path('framework/testing/car-browser');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
file_put_contents($directory.'/index.html', view('auditor.corrective-actions')->render());
echo "Corrective action template rendered without database writes.\n";
