<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && (int) $user->roleId === 0 && ! $user->isActive) {
            $user->isActive = true;
            $user->save();
        }

        if (!$user || (!$user->isActive && (int) $user->roleId !== 0)) {
            $token = $user?->currentAccessToken();
            if ($token instanceof TransientToken) {
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            } else {
                $token?->delete();
            }

            return response()->json(['message' => 'This account is inactive.'], 401);
        }

        return $next($request);
    }
}
