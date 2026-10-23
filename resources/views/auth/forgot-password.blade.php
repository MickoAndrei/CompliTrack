@extends('layouts.app')
@section('title', 'Reset password')
@section('content')
<div class="auth-container">
    @include('auth.brand-panel')
    <main class="main-panel" x-data="forgotPasswordPage()">
        <template x-if="error"><div class="status-toast" role="alert" x-text="error"></div></template>
        <section class="form-wrapper" x-show="!submitted">
            <a href="{{ route('login') }}" class="back-link-btn"><span class="back-arrow">&larr;</span> Back to log in</a>
            <div class="form-header"><h1 class="form-title">Forgot password?</h1><p class="form-instructions">No worries. Enter your registered USTP email and we'll send you a link to reset it.</p></div>
            <form class="auth-form" @submit.prevent="submit()">
                <div class="form-group"><label for="resetEmail" class="form-label">Institutional Email</label><input x-model="email" type="email" id="resetEmail" class="form-input" placeholder="Enter your registered email" autocomplete="email" required></div>
                <button :disabled="loading" type="submit" class="btn btn-primary"><span x-text="loading ? 'Sending…' : 'Send Reset Link'"></span><i data-lucide="send"></i></button>
            </form>
            <div class="form-footer-action"><span>Remembered your password?</span><a href="{{ route('login') }}" class="link-bold">Log in</a></div>
        </section>
        <section class="form-wrapper check-inbox-wrapper" x-cloak x-show="submitted">
            <div class="back-button-container"><a href="{{ route('login') }}" class="back-link-btn"><span class="back-arrow">&larr;</span> Back to log in</a></div>
            <div class="inbox-card"><div class="mail-badge-icon"><i data-lucide="mail"></i></div><h2 class="form-title inbox-title">Check your inbox</h2><p class="inbox-message">If an active account exists for <strong x-text="email"></strong>, a reset link has been sent. The link expires in 10 minutes.</p><button type="button" class="btn btn-secondary" :disabled="loading" @click="submit()" x-text="loading ? 'Sending…' : 'Resend link'"></button><div class="form-footer-action"><span>Remembered your password?</span><a href="{{ route('login') }}" class="link-bold">Log in</a></div></div>
        </section>
    </main>
</div>
@endsection
