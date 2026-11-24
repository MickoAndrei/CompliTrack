<?php
// Production Blade, isolated read-only browser responses. No application records are created.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$actor = new App\Models\User(['fullName' => 'Walkthrough browser fixture', 'roleId' => 1, 'isActive' => true]);
$actor->setAttribute('_id', new MongoDB\BSON\ObjectId('0123456789abcdef01234567'));
Illuminate\Support\Facades\Auth::setUser($actor);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$dir = storage_path('framework/testing/walkthrough-browser');
Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
foreach (['reports' => 'admin.reports', 'plans' => 'audit-plans.index'] as $name => $view) {
    $html = view($view)->render();
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
    $html = preg_replace('/<link[^>]+(?:fonts\.googleapis|fonts\.gstatic)[^>]*>/', '', $html);
    $html = preg_replace('~((?:href|src)=")https?://[^/"]+~', '$1', $html);
    file_put_contents($dir.'/'.$name.'.html', $html);
}
echo "Walkthrough views rendered with no database writes.\n";
