<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

class UseTunnelAssets
{
    public function handle(Request $request, Closure $next)
    {
        // Vite's client-local URLs cannot be used by people visiting a public tunnel.
        // Leave the local hot file in place so ordinary development retains HMR.
        if (!preg_match('/\.(ngrok-free\.(dev|app)|ngrok\.app|devtunnels\.ms)$/i', $request->getHost())) {
            return $next($request);
        }
        $vite = app(Vite::class);
        $original = $vite->hotFile();
        $vite->useHotFile(storage_path('framework/tunnel-build-only.hot'));
        try {
            $response = $next($request);
            $content = $response->getContent();
            // Dev tunnels may leave EOF-delimited PHP HTML responses open after
            // forwarding the body. Give buffered HTML an explicit byte length.
            if (!$request->isMethod('HEAD') && is_string($content)
                && str_starts_with($response->headers->get('Content-Type', ''), 'text/html')) {
                $response->headers->set('Content-Length', (string) strlen($content));
            }

            return $response;
        } finally {
            $vite->useHotFile($original);
        }
    }
}
