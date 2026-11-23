@once
@push('styles')
<link rel="stylesheet" href="{{ asset('css/database-switch.css') }}?v={{ filemtime(public_path('css/database-switch.css')) }}">
@endpush
@endonce
    <dialog x-ref="databaseSwitchDialog" class="settings-dialog database-switch-dialog" aria-labelledby="database-switch-confirm-title" @cancel="if (busy) $event.preventDefault(); else databaseConfirmation = ''">
        <form @submit.prevent="confirmDatabaseSwitch()">
            <h2 id="database-switch-confirm-title">Switch database environment?</h2>
            <p>The target will be health-checked before the local services stop. After switching, CompliTrack will verify the database, restart its services, and sign out all users. If verification fails, the previous target will be restored. No data will be copied, migrated, or seeded.</p>
            <p x-show="error" x-text="error" role="alert"></p>
            <label for="database-switch-confirmation">Type <span x-text="databaseConfirmationPhrase()"></span> to confirm</label>
            <input id="database-switch-confirmation" x-ref="databaseSwitchConfirmation" type="text" autocomplete="off"
                x-model="databaseConfirmation" :disabled="busy" required>
            <div class="settings-card-footer">
                <button type="button" class="settings-button settings-button-outline" :disabled="busy" @click="closeDatabaseSwitch()">Cancel</button>
                <button type="submit" class="settings-button settings-button-gold"
                    :disabled="busy || databaseConfirmation !== databaseConfirmationPhrase()"
                    x-text="busy ? 'Starting switch...' : 'Confirm and Switch'"></button>
            </div>
        </form>
    </dialog>
    <dialog x-ref="databaseSwitchProgress" class="database-switch-dialog database-switch-progress"
        aria-labelledby="database-switch-progress-title" aria-describedby="database-switch-progress-description"
        :aria-busy="busy || switching" @cancel="if (busy || switching) $event.preventDefault()">
        <div class="database-switch-orbit" :class="{'is-stopped': !busy && !switching}" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="none"><ellipse cx="16" cy="8" rx="10" ry="4"/><path d="M6 8v8c0 2.2 4.5 4 10 4s10-1.8 10-4V8M6 16v8c0 2.2 4.5 4 10 4s10-1.8 10-4v-8"/></svg>
        </div>
        <h2 id="database-switch-progress-title" x-text="error && !busy && !switching ? 'Switch needs attention' : switchProgressOutcome === 'switch_verified' ? 'Database connected' : 'Switching database server'"></h2>
        <p id="database-switch-progress-description">Please keep this page open. You will return to sign-in after services restart.</p>
        <div class="database-switch-progress-meta" role="status" aria-live="polite">
            <strong x-text="databaseSwitchStage()"></strong>
            <span x-text="`${databaseSwitchPercent()}%`"></span>
        </div>
        <div class="database-switch-progress-track" role="progressbar" aria-label="Database switch stages"
            aria-valuemin="0" aria-valuemax="100" :aria-valuenow="databaseSwitchPercent()" :aria-valuetext="databaseSwitchStage()"
            :class="{'is-active': busy || switching}">
            <span :style="`width: ${databaseSwitchPercent()}%`"></span>
        </div>
        <p class="database-switch-progress-note">Progress follows confirmed stages; database and restart times can vary.</p>
        <p class="database-switch-reconnecting" x-show="switchDisconnected && switching" role="status">Services are restarting. Reconnecting automatically…</p>
        <p x-show="error" x-text="error" role="alert"></p>
        <div class="settings-card-footer" x-show="!busy && !switching">
            <button type="button" class="settings-button" @click="closeDatabaseSwitchProgress()">Close</button>
        </div>
    </dialog>
