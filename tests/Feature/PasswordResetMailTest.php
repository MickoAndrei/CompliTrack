<?php

namespace Tests\Feature;

use App\Mail\MicrosoftSmtpOAuth;
use App\Mail\PhpMailerTransport;
use App\Notifications\PasswordResetLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class PasswordResetMailTest extends TestCase
{
    private function transport(PHPMailer $mailer): PhpMailerTransport
    {
        return new class($mailer) extends PhpMailerTransport
        {
            public function __construct(private PHPMailer $mailer)
            {
                parent::__construct(['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls']);
            }

            protected function createMailer(): PHPMailer
            {
                return $this->mailer;
            }
        };
    }

    public function test_reset_email_uses_phpmailer_even_when_default_mailer_is_log(): void
    {
        Log::spy();
        config(['mail.default' => 'log', 'mail.from.address' => 'sender@example.test']);
        $smtp = Mockery::mock(SMTP::class);
        $smtp->shouldReceive('mail')->once()->with('sender@example.test')->andReturnTrue();
        $smtp->shouldReceive('recipient')->once()->with('recipient@example.test')->andReturnTrue();
        $smtp->shouldReceive('data')->once()->withArgs(function ($mime) {
            $this->assertStringContainsString('CompliTrack password reset', $mime);
            $this->assertStringContainsString('fixture-reset-token', $mime);
            $this->assertStringContainsString('text/html', $mime);
            $this->assertStringContainsString('text/plain', $mime);

            return true;
        })->andReturnTrue();
        $mailer = Mockery::mock(PHPMailer::class, [true])->makePartial();
        $mailer->shouldReceive('smtpConnect')->once()->andReturnTrue();
        $mailer->shouldReceive('getSMTPInstance')->once()->andReturn($smtp);
        $mailer->shouldReceive('smtpClose')->once();
        Mail::extend('phpmailer', fn () => $this->transport($mailer));
        Notification::route('mail', 'recipient@example.test')->notify(new PasswordResetLink('https://example.test/reset-password?token=fixture-reset-token'));
        $this->assertSame(0, $mailer->SMTPDebug);
        $this->assertSame('tls', $mailer->SMTPSecure);
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('info');
    }

    public function test_smtp_failure_is_sanitized_and_does_not_fall_back_to_logging(): void
    {
        $mailer = Mockery::mock(PHPMailer::class, [true])->makePartial();
        $mailer->shouldReceive('smtpConnect')->once()->andThrow(new \RuntimeException('sensitive-smtp-dialogue'));
        $mailer->shouldReceive('smtpClose')->once();
        try {
            $this->transport($mailer)->send((new Email)->from('sender@example.test')->to('recipient@example.test')->text('private-reset-content'));
            $this->fail('Expected SMTP failure');
        } catch (TransportException $error) {
            $this->assertStringNotContainsString('sensitive', $error->getMessage());
            $this->assertStringNotContainsString('private-reset', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_microsoft_oauth_uses_exchange_scope_and_formats_smtp_authorization(): void
    {
        Http::preventStrayRequests();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'isolated-access-token'])]);
        $provider = new MicrosoftSmtpOAuth(['tenant_id' => 'tenant-fixture', 'client_id' => 'client-fixture',
            'client_secret' => 'secret-fixture', 'username' => 'sender@example.test']);
        $this->assertSame("user=sender@example.test\x01auth=Bearer isolated-access-token\x01\x01", base64_decode($provider->getOauth64()));
        Http::assertSent(fn ($r) => $r['grant_type'] === 'client_credentials' && $r['scope'] === 'https://outlook.office365.com/.default');
    }

    public function test_oauth_errors_do_not_expose_provider_response_or_credentials(): void
    {
        Http::preventStrayRequests();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error_description' => 'sensitive-provider-response'], 401)]);
        $provider = new MicrosoftSmtpOAuth(['tenant_id' => 'tenant-fixture', 'client_id' => 'client-fixture',
            'client_secret' => 'secret-fixture', 'username' => 'sender@example.test']);
        try {
            $provider->getOauth64();
            $this->fail('Expected authorization failure');
        } catch (\RuntimeException $error) {
            $this->assertStringNotContainsString('sensitive-provider', $error->getMessage());
            $this->assertStringNotContainsString('secret-fixture', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_mail_check_reports_missing_fields_without_showing_secrets(): void
    {
        config(['mail.mailers.password_reset.username' => null, 'mail.mailers.password_reset.oauth.client_secret' => 'secret-fixture']);
        $this->artisan('mail:check')->expectsOutputToContain('MAIL_USERNAME')->doesntExpectOutputToContain('secret-fixture')->assertFailed();
    }

    public function test_microsoft_delegated_oauth_can_exchange_a_refresh_token(): void
    {
        Http::preventStrayRequests();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'isolated-access-token'])]);
        $provider = new MicrosoftSmtpOAuth(['tenant_id' => 'common', 'client_id' => 'client-fixture', 'client_secret' => 'secret-fixture',
            'username' => 'sender@example.test', 'refresh_token' => 'refresh-fixture']);
        $provider->getOauth64();
        Http::assertSent(fn ($r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'refresh-fixture');
    }

    public function test_gmail_login_failure_has_a_safe_actionable_message(): void
    {
        $mailer = Mockery::mock(PHPMailer::class, [true])->makePartial();
        $mailer->shouldReceive('smtpConnect')->once()->andThrow(new \RuntimeException('Could not authenticate. sensitive-provider-response'));
        $mailer->shouldReceive('smtpClose')->once();
        $transport = new class($mailer) extends PhpMailerTransport
        {
            public function __construct(private PHPMailer $mailer)
            {
                parent::__construct(['host' => 'smtp.gmail.com', 'encryption' => 'tls', 'password' => 'abcd efgh ijkl mnop']);
            }

            protected function createMailer(): PHPMailer
            {
                return $this->mailer;
            }
        };
        try {
            $transport->send((new Email)->from('sender@example.test')->to('recipient@example.test')->text('private-reset-content'));
            $this->fail('Expected Gmail authentication failure');
        } catch (TransportException $error) {
            $this->assertStringContainsString('Google App Password', $error->getMessage());
            $this->assertStringNotContainsString('sensitive-provider-response', $error->getMessage());
            $this->assertNull($error->getPrevious());
            $this->assertSame('abcdefghijklmnop', $mailer->Password);
        }
    }

    public function test_mail_check_identifies_an_incorrect_gmail_app_password_format(): void
    {
        config(['mail.mailers.password_reset.host' => 'smtp.gmail.com', 'mail.mailers.password_reset.auth_type' => 'LOGIN',
            'mail.mailers.password_reset.username' => 'sender@example.test', 'mail.mailers.password_reset.password' => 'short-fixture',
            'mail.from.address' => 'sender@example.test']);
        $this->artisan('mail:check')->expectsOutputToContain('Google App Password')->doesntExpectOutputToContain('short-fixture')->assertFailed();
    }
}
