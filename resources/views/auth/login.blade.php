@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="auth-container">
    @include('auth.brand-panel')
    <main class="main-panel">
        <section class="form-wrapper" x-data="loginPage()">
            <template x-if="error"><div class="status-toast" role="alert" x-text="error"></div></template>
            <div class="form-header"><div class="campus-pill">USTP CLAVERIA PORTAL</div><h2 class="form-title">Welcome back</h2><p class="form-subtitle">Sign in to your CompliTrack account</p></div>
            @if ($errors->any()) <div class="status-toast" role="alert">{{ $errors->first() }}</div> @endif
            <form class="auth-form" @submit.prevent="submit()">
                <div class="form-group"><label for="loginEmail" class="form-label">Institutional Email</label><input x-model="email" type="email" id="loginEmail" class="form-input" placeholder="Enter your email" autocomplete="username" required autofocus></div>
                <div class="form-group"><label for="loginPassword" class="form-label">Password</label><div class="password-input-wrapper"><input x-model="password" type="password" id="loginPassword" class="form-input password-input" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle-btn" aria-label="Toggle password visibility" @click="$el.previousElementSibling.type = $el.previousElementSibling.type === 'password' ? 'text' : 'password'"><i data-lucide="eye" class="eye-icon eye-show"></i></button></div></div>
                <div class="form-row-utility"><label class="checkbox-container"><input x-model="remember" type="checkbox"><span class="checkbox-label">Remember Me</span></label><a href="{{ route('password.request') }}" class="link-forgot">Forgot password?</a></div>
                <button :disabled="loading" type="submit" class="btn btn-primary"><span class="btn-text" x-text="loading ? 'Signing in…' : 'Log In'"></span><span class="btn-arrow">&rarr;</span></button>
            </form>
            <div class="form-disclaimer"><p>Accounts are provisioned by your Department Admin. Contact your Super Admin if you need access.</p></div>
        </section>
    </main>
</div>
@endsection
