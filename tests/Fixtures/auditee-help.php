<?php
// Production view and built assets, without database writes or session credentials.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
app(\Illuminate\Foundation\Vite::class)->useHotFile(storage_path('framework/testing/help-no-hot'));
$user = new App\Models\User(['fullName' => 'Help Browser Test', 'roleId' => 3, 'isActive' => true]);
$user->setAttribute('_id', new MongoDB\BSON\ObjectId());
Illuminate\Support\Facades\Auth::setUser($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$directory = storage_path('framework/testing/help-browser');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
$publicUrl = 'file:///'.str_replace('\\', '/', public_path());
$html = view('auditee.help')->render();
$html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
$html = str_replace([rtrim(asset('/'), '/'), 'http://localhost'], $publicUrl, $html);
$html = str_replace('</body>', '<script type="module" src="'.$publicUrl.'/build/'.$manifest['resources/js/app.js']['file'].'"></script></body>', $html);
file_put_contents($directory.'/help.html', $html);
foreach ([0 => 'super-admin', 1 => 'admin', 2 => 'auditor'] as $role => $portal) {
    $user->roleId = $role;
    Illuminate\Support\Facades\Auth::setUser($user);
    $html = view('help.index')->render();
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
    $html = str_replace([rtrim(asset('/'), '/'), 'http://localhost'], $publicUrl, $html);
    $html = str_replace('</body>', '<script type="module" src="'.$publicUrl.'/build/'.$manifest['resources/js/app.js']['file'].'"></script></body>', $html);
    file_put_contents($directory.'/'.$portal.'.html', $html);
}
echo "Help browser fixture rendered without database writes.\n";
