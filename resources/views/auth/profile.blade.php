@extends('layouts.app')
@section('title', 'My Profile')
@section('heading', 'My Profile')
@section('content')
@php
    $profileUser = auth()->user();
    $profileInitials = collect(explode(' ', $profileUser->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $avatarUrl = $profileUser->avatarPath ? url('/api/v1/auth/avatar') : null;
@endphp
<section class="profile-page max-w-3xl" x-data="profilePage({ avatarUrl: @js($avatarUrl) })">
    <h2 class="font-heading text-2xl font-bold text-ustp-navy">Account details</h2>
    <p class="mt-1 text-sm text-ustp-muted">Your CompliTrack account and assigned role.</p>
    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div class="flex flex-wrap items-center gap-4 border-b border-slate-100 pb-6">
            <div class="profile-photo-frame">
                <img x-show="avatarUrl" :src="avatarUrl" alt="Profile photo" class="profile-photo" data-user-avatar>
                <span x-show="!avatarUrl" class="profile-photo-fallback" data-user-avatar-fallback>{{ $profileInitials }}</span>
            </div>
            <div class="min-w-0 flex-1"><h3 class="font-heading text-xl font-semibold">{{ $profileUser->fullName }}</h3><p class="text-sm text-ustp-muted">{{ $profileUser->email }}</p></div>
            <div class="flex flex-col items-start gap-1 sm:items-end">
                <input x-ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="uploadAvatar($event)">
                <button type="button" @click="$refs.avatarInput.click()" :disabled="uploading" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-ustp-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-ustp-gold hover:text-ustp-navy disabled:cursor-wait disabled:opacity-60">
                    <i data-lucide="camera" class="size-4" aria-hidden="true"></i><span x-text="uploading ? 'Uploading…' : (avatarUrl ? 'Change photo' : 'Upload photo')"></span>
                </button>
                <span class="text-xs text-ustp-muted">JPG, PNG, or WebP · up to 2 MB</span>
            </div>
        </div>
        <template x-if="error"><p class="mt-4 rounded-lg bg-error/10 px-3 py-2 text-sm text-error" role="alert" x-text="error"></p></template>
        <template x-if="message"><p class="mt-4 rounded-lg bg-success/10 px-3 py-2 text-sm text-success" role="status" x-text="message"></p></template>
        <dl class="grid gap-x-8 gap-y-5 pt-6 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted">User code</dt><dd class="mt-1 text-sm">{{ $profileUser->userCode }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted">Role</dt><dd class="mt-1"><x-role-badge :role-id="$profileUser->roleId" /></dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted">Office</dt><dd class="mt-1 text-sm">{{ $profileUser->officeName ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted">Account status</dt><dd class="mt-1 text-sm">{{ $profileUser->isActive ? 'Active' : 'Inactive' }}</dd></div>
        </dl>
    </div>
</section>
@endsection
