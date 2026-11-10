<?php

namespace App\Http\Middleware;

use App\Services\SystemOperationLock;
use App\Services\SystemSettingsService;
use Closure;
use Illuminate\Http\Request;

class SystemAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $request->is('api/v1/auth/login', 'api/v1/auth/logout', 'logout')
            && $user && (int) $user->roleId !== 0
            && (app(SystemSettingsService::class)->read()['maintenanceEnabled'] ?? false)) {
            abort(503, 'CompliTrack is undergoing maintenance. Please try again later.');
        }

        // Settings operations acquire their own exclusive lock after authorization.
        if ($request->is('api/v1/settings', 'api/v1/settings/*')) {
            return $next($request);
        }

        return app(SystemOperationLock::class)->run(fn () => $next($request), false);
    }
}
