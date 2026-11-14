@extends('layouts.app')
@section('title', 'My Profile')
@section('heading', 'My Profile')
@section('subtitle', 'Account details & security')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
@endpush
@section('content')
@php
    $profileUser = auth()->user();
    $roleNames = ['Super Admin', 'Admin', 'Auditor', 'Auditee'];
    $profileInitials = collect(explode(' ', $profileUser->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $avatarUrl = $profileUser->avatarUrl();
@endphp
<section class="super-admin-profile-page" x-data="profilePage({ avatarUrl: @js($avatarUrl) })">
    <div x-page-notice="error ? 'error' : 'success'" x-cloak x-show="error || message" x-transition class="profile-feedback" :class="error ? 'is-error' : 'is-success'" :role="error ? 'alert' : 'status'" aria-live="polite">
        <span x-text="error || message"></span>
        <button type="button" @click="error = null; message = null" aria-label="Dismiss message"><i data-lucide="x" aria-hidden="true"></i></button>
    </div>

    @if (session('status'))
        <div x-page-notice class="profile-feedback is-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($profileUser->passwordChangeRequired)
        <div x-page-notice class="profile-feedback is-error" role="alert">Your administrator issued a temporary password. Change it below before continuing to use CompliTrack.</div>
    @endif

    <article class="super-admin-profile-card">
        <header class="super-admin-profile-identity">
            <div class="super-admin-profile-avatar">
                <button type="button" class="profile-photo-open" @click="openPhotoPreview()" :disabled="!avatarUrl" aria-label="View full-size profile picture" aria-haspopup="dialog">
                <img x-show="avatarUrl" :src="avatarUrl" alt="Profile photo" class="profile-photo" data-user-avatar>
                <span x-show="!avatarUrl" class="super-admin-profile-crest" data-user-avatar-fallback aria-hidden="true">
                    <span>USTP</span>
                </span>
                </button>
                <input x-ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="uploadAvatar($event)">
                <button type="button" class="super-admin-profile-camera" @click="$refs.avatarInput.click()" :disabled="uploading" aria-label="Upload or update profile picture">
                    <i data-lucide="camera" aria-hidden="true"></i>
                </button>
            </div>
            <h2>{{ $profileUser->fullName }}</h2>
            <div class="profile-role-details">
                <x-role-badge :role-id="$profileUser->roleId" class="super-admin-profile-role" :data-profile-role="(int) $profileUser->roleId" />
                <x-organizational-responsibility :user="$profileUser" />
            </div>
            <p class="super-admin-profile-avatar-hint" x-text="uploading ? 'Uploading photo…' : (avatarUrl ? 'Select your photo to enlarge it, or the camera to update it' : 'Select the camera to add a profile photo')"></p>
        </header>

        <form method="POST" action="{{ route('profile.update') }}" class="super-admin-profile-form">
            @csrf
            @method('PATCH')
            <label class="super-admin-profile-field">
                <span>Full Name</span>
                <input type="text" name="fullName" value="{{ old('fullName', $profileUser->fullName) }}" autocomplete="name" maxlength="255" required>
                @error('fullName')<small class="profile-field-error">{{ $message }}</small>@enderror
            </label>
            <label class="super-admin-profile-field">
                <span>Email Address</span>
                <input type="email" value="{{ $profileUser->email }}" readonly aria-readonly="true">
            </label>
            <label class="super-admin-profile-field">
                <span>Role</span>
                <input type="text" value="{{ $roleNames[(int) $profileUser->roleId] ?? 'Unknown' }}" data-profile-role="{{ (int) $profileUser->roleId }}" readonly aria-readonly="true">
            </label>
            <button type="submit" class="super-admin-profile-save">Save Profile</button>
            @if ($errors->has('fullName'))
                <p class="profile-form-error" role="alert">Your profile details could not be saved.</p>
            @endif
        </form>

        <form method="POST" action="{{ route('profile.password.update') }}" class="super-admin-password-form">
            @csrf
            @method('PATCH')
            <h3>Change Password</h3>
            <label class="super-admin-profile-field">
                <span>Current Password</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
                @error('current_password')<small class="profile-field-error">{{ $message }}</small>@enderror
            </label>
            <label class="super-admin-profile-field">
                <span>New Password</span>
                <input type="password" name="password" autocomplete="new-password" minlength="12" required>
                @error('password')<small class="profile-field-error">{{ $message }}</small>@enderror
            </label>
            <label class="super-admin-profile-field">
                <span>Confirm New Password</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
            </label>
            <button type="submit" class="super-admin-password-submit">{{ $profileUser->passwordChangeRequired ? 'Set Password & Continue to Dashboard' : 'Update Password' }}</button>
        </form>

        <div class="super-admin-profile-actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="super-admin-switch-account">Switch Account</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="super-admin-profile-logout"><i data-lucide="log-out" aria-hidden="true"></i><span>Log Out</span></button>
            </form>
        </div>
        <p class="super-admin-switch-hint">Switch Account securely ends this session and returns you to sign in.</p>
    </article>
    <dialog class="profile-photo-preview" x-ref="photoPreview" aria-labelledby="profile-photo-preview-title" @click="closePhotoOnBackdrop($event)">
        <header><h3 id="profile-photo-preview-title">Profile picture</h3><button type="button" @click="$refs.photoPreview.close()" aria-label="Close profile picture"><i data-lucide="x" aria-hidden="true"></i></button></header>
        <img :src="avatarUrl || null" alt="Full-size profile picture" x-show="!photoPreviewError" x-on:error="photoPreviewError = true">
        <p x-page-notice x-show="photoPreviewError" x-cloak role="alert">This profile picture could not be loaded. Close the preview and try again.</p>
    </dialog>
</section>
@endsection
