<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isActive) {
            $user?->currentAccessToken()?->delete();

            return response()->json(['message' => 'This account is inactive.'], 401);
        }

        return $next($request);
    }
}