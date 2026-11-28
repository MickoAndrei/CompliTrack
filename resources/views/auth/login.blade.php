@extends('layouts.app')
@section('title', 'Sign in')
@push('styles')<link rel="stylesheet" href="{{ asset('css/v2/auth.css') }}">@endpush
@section('content')
<div class="ct-login">
    <aside class="ct-login-intro"><span class="ct-login-brand"><img src="{{ asset('images/ustp-claveria-logo.jpg') }}" alt="USTP Claveria" width="38" height="33"><span>Compli<b>Track</b></span></span><div class="ct-login-story"><h1>Regulatory Compliance &amp; Accountability, in one place.</h1><p>CompliTrack keeps every department’s evidence, checks, and corrective actions auditable and up to date.</p><ul><li>Encrypted archive of compliance evidence</li><li>Track compliance checks per department</li><li>Real-time corrective action notifications</li></ul></div><small>CLAVERIA CAMPUS</small></aside>
    <main class="ct-login-main">
        <section class="ct-login-form" x-data="loginPage()">
            <template x-if="error"><div x-page-notice class="status-toast" role="alert" x-text="error"></div></template>
            @if (request()->query('reason') === 'server-restarted') <div x-page-notice class="status-toast" role="status">The server restarted. Please sign in again.</div> @endif
            @if (request()->query('reason') === 'database-switched') <div x-page-notice="'success'" class="status-toast" role="status">Database switched successfully. Sign in to continue.</div> @endif
            @if (request()->query('reason') === 'database-switch-rolled-back') <div x-page-notice class="status-toast" role="status">The database switch could not complete. The previous database was restored. Please sign in again.</div> @endif
            @if (request()->query('reset') === 'success') <div x-page-notice="'success'" class="status-toast" role="status">Password reset successfully. Sign in with your new password.</div> @endif
            <span class="ct-login-brand"><img src="{{ asset('images/ustp-claveria-logo.jpg') }}" alt="USTP Claveria" width="38" height="33"><span>Compli<b>Track</b></span></span><div class="form-header"><h2 class="form-title">USTP Claveria</h2><p class="form-subtitle">CompliTrack</p></div>
            @if ($errors->any()) <div x-page-notice class="status-toast" role="alert">{{ $errors->first() }}</div> @endif
            <form class="auth-form" @submit.prevent="submit()">
                <div class="form-group"><label for="loginEmail" class="form-label">Email</label><input x-model="email" type="email" id="loginEmail" class="form-input" placeholder="name@ustp.edu.ph" autocomplete="username" required autofocus></div>
                <div class="form-group"><label for="loginPassword" class="form-label">Password</label><div class="password-input-wrapper"><input x-model="password" type="password" id="loginPassword" class="form-input password-input" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle-btn" aria-label="Toggle password visibility" @click="$el.previousElementSibling.type = $el.previousElementSibling.type === 'password' ? 'text' : 'password'"><i data-lucide="eye" class="eye-icon eye-show"></i></button></div></div>
                <div class="form-row-utility"><label class="checkbox-container"><input x-model="remember" type="checkbox"><span class="checkbox-label">Remember Me</span></label><a href="{{ route('password.request') }}" class="link-forgot">Forgot password?</a></div>
                <button :disabled="loading" type="submit" class="btn btn-primary"><span class="btn-text" x-text="loading ? 'Signing in…' : 'Log In'"></span></button>
            </form>
            <div class="form-disclaimer"><p>Accounts are provisioned by your Department Admin. Contact your Super Admin if you need access.</p></div>
        </section>
    </main>
</div>
@endsection
