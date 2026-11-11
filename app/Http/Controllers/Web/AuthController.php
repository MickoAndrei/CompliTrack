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
        // Mail logs include HTML hrefs; copying one literally can produce amp;token.
        // Normalize only the two expected field names, never decode arbitrary input.
        $parameters = [
            'email' => $request->query('email', $request->query('amp;email')),
            'token' => $request->query('token', $request->query('amp;token')),
        ];
        $validator = validator($parameters, [
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('password.request')->with('status',
                'Open the complete password reset link from your email, or request a new link below.');
        }

        return view('auth.reset-password', [
            'email' => $parameters['email'],
            'token' => $parameters['token'],
        ]);
    }

    public function resetPassword(Request $request)
    {
        if (! $request->filled('token')) {
            return redirect()->route('password.request')->with('status',
                'Your password reset link is missing. Request a new link below, then open it from your email.');
        }
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], ['token.size' => 'This reset link is invalid. Request a new password reset link.']);

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
        $this->passwordResets->complete($reset->token);
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
