<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckResetMail extends Command
{
    protected $signature = 'mail:check';

    protected $description = 'Check password-reset SMTP configuration without sending mail or printing credentials';

    public function handle(): int
    {
        $config = config('mail.mailers.password_reset', []);
        $missing = [];
        if (empty($config['host']) || in_array($config['host'], ['127.0.0.1', 'localhost'], true)) {
            $missing[] = 'MAIL_HOST (Microsoft 365: smtp.office365.com)';
        }
        if (empty($config['username'])) {
            $missing[] = 'MAIL_USERNAME (sending mailbox)';
        }
        $from = config('mail.from.address');
        if (! $from || str_ends_with($from, '@example.com') || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'MAIL_FROM_ADDRESS (authorized sender)';
        }
        if (strtoupper($config['auth_type'] ?? '') === 'XOAUTH2') {
            foreach (['tenant_id', 'client_id', 'client_secret'] as $key) {
                if (empty($config['oauth'][$key])) {
                    $missing[] = 'MAIL_OAUTH_'.strtoupper($key);
                }
            }
        } elseif (empty($config['password'])) {
            $missing[] = 'MAIL_PASSWORD';
        }
        if (($config['host'] ?? '') === 'smtp.gmail.com' && strtoupper($config['auth_type'] ?? '') !== 'XOAUTH2'
            && ! empty($config['password']) && strlen(preg_replace('/\s+/', '', $config['password'])) !== 16) {
            $missing[] = 'MAIL_PASSWORD (16-character Google App Password, not your Gmail sign-in password)';
        }
        if (! in_array($config['encryption'] ?? '', ['tls', 'ssl', 'starttls', 'smtps'], true)) {
            $missing[] = 'MAIL_ENCRYPTION (tls for Microsoft 365)';
        }
        if ($missing) {
            $this->error('Password-reset email configuration is incomplete. No email was sent.');
            foreach ($missing as $field) {
                $this->line('Set '.$field);
            }

            return self::FAILURE;
        }
        $this->info('Password-reset email configuration is present. PHPMailer is selected; mail-body logging is bypassed.');
        $this->line('This checks configuration only. Provider authentication, network access, and actual delivery still require verification.');

        return self::SUCCESS;
    }
}
