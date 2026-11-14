const errorMessage = error => Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || 'Unable to save. Please retry.';

document.addEventListener('alpine:init', () => {
    Alpine.data('auditPlanPermissions', () => ({
        roles: [], selected: 0, permission: 'audit_plans.approve', enabled: false, password: '', remarks: '', loading: true, saving: false, error: '', notice: '',
        init() { this.load(); },
        current() { return this.roles.find(role => role.roleId === Number(this.selected)); },
        choose() { this.enabled = this.current()?.enabled || false; this.password = ''; this.remarks = ''; },
        async load() {
            this.loading = true; this.error = '';
            try { const { data } = await window.axios.get('/api/v1/roles', { params: { permission: this.permission } }); this.roles = data.roles; this.choose(); }
            catch (error) { this.error = errorMessage(error); }
            finally { this.loading = false; }
        },
        async save() {
            if (this.saving || !this.current()) return;
            this.saving = true; this.error = ''; this.notice = '';
            try {
                const { data } = await window.axios.patch(`/api/v1/roles/${this.selected}/permissions`, {
                    permission: this.permission, enabled: this.enabled, version: this.current().version,
                    password: this.password, remarks: this.remarks,
                });
                this.roles = data.roles; this.choose(); this.notice = 'Permission updated.';
            } catch (error) { this.error = errorMessage(error); if (error.response?.status === 409) await this.load(); }
            finally { this.password = ''; this.saving = false; }
        },
    }));
    Alpine.data('auditPlans', () => ({
        data: { plans: [], archives: [], units: [], reviewers: [] }, loading: true, saving: false, error: '', notice: '',
        editing: null, selected: '', remarks: {}, draft: {},
        init() { this.load(); },
        blank() { return { unitIds: [], reviewerIds: [], scope: '', criteria: '', scheduleStart: '', scheduleEnd: '', procedureReference: '', remarks: '' }; },
        async load() {
            this.loading = true;
            try { const { data } = await window.axios.get('/api/v1/audit-plans'); this.data = data; }
            catch (error) { this.error = errorMessage(error); }
            finally { this.loading = false; }
        },
        edit(row = null) {
            this.editing = row; this.selected = row?.id || ''; this.error = '';
            this.draft = { ...this.blank(), ...JSON.parse(JSON.stringify(row?.plan?.header || {})) };
            this.$refs.editor.showModal();
        },
        async save() {
            if (this.saving) return;
            this.saving = true; this.error = ''; this.notice = '';
            try {
                await window.axios.post(`/api/v1/audit-plans/${this.selected}`, { ...this.draft, action: 'save', version: this.editing?.plan.version || 0 });
                this.$refs.editor.close(); await this.load(); this.notice = 'Draft saved. Submit it when ready for approval.';
            } catch (error) { this.error = errorMessage(error); }
            finally { this.saving = false; }
        },
        async act(row, action) {
            if (this.saving) return;
            this.saving = true; this.error = ''; this.notice = '';
            try {
                const { data } = await window.axios.post(`/api/v1/audit-plans/${row.id}`, { action, version: row.plan.version, remarks: this.remarks[row.id] || '' });
                this.notice = data.message; this.remarks[row.id] = ''; await this.load();
            } catch (error) { this.error = errorMessage(error); if (error.response?.status === 409) await this.load(); }
            finally { this.saving = false; }
        },
    }));
});
