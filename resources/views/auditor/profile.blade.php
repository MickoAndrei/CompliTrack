<section class="auditor-profile-card" x-data="profilePage({ avatarUrl: @js($avatarUrl) })">
    <div class="auditor-profile-identity">
        <button class="auditor-profile-avatar" type="button" @click="$refs.avatarInput.click()" :disabled="uploading" aria-label="Change profile photo">
            <img x-show="avatarUrl" :src="avatarUrl" alt="Profile photo" data-user-avatar>
            <span x-show="!avatarUrl" data-user-avatar-fallback>{{ $profileInitials }}</span>
        </button>
        <input x-ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="uploadAvatar($event)">
        <h2>{{ $profileUser->fullName }}</h2><span class="auditor-role-badge">Auditor</span>
        <p x-cloak x-show="error || message" x-text="error || message" role="status"></p>
    </div>
    @include('auditor.audit-feedback')
    <form method="POST" action="{{ route('profile.password.update') }}" class="auditor-password-form">
        @csrf @method('PATCH')
        <label>Full Name<input value="{{ $profileUser->fullName }}" readonly autocomplete="name"></label>
        <h3>Change Password</h3>
        <label>Current Password<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>New Password<input type="password" name="password" required minlength="12" placeholder="Type new password" autocomplete="new-password"></label>
        <label>Confirm New Password<input type="password" name="password_confirmation" required minlength="12" placeholder="Confirm new password" autocomplete="new-password"></label>
        <p>Use at least 12 characters.</p>
        <button class="auditor-gold-button" type="submit">Update Password</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="auditor-profile-signout">@csrf<button type="submit">Sign out</button></form>
</section>
