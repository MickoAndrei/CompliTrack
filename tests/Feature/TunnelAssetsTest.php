<?php

namespace Tests\Feature;

use App\Http\Middleware\UseTunnelAssets;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Tests\TestCase;

class TunnelAssetsTest extends TestCase
{
    public function test_forwarded_html_has_an_explicit_byte_length(): void
    {
        $html = '<!doctype html><html><body>Sign in — CompliTrack</body></html>';
        $response = (new UseTunnelAssets())->handle(
            Request::create('https://example.asse.devtunnels.ms/login'),
            fn () => response($html)->header('Content-Type', 'text/html; charset=utf-8'),
        );

        $this->assertSame((string) strlen($html), $response->headers->get('Content-Length'));
        $this->assertSame($html, $response->getContent());
    }

    public function test_streamed_responses_keep_their_streaming_headers(): void
    {
        $response = (new UseTunnelAssets())->handle(
            Request::create('https://example.asse.devtunnels.ms/export'),
            fn () => response()->stream(fn () => print('stream'), 200, ['Content-Type' => 'text/html']),
        );

        $this->assertFalse($response->headers->has('Content-Length'));
    }

    public function test_tunnel_ignores_hot_file_and_restores_local_hmr_after_the_request(): void
    {
        $vite = app(Vite::class);
        $original = $vite->hotFile();
        $hot = storage_path('framework/testing/vite-test-'.bin2hex(random_bytes(5)).'.hot');
        file_put_contents($hot, 'http://[::1]:5173');
        $vite->useHotFile($hot);
        try {
            $middleware = new UseTunnelAssets();
            $middleware->handle(Request::create('http://localhost:8000/login'), function () use ($vite) {
                $this->assertTrue($vite->isRunningHot());
                return response('local');
            });
            $middleware->handle(Request::create('https://example.ngrok-free.dev/login'), function () use ($vite) {
                $this->assertFalse($vite->isRunningHot());
                return response('public');
            });
            $middleware->handle(Request::create('https://w8whqn8k-8000.asse.devtunnels.ms/login'), function () use ($vite) {
                $this->assertFalse($vite->isRunningHot());
                return response('forwarded');
            });
            $middleware->handle(Request::create('https://example.devtunnels.ms.example.com/login'), function () use ($vite) {
                $this->assertTrue($vite->isRunningHot());
                return response('other host');
            });
            $this->assertSame($hot, $vite->hotFile());
            $this->assertTrue($vite->isRunningHot());
            try {
                $middleware->handle(Request::create('https://example.ngrok.app/login'), function () {
                    throw new \RuntimeException('render failed');
                });
            } catch (\RuntimeException $error) {
                $this->assertSame('render failed', $error->getMessage());
            }
            $this->assertSame($hot, $vite->hotFile());
        } finally {
            $vite->useHotFile($original);
            unlink($hot);
        }
    }
}
