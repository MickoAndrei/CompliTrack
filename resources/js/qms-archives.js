document.addEventListener('alpine:init', () => {
    window.Alpine.data('qmsArchives', () => ({
        tree: { colleges: [], units: [], categories: [] }, documents: [], meta: {}, loading: true, saving: false,
        collegeId: '', unitId: '', categoryId: '', search: '', recordType: '', page: 1, error: '', notice: '',
        detail: null, versions: [], activity: [], form: {}, previous: null, accessOptions: { accounts: [], audits: [] }, auditId: '', navExpanded: false,
        organization: null, selectedUser: '', responsibility: 'faculty_staff', scopeUnits: [], scopeColleges: [],
        requestNumber: 0, initialLoading: true,
        async init() {
            await this.refreshTree(); await this.load();
            this.initialLoading = false;
            if (this.tree.canManageOrganization && new URLSearchParams(window.location.search).get('manage') === 'responsibilities') {
                await this.manageOrganization();
            }
        },
        message(error) { return Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || 'The request could not be completed. Please try again.'; },
        storageSize(value) {
            const bytes = Math.max(0, Number(value) || 0);
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            return `${(bytes / (1024 ** index)).toLocaleString(undefined, { maximumFractionDigits: index ? 2 : 0 })} ${units[index]}`;
        },
        async refreshTree() {
            try { this.tree = (await window.axios.get('/api/v1/archives/tree')).data; }
            catch (error) { this.error = this.message(error); }
        },
        async load(reset = false, requestedPage = this.page) {
            if (this.tree.canViewDocuments === false) {
                this.documents = []; this.meta = { total: 0, lastPage: 1 }; this.page = 1; this.loading = false;
                return;
            }
            const page = reset ? 1 : Math.max(1, Number(requestedPage) || 1);
            const number = ++this.requestNumber;
            this.loading = true; this.error = '';
            try {
                const params = { page, perPage: 12, search: this.search || undefined, unitId: this.unitId || undefined,
                    categoryId: this.categoryId || undefined, recordType: this.recordType || undefined, collegeId: this.collegeId || undefined };
                const response = await window.axios.get('/api/v1/archives', { params });
                if (number !== this.requestNumber) return;
                this.documents = response.data.data; this.meta = response.data.meta;
                this.page = Number(this.meta.currentPage) || page;
            } catch (error) { if (number === this.requestNumber) this.error = this.message(error); }
            finally { if (number === this.requestNumber) this.loading = false; }
        },
        goToPage(page) {
            if (this.loading || page < 1 || page > (Number(this.meta.lastPage) || 1)) return;
            return this.load(false, page);
        },
        get unit() { return this.tree.units.find(unit => unit._id === this.unitId); },
        get college() { return this.tree.colleges.find(college => college._id === this.collegeId); },
        get category() { return this.tree.categories.find(category => category._id === this.categoryId); },
        get categoryTrail() {
            const trail = []; let category = this.category;
            while (category && !trail.some(item => item._id === category._id)) {
                trail.unshift(category); category = this.tree.categories.find(item => item._id === category.parentId);
            }
            return trail;
        },
        get folders() { return this.tree.units.filter(unit => !this.collegeId || unit.collegeId === this.collegeId); },
        get categories() {
            if (!this.unit) return [];
            return this.tree.categories.filter(category => category.collegeId === this.unit.collegeId && category.unitType === this.unit.unitType
                && category.parentId === (this.categoryId || null));
        },
        get canCreate() { return this.tree.units.some(unit => unit.canCreate && (!this.unitId || unit._id === this.unitId)); },
        get formCategories() {
            const unit = this.tree.units.find(unit => unit._id === this.form.unitId);
            return this.tree.categories.filter(category => unit && category.collegeId === unit.collegeId && category.unitType === unit.unitType);
        },
        navigate(college = '', unit = '', category = '') { this.collegeId = college; this.unitId = unit; this.categoryId = category; this.load(true); },
        date(value) { return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'Not recorded'; },
        type(value) { return ({file: 'Digital file', 'digital-reference': 'Document reference', 'physical-original': 'Physical original'})[value] || value; },
        label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()); },
        async inspect(document, open = true) {
            this.error = ''; this.notice = '';
            try {
                const response = (await window.axios.get(`/api/v1/archives/${document._id}`)).data;
                this.detail = response.data; this.versions = response.versions; this.activity = response.activity;
                this.accessOptions = response.accessOptions || { accounts: [], audits: [] }; this.auditId = '';
                if (open && !this.$refs.details.open) this.$refs.details.showModal();
            } catch (error) { this.error = this.message(error); }
        },
        create(previous = null) {
            this.previous = previous; this.notice = ''; this.error = '';
            this.form = { unitId: previous?.departmentId || this.unitId || this.tree.units.find(unit => unit.canCreate)?._id || '',
                categoryId: previous?.categoryId || this.categoryId || '', title: previous?.title || '', description: previous?.description || '',
                recordType: previous?.recordType || 'file', documentReference: previous?.documentReference || '', physicalLocation: previous?.physicalLocation || '',
                visibility: previous?.visibility || 'owner', downloadAllowed: previous?.downloadAllowed ?? true, copyAuthorized: false };
            this.$refs.uploadForm.reset(); this.$refs.upload.showModal();
        },
        async save() {
            this.saving = true; this.error = '';
            try {
                const data = new FormData();
                Object.entries(this.form).forEach(([key, value]) => data.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value || ''));
                data.append('purpose', 'qms');
                const file = this.$refs.file.files[0]; if (file && this.form.recordType === 'file') data.append('file', file);
                const url = this.previous ? `/api/v1/archives/${this.previous._id}/versions` : '/api/v1/archives/upload';
                const result = await window.axios.post(url, data);
                this.$refs.upload.close(); await this.refreshTree(); await this.load();
                if (this.$refs.details.open) await this.inspect(result.data.data, false);
                this.notice = this.previous ? 'New version saved. Earlier versions are preserved.' : 'Archive record saved.';
            } catch (error) { this.error = this.message(error); }
            finally { this.saving = false; }
        },
        async permissions() {
            this.saving = true;
            try {
                await window.axios.patch(`/api/v1/archives/${this.detail._id}/permissions`, { visibility: this.detail.visibility || 'owner',
                    downloadAllowed: !!this.detail.downloadAllowed, accessUserIds: this.detail.accessUserIds || [] });
                this.notice = 'Document permissions updated.'; await this.refreshTree(); await this.load();
            } catch (error) { this.error = this.message(error); } finally { this.saving = false; }
        },
        async share(revoke = '') {
            this.saving = true;
            try {
                const base = `/api/v1/archives/${this.detail._id}/share`;
                const response = revoke ? await window.axios.delete(`${base}/${revoke}`) : await window.axios.post(base, { auditId: this.auditId });
                await this.inspect(this.detail, false); this.notice = response.data.message;
            } catch (error) { this.error = this.message(error); } finally { this.saving = false; }
        },
        async manageOrganization() {
            try { this.organization = (await window.axios.get('/api/v1/archives/organization')).data; this.$refs.organization.showModal(); }
            catch (error) { this.error = this.message(error); }
        },
        selectUser() {
            const user = this.organization.users.find(user => user._id === this.selectedUser);
            this.responsibility = user?.responsibility || 'faculty_staff'; this.scopeUnits = user?.unitIds || []; this.scopeColleges = user?.collegeIds || [];
        },
        async assign() {
            this.saving = true;
            try {
                const collegeRole = ['college_dean', 'college_secretary', 'cluster_administrator'].includes(this.responsibility);
                await window.axios.patch(`/api/v1/archives/organization/${this.selectedUser}`, { responsibility: this.responsibility,
                    unitIds: collegeRole ? [] : this.scopeUnits, collegeIds: collegeRole ? this.scopeColleges : [] });
                this.$refs.organization.close(); this.notice = 'Archive responsibility updated.'; await this.refreshTree(); await this.load();
            } catch (error) { this.error = this.message(error); } finally { this.saving = false; }
        },
        async configureCategory(category) {
            const name = window.prompt('Category name (provisional organization)', category.name);
            if (!name?.trim()) return;
            try { await window.axios.patch(`/api/v1/archives/categories/${category._id}`, { name: name.trim() }); await this.refreshTree(); }
            catch (error) { this.error = this.message(error); }
        },
    }));
});
