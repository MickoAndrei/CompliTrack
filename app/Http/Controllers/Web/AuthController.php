<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogs,
        private readonly PasswordResetService $passwordResets,
    ) {}

    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->passwordResets->send($user);
            $this->auditLogs->record($request, 'Requested password reset code', 'user', $user->getKey());
        }

        return redirect()->route('password.request')
            ->with('status', 'If an active account exists for that email, a password reset link has been sent.');
    }

    public function showResetForm(Request $request)
    {
        $request->validate([
            'email' => ['sometimes', 'email', 'max:255'],
            'token' => ['sometimes', 'string', 'size:64'],
        ]);

        return view('auth.reset-password', [
            'email' => $request->query('email'),
            'token' => $request->query('token'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));

        $reset = PasswordResetToken::where('email', $email)
            ->where('token', hash('sha256', $validated['token']))
            ->where('createdAt', '>=', now()->subMinutes(10))
            ->first();

        if (! $reset) {
            return back()->withErrors(['token' => 'This reset link is invalid or has expired. Request a new one.'])->withInput();
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $reset->delete();
            return back()->withErrors(['email' => 'Account not found.'])->withInput();
        }

        $user->password = Hash::make($validated['password'], ['rounds' => 12]);
        $user->isActive = true;
        $user->save();

        $reset->delete();
        $this->auditLogs->record($request, 'Reset password via web', 'user', $user->getKey());

        return redirect()->route('login')->with('status', 'Password set! Login now.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
