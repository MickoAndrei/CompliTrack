<?php
// Render production views with isolated browser data; this script never writes database records.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$user = new App\Models\User(['fullName' => 'Archive Browser Test', 'roleId' => 0, 'isActive' => true]);
$user->setAttribute('_id', new MongoDB\BSON\ObjectId('0123456789abcdef01234567'));
Illuminate\Support\Facades\Auth::setUser($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$directory = storage_path('framework/testing/qms-browser');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
$bundle = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/js/app.js']['file']));
$html = view('super-admin.archives')->render();
$html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
$html = preg_replace('/<link[^>]+(?:fonts\.googleapis|fonts\.gstatic)[^>]*>/', '', $html);
$html = str_replace('http://localhost', 'file:///'.str_replace('\\', '/', public_path()), $html);
$html = str_replace('</body>', '<script type="module" src="'.$bundle.'"></script></body>', $html);
file_put_contents($directory.'/explorer.html', $html);
echo "QMS browser fixture rendered without database writes.\n";
