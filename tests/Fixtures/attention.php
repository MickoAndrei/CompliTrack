<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use MongoDB\BSON\ObjectId;

// Render real Blade layouts without database access or persisted fixture records.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'app.url' => 'http://fixture.local']);
URL::forceRootUrl('http://fixture.local');
app(Vite::class)->useHotFile(storage_path('framework/testing/attention-no-hot'));
view()->share('errors', new ViewErrorBag);
$directory = storage_path('framework/testing/attention-browser');
File::ensureDirectoryExists($directory);
foreach ([0 => 'super-admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'] as $role => $portal) {
    $user = new User(['fullName' => 'Notification Reviewer', 'roleId' => $role, 'isActive' => true]);
    $user->setAttribute('_id', new ObjectId('0123456789abcdef01234567'));
    Auth::setUser($user);
    file_put_contents($directory.'/notifications-'.$role.'.html', view($portal.'.notifications')->render());
    if ($role === 0) {
        file_put_contents($directory.'/logs.html', view('super-admin.logs')->render());
    }
    if ($role <= 1) {
        foreach (['users', 'departments'] as $section) {
            file_put_contents($directory.'/directory-'.$section.'-'.$role.'.html', view($portal.'.'.$section)->render());
        }
    }
}
echo "Notification and activity templates rendered without database writes.\n";
