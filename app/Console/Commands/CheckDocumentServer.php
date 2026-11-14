<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckDocumentServer extends Command
{
    protected $signature = 'documents:check {--local : Check the loopback browser editor endpoint}';
    protected $description = 'Check the existing ONLYOFFICE endpoints without exposing JWT secrets';

    public function handle(): int
    {
        $this->line('CompliTrack: '.config('app.url'));
        $this->line('Browser editor: '.config('documents.url'));
        $this->line('Local browser editor: '.config('documents.local_url'));
        $this->line('Laravel → ONLYOFFICE: '.config('documents.internal_url'));
        $this->line('ONLYOFFICE → Laravel: '.config('documents.app_url'));
        $configured = strlen((string) config('documents.secret')) >= 32;
        $this->line('JWT signing secret configured: '.($configured ? 'yes' : 'no'));
        $ok = $configured;
        $browserUrl = $this->option('local') ? config('documents.local_url') : config('documents.url');
        foreach (['Internal health' => config('documents.internal_url').'/healthcheck', 'Browser API' => $browserUrl.'/web-apps/apps/api/documents/api.js'] as $label => $url) {
            try {
                $response = Http::timeout(15)->withOptions(['allow_redirects' => false])->get($url);
                $valid = $response->successful() && ($label !== 'Internal health' || trim($response->body()) === 'true');
                $this->line($label.': '.($valid ? 'OK' : 'FAILED').' (HTTP '.$response->status().')');
                $ok = $ok && $valid;
            } catch (\Throwable $exception) {
                $reason = str_contains($exception->getMessage(), 'cURL error 6') ? 'DNS resolution failed for the configured hostname.' : 'Unreachable; verify the configured URL, TLS and reverse proxy.';
                $this->error($label.': '.$reason); $ok = false;
            }
        }
        $this->line('Container-to-Laravel reachability and actual editing must be checked from the existing container/browser.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
