<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use PHPMailer\PHPMailer\OAuthTokenProvider;
use RuntimeException;
use Throwable;

class MicrosoftSmtpOAuth implements OAuthTokenProvider
{
    public function __construct(private readonly array $config) {}

    public function getOauth64(): string
    {
        $tenant = (string) ($this->config['tenant_id'] ?? '');
        if (! preg_match('/^[a-zA-Z0-9.-]+$/', $tenant) || empty($this->config['client_id']) || empty($this->config['client_secret']) || empty($this->config['username'])) {
            throw new RuntimeException('Microsoft SMTP OAuth configuration is incomplete.');
        }
        $payload = ['client_id' => $this->config['client_id'], 'client_secret' => $this->config['client_secret']];
        if (! empty($this->config['refresh_token'])) {
            $payload += ['grant_type' => 'refresh_token', 'refresh_token' => $this->config['refresh_token']];
        } else {
            $payload += ['grant_type' => 'client_credentials', 'scope' => 'https://outlook.office365.com/.default'];
        }
        try {
            // Never include response bodies, access tokens, or client secrets in exceptions/logs.
            $response = Http::asForm()->timeout(20)->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", $payload);
            $token = $response->successful() ? $response->json('access_token') : null;
        } catch (Throwable) {
            throw new RuntimeException('Microsoft SMTP authorization is unavailable.');
        }
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Microsoft SMTP authorization failed. Check the app permissions and mailbox configuration.');
        }

        return base64_encode("user={$this->config['username']}\x01auth=Bearer {$token}\x01\x01");
    }
}
