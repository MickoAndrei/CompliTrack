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
        if (!preg_match('/\.(ngrok-free\.(dev|app)|ngrok\.app)$/i', $request->getHost())) {
            return $next($request);
        }
        $vite = app(Vite::class);
        $original = $vite->hotFile();
        $vite->useHotFile(storage_path('framework/tunnel-build-only.hot'));
        try {
            return $next($request);
        } finally {
            $vite->useHotFile($original);
        }
    }
}
