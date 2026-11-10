const endpoint = '/api/v1/users';
const message = (error, fallback) => error.response?.data?.message || fallback;

// Extends the existing resourcePage component; all account writes use the existing Users API.
export function enhancedUserManagement(config) {
    if (!config.enhancedUsers) return {};
    return {
        userForm: { _id: null, fullName: '', email: '', roleId: 3, departmentId: '', officeName: '', isActive: true, assignedDepartmentIds: [], archiveResponsibility: '', archiveUnitIds: [], archiveCollegeIds: [] },
        summary: null, options: { departments: [], colleges: [], responsibilities: [] },
        filters: { roleId: '', departmentId: '', responsibility: '', status: '' },
        requestNumber: 0, detailRequest: 0, details: null, detailLoading: false, detailError: '',
        formError: '', fieldErrors: {}, originalUser: null, menuTarget: null, menuTrigger: null, actionTarget: null, action: '', actionError: '',
        collegeResponsibility() { return ['college_dean', 'college_secretary', 'cluster_administrator'].includes(this.userForm.archiveResponsibility); },
        responsibilityName(value) { return ({ department_head: 'Department Head', department_secretary: 'Department Secretary', college_dean: 'College Dean', college_secretary: 'College Secretary', cluster_administrator: 'Cluster Administrator', faculty_staff: 'Faculty / Staff' })[value] || 'Not assigned'; },
        headUnits() { return this.options.departments.filter(unit => unit.authorizedCet || this.originalUser?.archiveUnitIds?.includes(unit._id)); },
        assignmentNames(row) { return [...(row.organizationalColleges || []), ...(row.organizationalUnits || [])].join(', ') || row.departmentName || 'Not assigned'; },
        availableUnits() {
            const head = ['department_head', 'department_secretary'].includes(this.userForm.archiveResponsibility);
            return this.options.departments.filter(unit => (head ? unit.authorizedCet : unit.active && unit.verified && unit.unitType !== 'legacy_unclassified')
                || unit._id === this.originalUser?.departmentId);
        },
        async load(page = this.page) {
            const number = ++this.requestNumber;
            this.loading = true; this.error = null;
            try {
                const params = { page, perPage: this.perPage, management: 1, search: this.search };
                Object.entries(this.filters).forEach(([key, value]) => { if (value !== '') params[key] = value; });
                const { data } = await axios.get(endpoint, { params });
                if (number !== this.requestNumber) return;
                this.rows = data.data; this.meta = data.meta; this.page = data.meta.currentPage;
                this.summary = data.summary; this.options = data.options;
                if (this.page > this.meta.lastPage) return this.load(this.meta.lastPage);
            } catch (error) {
                if (number === this.requestNumber) this.error = message(error, 'Users could not be loaded. Please retry.');
            } finally {
                if (number === this.requestNumber) { this.loading = false; this.$nextTick(() => window.renderIcons?.()); }
            }
        },
        clearUserFilters() { this.search = ''; this.filters = { roleId: '', departmentId: '', responsibility: '', status: '' }; this.load(1); },
        startUserCreate() { this.openUserEditor(); },
        startUserEdit(row) { this.openUserEditor(row); },
        openUserEditor(row = null) {
            this.closeUserMenu(); this.originalUser = row ? structuredClone(Alpine.raw(row)) : null;
            this.formError = ''; this.fieldErrors = {};
            this.userForm = { _id: row?._id || null, fullName: row?.fullName || '', email: row?.email || '', roleId: Number(row?.roleId ?? 3),
                departmentId: row?.departmentId || '', officeName: row?.officeName || '', isActive: row?.isActive ?? true,
                assignedDepartmentIds: [...(row?.assignedDepartmentIds || [])], archiveResponsibility: row?.archiveResponsibility || '',
                archiveUnitIds: [...(row?.archiveUnitIds || [])], archiveCollegeIds: [...(row?.archiveCollegeIds || [])] };
            this.$refs.userEditor.showModal();
        },
        changeResponsibility() {
            this.userForm.archiveUnitIds = []; this.userForm.archiveCollegeIds = []; this.userForm.departmentId = '';
        },
        changePrimaryRole() {
            if (this.userForm.roleId === 2) { this.userForm.archiveResponsibility = ''; this.userForm.archiveUnitIds = []; this.userForm.archiveCollegeIds = []; }
            if (this.userForm.roleId !== 2) this.userForm.assignedDepartmentIds = [];
        },
        async saveUser() {
            if (this.saving) return;
            this.saving = true; this.formError = ''; this.fieldErrors = {};
            try {
                const { _id, ...payload } = structuredClone(Alpine.raw(this.userForm));
                payload.departmentId ||= null; payload.archiveResponsibility ||= null;
                if (['department_head', 'department_secretary'].includes(payload.archiveResponsibility) && !payload.departmentId) payload.departmentId = payload.archiveUnitIds[0] || null;
                if (this.originalUser) {
                    // Send only changed values so existing credentials and legacy relationships keep their BSON representation.
                    for (const key of Object.keys(payload)) {
                        const original = this.originalUser[key] ?? (Array.isArray(payload[key]) ? [] : key === 'archiveResponsibility' || key === 'departmentId' ? null : '');
                        if (JSON.stringify(payload[key]) === JSON.stringify(original)) delete payload[key];
                    }
                }
                if (_id) await axios.patch(`${endpoint}/${encodeURIComponent(_id)}`, payload);
                else await axios.post(endpoint, payload);
                this.$refs.userEditor.close();
                this.showSuccessPopup(_id ? 'User updated.' : 'User created. An account setup link has been sent.');
                await this.load(_id ? this.page : 1);
            } catch (error) {
                this.formError = message(error, 'The user could not be saved.'); this.fieldErrors = error.response?.data?.errors || {};
                this.$nextTick(() => this.$refs.userEditor.querySelector('[role="alert"]')?.focus());
            } finally { this.saving = false; }
        },
        toggleUserMenu(row, trigger) {
            if (this.menuTarget?._id === row._id && this.$refs.userMenu.matches(':popover-open')) { this.closeUserMenu(); return; }
            this.$refs.userMenu.hidePopover(); this.menuTarget = row; this.menuTrigger = trigger;
            const rect = trigger.getBoundingClientRect();
            this.$refs.userMenu.style.left = `${Math.max(8, Math.min(rect.right - 210, innerWidth - 218))}px`;
            this.$refs.userMenu.style.top = `${Math.max(8, Math.min(rect.bottom + 4, innerHeight - 210))}px`;
            this.$refs.userMenu.showPopover();
            this.$nextTick(() => this.$refs.userMenu.querySelector('button')?.focus());
        },
        closeUserMenu() { this.$refs.userMenu?.hidePopover(); this.menuTarget = null; this.menuTrigger?.focus(); },
        menuKey(event) {
            const items = [...this.$refs.userMenu.querySelectorAll('button')].filter(button => !button.disabled && button.getClientRects().length);
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault(); const current = items.indexOf(document.activeElement);
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items[next]?.focus();
            }
        },
        async viewUser(row) {
            this.closeUserMenu(); const number = ++this.detailRequest;
            this.details = null; this.detailError = ''; this.detailLoading = true;
            this.$refs.userDetails.showModal();
            try { const { data } = await axios.get(`${endpoint}/${encodeURIComponent(row._id)}`); if (number === this.detailRequest) this.details = data.data; }
            catch (error) { if (number === this.detailRequest) this.detailError = message(error, 'User details could not be loaded.'); }
            finally { if (number === this.detailRequest) this.detailLoading = false; }
        },
        requestUserAction(action, row) {
            this.closeUserMenu(); this.action = action; this.actionTarget = row; this.actionError = '';
            this.$refs.userAction.showModal();
        },
        async confirmUserAction() {
            if (this.saving || !this.actionTarget) return;
            this.saving = true; this.actionError = '';
            try {
                const url = `${endpoint}/${encodeURIComponent(this.actionTarget._id)}`;
                if (this.action === 'deactivate') await axios.delete(url);
                else await axios.post(`${url}/${this.action === 'reset' ? 'reset-password' : 'reactivate'}`);
                this.$refs.userAction.close(); this.showSuccessPopup(this.action === 'reset' ? 'Password reset link sent.' : 'Account status updated.');
                await this.load();
            } catch (error) { this.actionError = message(error, 'This action could not be completed.'); }
            finally { this.saving = false; }
        },
        userDate(value) { return value ? new Date(value).toLocaleString() : 'Not recorded'; },
    };
}
