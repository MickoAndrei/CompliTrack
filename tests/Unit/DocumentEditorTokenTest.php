<?php

namespace Tests\Unit;

use App\Services\DocumentEditorToken;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DocumentEditorTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['documents.secret' => str_repeat('s', 48), 'documents.url' => 'https://docs.example.test', 'documents.internal_url' => 'http://127.0.0.1:8082']);
    }

    public function test_configuration_round_trip_and_tampering_rejection(): void
    {
        $tokens = app(DocumentEditorToken::class);
        $token = $tokens->sign(['document' => ['key' => 'revision-key'], 'editorConfig' => ['mode' => 'edit']]);
        $this->assertSame('revision-key', $tokens->verify($token)['document']['key']);
        config(['documents.secret' => str_repeat('x', 48)]);
        $this->expectException(HttpException::class);
        $tokens->verify($token);
    }

    public function test_download_url_maps_only_the_configured_server(): void
    {
        $tokens = app(DocumentEditorToken::class);
        $this->assertSame('http://127.0.0.1:8082/cache/files/file.docx?token=abc', $tokens->internalDownloadUrl('https://docs.example.test/cache/files/file.docx?token=abc'));
        $this->expectException(HttpException::class);
        $tokens->internalDownloadUrl('https://docs.example.test.attacker.test/file.docx');
    }

    public function test_download_url_rejects_credentials_and_redirect_targets(): void
    {
        $this->expectException(HttpException::class);
        app(DocumentEditorToken::class)->internalDownloadUrl('http://user@127.0.0.1:8082/cache/file.pdf');
    }
}
