<?php

use App\Notifications\PasswordResetLink;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['mail.default' => 'log', 'mail.from.address' => 'sender@example.test', 'mail.mailers.password_reset' => [
    'transport' => 'phpmailer', 'host' => '127.0.0.1', 'port' => (int) $argv[1], 'encryption' => '', 'auth_type' => '',
]]);
Log::shouldReceive('debug')->never();
Log::shouldReceive('info')->never();
Notification::route('mail', 'recipient@example.test')
    ->notify(new PasswordResetLink('https://example.test/reset-password?token=isolated-smtp-fixture'));
echo 'SMTP delivery completed without mail-body logging.', PHP_EOL;
