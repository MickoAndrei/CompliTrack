@extends('layouts.app')
@section('title', 'Set a new password')
@section('content')
<div class="auth-container">
    @include('auth.brand-panel')
    <main class="main-panel">
        <section class="form-wrapper reset-form-wrapper">
            <a href="{{ route('login') }}" class="back-link-btn"><span class="back-arrow">&larr;</span> Back to log in</a>
            <div class="form-header">
                <h1 class="form-title">Set a new password</h1>
                <p class="form-instructions">Choose a strong password you have not used before. Your reset link is valid for 10 minutes.</p>
            </div>

            @if (session('status'))
                <div class="reset-status" role="status" data-auto-dismiss>{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="status-toast" role="alert" data-auto-dismiss>{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="auth-form reset-auth-form" id="resetPasswordForm" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ old('token', $token ?? '') }}">
                <div class="form-group">
                    <label for="resetEmail" class="form-label">Institutional email</label>
                    <input id="resetEmail" type="email" name="email" value="{{ old('email', $email ?? '') }}" class="form-input" autocomplete="email" required>
                </div>
                <div class="form-group">
                    <label for="newPassword" class="form-label">New password</label>
                    <div class="password-input-wrapper">
                        <input id="newPassword" type="password" name="password" class="form-input password-input" autocomplete="new-password" minlength="12" required aria-describedby="passwordHint passwordMismatch">
                        <button type="button" class="password-toggle-btn" aria-label="Show new password" aria-pressed="false" data-password-toggle="newPassword"><i data-lucide="eye" class="eye-icon"></i></button>
                    </div>
                    <p class="reset-field-hint">Use at least 12 characters.</p>
                    <p class="reset-field-error" id="passwordMismatch" role="alert" hidden>Passwords do not match. Please make sure both fields are the same.</p>
                </div>
                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Confirm new password</label>
                    <div class="password-input-wrapper">
                        <input id="confirmPassword" type="password" name="password_confirmation" class="form-input password-input" autocomplete="new-password" minlength="12" required aria-describedby="passwordMismatch">
                        <button type="button" class="password-toggle-btn" aria-label="Show password confirmation" aria-pressed="false" data-password-toggle="confirmPassword"><i data-lucide="eye" class="eye-icon"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary reset-submit-btn"><span>Update password</span><span class="btn-arrow">&rarr;</span></button>
            </form>
            <div class="form-footer-action"><span>Remembered your password?</span> <a href="{{ route('login') }}" class="link-bold">Log in</a></div>
        </section>
    </main>
</div>
<style>
    .reset-form-wrapper { max-width: 500px; }
    .reset-auth-form { gap: 22px; }
    .reset-auth-form .form-group { gap: 8px; }
    .reset-field-hint { color: #64748b; font-size: 12px; margin-top: 2px; }
    .reset-field-error { color: #b91c1c; font-size: 12px; margin-top: 2px; }
    .reset-submit-btn { width: 100%; justify-content: center; gap: 10px; margin-top: 4px; }
    .reset-status { padding: 12px 14px; margin-bottom: 18px; border: 1px solid #bbf7d0; border-radius: 10px; background: #f0fdf4; color: #166534; font-size: 14px; }
    [data-auto-dismiss] { transition: opacity .3s ease, transform .3s ease, margin .3s ease, padding .3s ease; }
    [data-auto-dismiss].is-dismissing { opacity: 0; transform: translateY(-6px); margin-top: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 0; overflow: hidden; }
    @media (max-width: 700px) {
        html, body { height: auto; min-height: 100%; overflow-y: auto; }
        .auth-container { min-height: 100dvh; height: auto; overflow: visible; }
        .reset-form-wrapper { padding-top: 38px; padding-bottom: 38px; }
    }
</style>
<script>
    const resetForm = document.getElementById('resetPasswordForm');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const mismatchMessage = document.getElementById('passwordMismatch');
    const updatePasswordMatch = () => {
        const mismatch = confirmPassword.value.length > 0 && newPassword.value !== confirmPassword.value;
        mismatchMessage.hidden = !mismatch;
        confirmPassword.setCustomValidity(mismatch ? 'Passwords do not match.' : '');
        confirmPassword.setAttribute('aria-invalid', String(mismatch));
    };
    newPassword.addEventListener('input', updatePasswordMatch);
    confirmPassword.addEventListener('input', updatePasswordMatch);
    resetForm.addEventListener('submit', (event) => {
        updatePasswordMatch();
        if (!resetForm.checkValidity()) {
            event.preventDefault();
            resetForm.reportValidity();
        }
    });
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            button.innerHTML = `<i data-lucide="${visible ? 'eye-off' : 'eye'}" class="eye-icon"></i>`;
            window.renderIcons?.();
        });
    });
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-auto-dismiss]').forEach((notification) => {
            window.setTimeout(() => {
                notification.classList.add('is-dismissing');
                window.setTimeout(() => notification.remove(), 350);
            }, 5000);
        });
    });
</script>
@endsection
