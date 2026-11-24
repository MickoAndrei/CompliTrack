import { portalDate } from './portal-date';
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
        editing: null, selected: '', remarks: {}, draft: {}, showLegacy: false, date: portalDate,
        statusFilter: '', search: '', unitFilter: '', sortBy: 'newest', page: 1, perPage: 10, detailId: null,
        countStatus(status) { return (this.data.plans || []).filter(row => !status || row.plan.status === status).length; },
        unitNames(row) { return (row.plan.header.unitIds || []).map(id => this.data.units.find(unit => unit.id === id)?.name || id).join(', ') || '—'; },
        schedule(row) { const h = row.plan.header; return h.scheduleStart && h.scheduleEnd ? `${h.scheduleStart} to ${h.scheduleEnd}` : '—'; },
        get filteredPlans() {
            const search = this.search.trim().toLowerCase();
            return (this.data.plans || []).filter(row => (!this.statusFilter || row.plan.status === this.statusFilter) && (!this.unitFilter || (row.plan.header.unitIds || []).includes(this.unitFilter)) && (!search || `${row.title || ''} ${row.reference || ''}`.toLowerCase().includes(search))).sort((a,b) => {
                if (this.sortBy === 'title') return (a.title || '').localeCompare(b.title || '');
                const order = String(b.plan.updatedAt || '').localeCompare(String(a.plan.updatedAt || ''));
                return this.sortBy === 'oldest' ? -order : order;
            });
        },
        get lastPage() { return Math.max(1, Math.ceil(this.filteredPlans.length / this.perPage)); },
        get visiblePlans() { return this.filteredPlans.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
        get detailRow() { return (this.data.plans || []).find(row => row.id === this.detailId); },
        openDetails(row) { this.detailId = row.id; this.error = ''; this.notice = ''; this.$refs.details.showModal(); },
        get documentChoices() { return (this.data.archives || []).filter(row => row.eligible || this.showLegacy); },
        get needsClassification() { return this.editing ? !this.editing.documentEligible : !!this.selected && !(this.data.archives || []).find(row => row.id === this.selected)?.eligible; },
        init() { this.draft = this.blank(); this.load(); },
        blank() { return { unitIds: [], reviewerIds: [], scope: '', criteria: '', scheduleStart: '', scheduleEnd: '', procedureReference: '', remarks: '' }; },
        async load() {
            this.loading = true;
            try { const { data } = await window.axios.get('/api/v1/audit-plans'); if (this.detailId && !data.plans.some(row => row.id === this.detailId)) { if (this.$refs.details.open) this.$refs.details.close(); this.detailId = null; } this.data = data; this.page = Math.min(this.page, this.lastPage); }
            catch (error) { this.error = errorMessage(error); }
            finally { this.loading = false; }
        },
        toggleSelection(field, id) {
            if (!['unitIds', 'reviewerIds'].includes(field)) return;
            const options = field === 'unitIds' ? this.data.units : this.data.reviewers;
            if (!options.some(item => item.id === id)) return;
            const values = this.draft[field] || [];
            this.draft[field] = values.includes(id) ? values.filter(value => value !== id) : [...values, id];
        },
        displayDate(value) {
            const parts = String(value || '').split('-');
            return parts.length === 3 ? `${parts[1]}/${parts[2]}/${parts[0]}` : '';
        },
        setDate(field, input) {
            const value = input.value.trim();
            if (!value) { this.draft[field] = ''; input.setCustomValidity(''); return; }
            const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value);
            const iso = match ? `${match[3]}-${match[1]}-${match[2]}` : '';
            const date = new Date(iso + 'T00:00:00Z');
            if (!match || match[3] === '0000' || Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== iso) {
                input.setCustomValidity('Enter a valid date in MM/DD/YYYY format.');
                return;
            }
            input.setCustomValidity(''); this.draft[field] = iso;
        },
        edit(row = null) {
            this.editing = row; this.selected = row?.id || ''; this.error = '';
            this.draft = { ...this.blank(), ...JSON.parse(JSON.stringify(row?.plan?.header || {})) };
            this.showLegacy = false;
            this.draft.confirmPlanDocument = false;
            this.draft.classificationReason = '';
            if (!row) {
                this.draft.unitIds = (this.data.units || []).map(unit => unit.id);
                const currentId = String(document.body.dataset.userId || '');
                this.draft.reviewerIds = (this.data.reviewers || []).some(person => String(person.id) === currentId) ? [currentId] : [];
            }
            this.$refs.editor.showModal();
            this.$nextTick(() => {
                for (const [ref, field] of [['startText', 'scheduleStart'], ['endText', 'scheduleEnd']]) {
                    this.$refs[ref].setCustomValidity('');
                    this.$refs[ref].value = this.displayDate(this.draft[field]);
                }
            });
        },
        async save() {
            if (this.saving) return;
            if (!this.selected || !this.draft.unitIds.length || !this.draft.reviewerIds.length) {
                this.error = 'Select a document, at least one covered unit and at least one authorized audit team leader.'; return;
            }
            if (this.needsClassification && (!this.draft.confirmPlanDocument || String(this.draft.classificationReason || '').trim().length < 10)) {
                this.error = 'Confirm the controlled audit-plan classification and provide a reason of at least 10 characters.'; return;
            }
            this.setDate('scheduleStart', this.$refs.startText);
            this.setDate('scheduleEnd', this.$refs.endText);
            if (!this.$refs.startText.reportValidity() || !this.$refs.endText.reportValidity()) return;
            if (!this.draft.scheduleStart || !this.draft.scheduleEnd || this.draft.scheduleEnd < this.draft.scheduleStart) {
                this.error = 'Choose a valid audit schedule. End date must be on or after start date.'; return;
            }
            const procedure = String(this.draft.procedureReference || '').trim();
            if (procedure.length < 5 || !/\p{L}/u.test(procedure) || ['none','na','notapplicable','nil','null','tbd','tobeconfirmed','unknown','pending','test','placeholder'].includes(procedure.toLowerCase().replace(/[^a-z0-9]/g,''))) {
                this.error = 'Provide the applicable independence/conflict procedure code or meaningful reference. Placeholder-only values are not valid.'; return;
            }
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
