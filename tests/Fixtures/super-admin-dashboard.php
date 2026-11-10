<?php
// Browser fixtures only: render production templates without database writes.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$user = new App\Models\User(['fullName' => 'Super Admin browser test', 'roleId' => 0, 'isActive' => true]);
$user->setAttribute('_id', new MongoDB\BSON\ObjectId('0123456789abcdef01234567'));
Illuminate\Support\Facades\Auth::setUser($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$directory = storage_path('framework/testing/super-admin-browser');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
$bundle = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/js/app.js']['file']));
foreach (['dashboard' => 'super-admin.dashboard', 'departments' => 'super-admin.departments', 'archives' => 'super-admin.archives', 'logs' => 'super-admin.logs', 'profile' => 'auth.profile', 'users' => 'super-admin.users'] as $view => $template) {
    $html = view($template)->render();
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
    $html = preg_replace('/<link[^>]+(?:fonts\.googleapis|fonts\.gstatic)[^>]*>/', '', $html);
    $html = str_replace('http://localhost', 'file:///'.str_replace('\\', '/', public_path()), $html);
    $html = str_replace('</body>', '<script type="module" src="'.$bundle.'"></script></body>', $html);
    file_put_contents($directory.'/'.$view.'.html', $html);
}
file_put_contents($directory.'/departments.json', json_encode(config('qms.departments')));
echo "Super Admin browser fixtures rendered without database writes.\n";
