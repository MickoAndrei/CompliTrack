<?php

namespace Tests\Feature;

use App\Http\Middleware\UseTunnelAssets;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Tests\TestCase;

class TunnelAssetsTest extends TestCase
{
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
