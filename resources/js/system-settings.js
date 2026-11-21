import { portalDate } from './portal-date';
document.addEventListener('alpine:init', () => {
    Alpine.data('systemSettings', () => ({
        state: {}, loading: true, busy: false, switching: false, message: '', error: '',
        action: '', password: '', confirmation: '',
        databaseConfirmation: '',
        async init() { await this.refresh(); },
        async refresh() {
            try {
                const { data } = await window.axios.get('/api/v1/settings');
                this.state = data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Unable to load settings. Reload to try again.';
            } finally { this.loading = false; }
        },
        async request(method, endpoint, payload, success) {
            if (this.busy) return false;
            this.busy = true;
            this.message = '';
            this.error = '';
            try {
                const { data } = await window.axios({ method, url: `/api/v1/settings${endpoint}`, data: payload });
                this.message = data.message || success;
                await this.refresh();
                return true;
            } catch (error) {
                const data = error.response?.data;
                this.error = Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'The operation could not complete. Please try again.';
                await this.refresh();
                return false;
            } finally { this.busy = false; }
        },
        async toggle(key) {
            await this.request('patch', '', { [key]: !this.state[key] }, 'Settings saved.');
        },
        async backup() { await this.request('post', '/backup', {}); },
        databaseSwitchLabel() {
            const active = this.state.databaseSwitch?.activeEnvironment;
            if (active === 'local') return 'Switch to Remote Database';
            if (active === 'remote') return 'Switch to Local Database';
            return 'Database environment unavailable';
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
            })[this.state.databaseSwitch?.pendingRequest?.stage] || 'In progress';
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
            if (this.busy || this.databaseConfirmation !== this.databaseConfirmationPhrase()) return;
            this.busy = true;
            this.error = '';
            this.message = '';
            try {
                const { data } = await window.axios.post('/super-admin/system/database-switch', {
                    target: this.state.databaseSwitch.targetEnvironment,
                    confirmation: this.databaseConfirmation,
                });
                this.message = data.message;
                this.switching = true;
                this.$refs.databaseSwitchDialog.close();
            } catch (error) {
                const data = error.response?.data;
                if (!error.response) {
                    this.message = 'The connection closed while the switch was starting. Wait for services to restart, then sign in again.';
                    this.switching = true;
                    this.$refs.databaseSwitchDialog.close();
                } else {
                    this.error = Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'The database switch could not be started.';
                    await this.refresh();
                }
            } finally {
                this.databaseConfirmation = '';
                this.busy = false;
            }
        },
        openConfirmation(action) {
            this.action = action;
            this.password = '';
            this.confirmation = '';
            this.error = '';
            this.$refs.confirmationDialog.showModal();
            this.$nextTick(() => this.$refs.currentPassword.focus());
        },
        closeConfirmation() {
            if (this.busy) return;
            this.$refs.confirmationDialog.close();
            this.password = '';
            this.confirmation = '';
        },
        async confirm() {
            const ok = await this.request('post', this.action === 'wipe' ? '/wipe' : '/rotate-key', {
                password: this.password, confirmation: this.confirmation,
            });
            this.password = '';
            if (ok) this.closeConfirmation();
        },
        formattedDate(value) { return value ? portalDate(value, {precise:true}) : 'Not yet'; },
    }));
});
