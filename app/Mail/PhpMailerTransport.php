<?php

namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Throwable;

class PhpMailerTransport extends AbstractTransport
{
    public function __construct(private readonly array $config)
    {
        parent::__construct();
    }

    protected function createMailer(): PHPMailer
    {
        return new PHPMailer(true);
    }

    protected function doSend(SentMessage $message): void
    {
        $mailer = $this->createMailer();
        $mailer->isSMTP();
        $mailer->Host = (string) ($this->config['host'] ?? '');
        $mailer->Port = (int) ($this->config['port'] ?? 587);
        $mailer->Timeout = 20;
        $mailer->SMTPDebug = SMTP::DEBUG_OFF;
        $mailer->Debugoutput = static function (): void {};
        $mailer->Username = (string) ($this->config['username'] ?? '');
        $mailer->Password = (string) ($this->config['password'] ?? '');
        if ($mailer->Host === 'smtp.gmail.com') {
            // Google displays App Passwords in groups; spaces are presentation only.
            $mailer->Password = preg_replace('/\s+/', '', $mailer->Password);
        }
        $mailer->SMTPAuth = $mailer->Username !== '';
        $encryption = strtolower((string) ($this->config['encryption'] ?? 'tls'));
        $mailer->SMTPSecure = match ($encryption) {
            'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS, 'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS, default => ''
        };
        if ($mailer->Host === '' || ($mailer->SMTPSecure === '' && ! in_array($mailer->Host, ['localhost', '127.0.0.1', '::1'], true))) {
            throw new TransportException('Configure a valid SMTP host and TLS encryption for email delivery.');
        }
        $mailer->SMTPAutoTLS = $mailer->SMTPSecure !== '';
        if (strtoupper((string) ($this->config['auth_type'] ?? '')) === 'XOAUTH2') {
            $mailer->SMTPAuth = true;
            $mailer->AuthType = 'XOAUTH2';
            $mailer->setOAuth(new MicrosoftSmtpOAuth(($this->config['oauth'] ?? []) + ['username' => $mailer->Username]));
        }
        try {
            if (! $mailer->smtpConnect()) {
                throw new TransportException('SMTP connection failed.');
            }
            $smtp = $mailer->getSMTPInstance();
            $envelope = $message->getEnvelope();
            if (! $smtp->mail($envelope->getSender()->getAddress())) {
                throw new TransportException('SMTP sender rejected.');
            }
            foreach ($envelope->getRecipients() as $recipient) {
                if (! $smtp->recipient($recipient->getAddress())) {
                    throw new TransportException('SMTP recipient rejected.');
                }
            }
            // Laravel builds the complete MIME message; PHPMailer handles SMTP/TLS/OAuth delivery.
            if (! $smtp->data($message->toString())) {
                throw new TransportException('SMTP message rejected.');
            }
        } catch (Throwable $error) {
            // No raw SMTP dialogue, message content, credentials, or nested exceptions in logs.
            if ($mailer->Host === 'smtp.gmail.com' && preg_match('/authenticat/i', $mailer->ErrorInfo.' '.$error->getMessage())) {
                throw new TransportException('Gmail authentication failed. Set MAIL_PASSWORD to a Google App Password for MAIL_USERNAME, then clear the configuration cache.');
            }
            throw new TransportException('Email delivery failed. Check the SMTP configuration and mailbox permissions.');
        } finally {
            $mailer->smtpClose();
        }
    }

    public function __toString(): string
    {
        return 'phpmailer';
    }
}
