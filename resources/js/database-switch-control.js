import { waitForDatabaseSwitch, reconnectDatabaseSwitch } from './database-switch.js';

// Shared by Database Control and the dashboard; both use the same restart flow.
export function databaseSwitchControl() {
    return {
        state: {}, loading: true, busy: false, switching: false, message: '', error: '',
        databaseConfirmation: '',
        switchProgressStage: 'queued', switchProgressOutcome: null, switchDisconnected: false,
        showDatabaseSwitchProgress() {
            this.$refs?.databaseSwitchDialog?.close();
            const dialog = this.$refs?.databaseSwitchProgress;
            if (dialog && !dialog.open) dialog.showModal();
        },
        closeDatabaseSwitchProgress() {
            if (this.busy || this.switching) return;
            this.$refs.databaseSwitchProgress?.close();
        },
        databaseSwitchPercent() {
            if (this.switchProgressOutcome === 'switch_verified') return 100;
            return ({ queued: 5, preflight: 15, stopping_services: 30, changing_target: 45,
                verifying_target: 60, invalidating_logins: 75, starting_services: 90,
                rolling_back: 90, rollback_failed: 90 })[this.switchProgressStage] || 5;
        },
        async init() {
            await this.refresh();
            const pending = this.state.databaseSwitch?.pendingRequest;
            if (pending?.stage === 'rollback_failed') {
                this.error = 'The previous database switch failed to restart services. Check logs/database-switch.log before retrying.';
            } else if (pending?.statusUrl) this.watchDatabaseSwitch(pending.statusUrl);
        },
        destroy() { this.switchController?.abort(); },
        async reconnectSwitch() {
            const controller = new AbortController();
            this.switchController = controller;
            try {
                const result = await reconnectDatabaseSwitch({ signal: controller.signal });
                if (!result) return;
                if (result.signedOut) {
                    window.location.replace(window.tabUrl?.('/login?reason=server-restarted') || '/login?reason=server-restarted');
                } else if (result.statusUrl) {
                    this.state = result.state;
                    this.watchDatabaseSwitch(result.statusUrl);
                } else {
                    this.state = result.state;
                    this.switching = false;
                    this.switchDisconnected = false;
                    this.error = 'The switch was not queued. You can try again.';
                }
            } catch (error) {
                if (controller.signal.aborted) return;
                this.switching = false;
                this.switchDisconnected = false;
                this.error = error.message || 'Unable to reconnect to application services.';
            }
        },
        async watchDatabaseSwitch(statusUrl) {
            this.switchController?.abort();
            const controller = new AbortController();
            this.switchController = controller;
            this.switching = true;
            this.switchProgressStage = this.state.databaseSwitch?.pendingRequest?.stage || this.switchProgressStage;
            this.showDatabaseSwitchProgress();
            try {
                const result = await waitForDatabaseSwitch(statusUrl, {
                    signal: controller.signal,
                    onUnavailable: () => { this.switchDisconnected = true; },
                    onProgress: progress => {
                        this.switchDisconnected = false;
                        this.switchProgressStage = progress.stage;
                        const databaseSwitch = this.state.databaseSwitch;
                        databaseSwitch.pendingRequest = { ...databaseSwitch.pendingRequest, stage: progress.stage };
                    },
                });
                if (!result) return;
                this.switchProgressOutcome = result.outcome;
                if (['switch_verified', 'rolled_back'].includes(result.outcome)) {
                    const reason = result.outcome === 'switch_verified' ? 'database-switched' : 'database-switch-rolled-back';
                    window.location.replace(window.tabUrl?.(`/login?reason=${reason}`) || `/login?reason=${reason}`);
                    return;
                }
                this.error = result.outcome === 'rollback_failed'
                    ? 'The switch could not restore healthy services. Check the switch log.'
                    : 'The switch did not complete. The active database was not changed.';
                this.switching = false;
                this.switchDisconnected = false;
                await this.refresh();
            } catch (error) {
                if (controller.signal.aborted) return;
                this.switching = false;
                this.switchDisconnected = false;
                this.error = error.message || 'Unable to retrieve database switch progress.';
            }
        },
        async refresh() {
            try {
                const { data } = await window.axios.get('/api/v1/settings');
                this.state = data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Unable to load settings. Reload to try again.';
            } finally { this.loading = false; }
        },
        databaseSwitchLabel() {
            const active = this.state.databaseSwitch?.activeEnvironment;
            if (active === 'local') return 'Switch to Remote Database';
            if (active === 'remote') return 'Switch to Local Database';
            return 'Database environment unavailable';
        },
        databaseConnectionLabel() {
            if (this.loading) return 'Checking database...';
            const connection = this.state.databaseSwitch;
            if (!['local', 'remote'].includes(connection?.activeEnvironment)) return 'Database environment unavailable';
            return `${connection.activeEnvironment.toUpperCase()} — ${connection.health === 'connected' ? 'Connected' : 'Unavailable'}`;
        },
        databaseConfirmationPhrase() {
            const target = this.state.databaseSwitch?.targetEnvironment;
            return target ? `SWITCH TO ${target.toUpperCase()}` : '';
        },
        databaseSwitchStage() {
            return ({
                queued: 'Preparing',
                preflight: 'Checking services and database health',
                stopping_services: 'Stopping application services',
                changing_target: 'Changing the active database',
                verifying_target: 'Verifying the target database',
                invalidating_logins: 'Signing out all users',
                starting_services: 'Restarting application services',
                rolling_back: 'Restoring the previous database',
                rollback_failed: 'Needs operator attention',
            })[this.switchProgressStage] || 'In progress';
        },
        openDatabaseSwitch() {
            if (this.busy || this.switching || !this.state.databaseSwitch?.targetConfigured || this.state.databaseSwitch?.pendingRequest) return;
            this.databaseConfirmation = '';
            this.error = '';
            this.$refs.databaseSwitchDialog.showModal();
            this.$nextTick(() => this.$refs.databaseSwitchConfirmation.focus());
        },
        closeDatabaseSwitch() {
            if (this.busy) return;
            this.$refs.databaseSwitchDialog.close();
            this.databaseConfirmation = '';
        },
        async confirmDatabaseSwitch() {
            if (this.busy || this.switching || !this.state.databaseSwitch?.targetConfigured || this.state.databaseSwitch?.pendingRequest || this.databaseConfirmation !== this.databaseConfirmationPhrase()) return;
            this.busy = true;
            this.error = '';
            this.message = '';
            this.switchProgressStage = 'queued';
            this.switchProgressOutcome = null;
            this.switchDisconnected = false;
            this.showDatabaseSwitchProgress();
            try {
                const { data } = await window.axios.post('/super-admin/system/database-switch', {
                    target: this.state.databaseSwitch.targetEnvironment,
                    confirmation: this.databaseConfirmation,
                });
                this.message = data.message;
                this.switching = true;
                this.$refs.databaseSwitchDialog.close();
                this.watchDatabaseSwitch(data.statusUrl);
            } catch (error) {
                const data = error.response?.data;
                if (!error.response) {
                    this.message = 'The connection closed while the switch was starting. Wait for services to restart, then sign in again.';
                    this.switching = true;
                    this.$refs.databaseSwitchDialog.close();
                    this.reconnectSwitch();
                } else {
                    this.error = Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'The database switch could not be started.';
                    await this.refresh();
                }
            } finally {
                this.databaseConfirmation = '';
                this.busy = false;
            }
        },
    };
}
