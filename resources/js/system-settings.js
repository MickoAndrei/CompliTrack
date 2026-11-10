document.addEventListener('alpine:init', () => {
    Alpine.data('systemSettings', () => ({
        state: {}, loading: true, busy: false, message: '', error: '',
        action: '', password: '', confirmation: '',
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
        formattedDate(value) { return value ? new Date(value).toLocaleString() : 'Not yet'; },
    }));
});
