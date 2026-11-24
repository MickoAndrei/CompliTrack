<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // Successful domain writes are recorded by their existing services.
        // Capture rejected requests without recording credentials or request bodies.
        if (in_array($response->getStatusCode(), [401, 403, 429], true) && $request->route()) {
            $login = $request->is('api/v1/auth/login');
            $outcome = $response->getStatusCode() === 403 ? 'denied' : 'failed';
            try {
                app(AuditLogService::class)->record($request,
                    $login ? 'Sign in rejected' : ($outcome === 'denied' ? 'Access denied' : 'Authentication or rate limit rejected'),
                    $login ? 'authentication' : 'security', outcome: $outcome);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }
}
