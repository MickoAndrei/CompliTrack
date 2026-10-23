@extends('layouts.app')
@section('title', 'My Profile')
@section('heading', 'My Profile')
@section('content')
@php
    $profileUser = auth()->user();
    $profileInitials = collect(explode(' ', $profileUser->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $avatarUrl = $profileUser->avatarPath ? url('/api/v1/auth/avatar') : null;
@endphp
<section class="account-profile-page mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8" x-data="profilePage({ avatarUrl: @js($avatarUrl) })">
    <header class="account-profile-heading mb-6">
        <p class="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-ustp-muted">Your account</p>
        <h2 class="font-heading text-3xl font-bold tracking-tight text-ustp-navy">Account details</h2>
        <p class="mt-2 text-sm leading-6 text-ustp-muted">Manage your profile photo and review your CompliTrack access.</p>
    </header>

    <div class="account-profile-card overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="account-profile-identity flex flex-col gap-5 border-b border-slate-100 bg-gradient-to-br from-white to-slate-50/80 p-6 sm:flex-row sm:items-center sm:p-8">
            <div class="profile-photo-frame size-20 shrink-0 overflow-hidden rounded-full ring-4 ring-white shadow-sm sm:size-24">
                <img x-show="avatarUrl" :src="avatarUrl" alt="Profile photo" class="profile-photo" data-user-avatar>
                <span x-show="!avatarUrl" class="profile-photo-fallback" data-user-avatar-fallback>{{ $profileInitials }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="font-heading text-xl font-semibold tracking-tight text-ustp-dark sm:text-2xl">{{ $profileUser->fullName }}</h3>
                <p class="mt-1 break-all text-sm text-ustp-muted">{{ $profileUser->email }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                <input x-ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="uploadAvatar($event)">
                <button type="button" @click="$refs.avatarInput.click()" :disabled="uploading" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-ustp-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#12336c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ustp-gold disabled:cursor-wait disabled:opacity-60">
                    <i data-lucide="camera" class="size-4" aria-hidden="true"></i>
                    <span x-text="uploading ? 'Uploading...' : (avatarUrl ? 'Change photo' : 'Upload photo')"></span>
                </button>
                <span class="text-xs text-ustp-muted">JPG, PNG, or WebP · up to 2 MB</span>
            </div>
        </div>

        <div x-cloak x-show="error || message" x-transition class="fixed right-6 top-6 z-[200] flex min-h-[52px] w-[calc(100%-3rem)] max-w-sm items-center rounded-lg border-l-4 bg-ustp-navy py-4 pr-5 text-[13px] font-medium leading-5 text-white shadow-[0_10px_25px_rgba(5,27,68,0.18)]" :class="error ? 'border-red-500' : 'border-ustp-gold'" style="padding-left: 24px" :role="error ? 'alert' : 'status'" aria-live="polite">
            <span class="min-w-0 flex-1" x-text="error || message"></span>
        </div>

        <dl class="account-profile-details grid gap-3 p-6 sm:grid-cols-2 sm:gap-4 sm:px-8 sm:pb-8">
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">User code</dt>
                <dd class="mt-2 font-semibold text-ustp-dark">{{ $profileUser->userCode }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">Role</dt>
                <dd class="mt-2"><x-role-badge :role-id="$profileUser->roleId" /></dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">Office</dt>
                <dd class="mt-2 font-medium text-ustp-dark">{{ $profileUser->officeName ?: '—' }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">Account status</dt>
                <dd class="mt-2 inline-flex items-center gap-2 font-medium text-ustp-dark">
                    <span class="size-2 rounded-full {{ $profileUser->isActive ? 'bg-success' : 'bg-error' }}"></span>
                    {{ $profileUser->isActive ? 'Active' : 'Inactive' }}
                </dd>
            </div>
        </dl>

        <div class="account-profile-signout flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-6 py-4 sm:px-8">
            <p class="text-sm text-ustp-muted">Finished with your session?</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ustp-navy">
                    <i data-lucide="log-out" class="size-4" aria-hidden="true"></i>
                    <span>Sign out</span>
                </button>
            </form>
        </div>
    </div>
</section>
@endsection
