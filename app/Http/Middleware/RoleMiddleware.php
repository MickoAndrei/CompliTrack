<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    // Usage: Route::middleware('role:0,1') -> only roleId 0 or 1 may pass
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user || !in_array((string) $user->roleId, $roles)) {
            abort(403, 'You do not have permission (roleId mismatch).');
        }

        return $next($request);
    }
}
