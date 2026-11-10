@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Settings')
@section('subtitle', 'Super Admin - System configuration')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/system-settings.css') }}?v={{ filemtime(public_path('css/system-settings.css')) }}">
@endpush
@section('content')
<section class="app-view-panel active app-settings-page" aria-label="System settings" x-data="systemSettings" :aria-busy="busy || loading">
    <p x-show="loading" class="settings-feedback" role="status">Loading system settings...</p>
    <p x-page-notice x-cloak x-show="message" x-text="message" class="settings-feedback settings-success" role="status"></p>
    <p x-page-notice x-cloak x-show="error" x-text="error" class="settings-feedback settings-error" role="alert"></p>
    <p x-cloak x-show="busy" class="settings-feedback" role="status">Processing your request. Please keep this page open.</p>
    <div class="settings-grid">
        <article class="settings-card">
            <h2>Automated Backup</h2>
            <p>Nightly encrypted backup of the CompliTrack database and uploaded files.</p>
            <div class="settings-card-footer">
                <span id="backup-label" class="settings-control-label">Enable backups</span>
                <button type="button" class="settings-switch" role="switch" :aria-checked="Boolean(state.backupsEnabled).toString()" aria-labelledby="backup-label" :disabled="loading || busy" @click="toggle('backupsEnabled')"><span></span></button>
            </div>
            <p x-cloak x-show="state.backupsEnabled">Scheduled daily at <span x-text="state.backupTime"></span> (<span x-text="state.timezone"></span>).</p>
            <p x-page-notice="'warning'" x-cloak x-show="state.backupsEnabled && !state.schedulerRunning" class="settings-warning">Scheduler is offline. Start CompliTrack with its startup script to run nightly backups.</p>
            <p>Last backup: <span x-text="formattedDate(state.lastBackup?.completedAt)"></span></p>
            <p x-page-notice="'warning'" x-cloak x-show="state.backupError" x-text="state.backupError" class="settings-warning"></p>
            <button type="button" class="settings-text-button settings-secondary-action" :disabled="loading || busy" @click="backup()">Back up now</button>
        </article>
        <article class="settings-card">
            <h2>Encryption Key Rotation</h2>
            <p>AES-256-GCM encryption protects archived documents. Rotation re-encrypts existing files.</p>
            <div class="settings-card-footer">
                <button type="button" class="settings-button settings-button-outline" :disabled="loading || busy" @click="openConfirmation('rotate')" x-text="state.rotationPending ? 'Resume Key Rotation' : 'Rotate Encryption Key'">Rotate Encryption Key</button>
            </div>
            <p>Last rotation: <span x-text="formattedDate(state.lastRotation)"></span></p>
            <p x-page-notice="'warning'" x-cloak x-show="state.rotationPending" class="settings-warning">A previous rotation is incomplete. Resume to finish migrating the remaining files.</p>
        </article>
        <article class="settings-card">
            <h2>Maintenance Mode</h2>
            <p>Temporarily blocks other users from logging in or accessing the portal. Superadmins retain access.</p>
            <div class="settings-card-footer">
                <span id="maintenance-label" class="settings-control-label">Enable maintenance mode</span>
                <button type="button" class="settings-switch" role="switch" :aria-checked="Boolean(state.maintenanceEnabled).toString()" aria-labelledby="maintenance-label" :disabled="loading || busy" @click="toggle('maintenanceEnabled')"><span></span></button>
            </div>
            <p x-page-notice="'warning'" x-cloak x-show="state.maintenanceEnabled" class="settings-warning">Maintenance mode is on. Turn it off to restore access for other users.</p>
        </article>
        <article class="settings-card settings-danger-card">
            <h2>Danger Zone - Wipe All System Data</h2>
            <p>Permanently deletes application data, including:</p>
            <ul>
                <li>All user accounts except your signed-in superadmin account</li>
                <li>Departments, compliance checks, archive records, notifications and audit logs</li>
                <li>Uploaded files, profile photos, and saved backups</li>
                <li>Other sessions and API tokens; application counters are reset</li>
            </ul>
            <p>Your superadmin account, role definitions, and system configuration are retained.</p>
            <div class="settings-card-footer">
                <span class="settings-muted">This cannot be undone. Your password and a typed confirmation are required.</span>
                <button type="button" class="settings-button settings-button-danger" :disabled="loading || busy" @click="openConfirmation('wipe')">Wipe All Data</button>
            </div>
        </article>
    </div>
    <dialog x-ref="confirmationDialog" class="settings-dialog" aria-labelledby="settings-confirm-title" @cancel="if (busy) $event.preventDefault(); else { password = ''; confirmation = ''; }">
        <form @submit.prevent="confirm()">
            <h2 id="settings-confirm-title" x-text="action === 'wipe' ? 'Permanently wipe system data?' : 'Rotate archive encryption key?'"></h2>
            <p x-text="action === 'wipe' ? 'All listed data and backups will be deleted. Only your superadmin account, role definitions and system configuration remain.' : 'Existing archives will be re-encrypted. If interrupted, the operation can be resumed safely.'"></p>
            <p x-page-notice x-show="error" x-text="error" class="settings-feedback settings-error" role="alert"></p>
            <label for="settings-current-password">Your current password</label>
            <input id="settings-current-password" x-ref="currentPassword" type="password" autocomplete="current-password" x-model="password" :disabled="busy" required>
            <div x-show="action === 'wipe'">
                <label for="settings-confirm-wipe">Type WIPE ALL DATA to confirm</label>
                <input id="settings-confirm-wipe" type="text" x-model="confirmation" autocomplete="off" :disabled="busy" :required="action === 'wipe'">
            </div>
            <div class="settings-card-footer">
                <button type="button" class="settings-button settings-button-outline" :disabled="busy" @click="closeConfirmation()">Cancel</button>
                <button type="submit" class="settings-button" :class="action === 'wipe' ? 'settings-button-danger' : 'settings-button-gold'" :disabled="busy || !password || (action === 'wipe' && confirmation !== 'WIPE ALL DATA')" x-text="busy ? 'Processing...' : (action === 'wipe' ? 'Permanently Wipe Data' : 'Rotate Key')"></button>
            </div>
        </form>
    </dialog>
</section>
@endsection
