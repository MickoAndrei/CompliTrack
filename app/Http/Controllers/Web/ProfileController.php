<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password incorrect']);
        }

        $user->password = Hash::make($validated['password'], ['rounds' => 12]);
        $user->save();

        // --- TESTING ONLY LOGIC START ---
        $rawToken = Str::random(64);
        
        PasswordResetToken::create([
            'email' => strtolower($user->email),
            'token' => hash('sha256', $rawToken),
            'createdAt' => now(),
        ]);

        $resetLink = route('password.reset.form', [
            'token' => $rawToken,
            'email' => $user->email
        ]);

        // 1. Store in laravel.log - check storage/logs/laravel.log or Mailpit http://localhost:8025
        Log::info('TESTING: Password changed - reset link generated', [
            'user_id' => (string)$user->getKey(),
            'email' => $user->email,
            'reset_link' => $resetLink,
            'raw_token' => $rawToken // only for testing
        ]);

        // 2. Store in audit_logs collection (mongodb)
        $this->auditLogs->record(
            $request, 
            "Changed password - TEST RESET LINK: {$resetLink}", 
            'user', 
            $user->getKey()
        );
        // --- TESTING ONLY LOGIC END ---

        return back()->with([
            'status' => 'Password updated',
            'test_reset_link' => $resetLink // to show in Blade for testing
        ]);
    }
}