import { clearComparisonPane, renderComparisonPane } from './archive-comparison';
import { openDocumentEditor, closeDocumentEditor } from './document-editor';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('qmsArchives', () => ({
        tree: { colleges: [], units: [], categories: [] }, documents: [], meta: {}, loading: true, saving: false,
        collegeId: '', unitId: '', categoryId: '', search: '', recordType: '', page: 1, error: '', notice: '',
        detail: null, versions: [], activity: [], form: {}, previous: null, accessOptions: { accounts: [], audits: [] }, auditId: '', navExpanded: false,
        organization: null, selectedUser: '', responsibility: 'faculty_staff', cluster: '', scopeUnits: [], scopeColleges: [],
        requestNumber: 0, initialLoading: true,
        navigationHistory: [{ college: '', unit: '', category: '' }], navigationIndex: 0,
        compactPath: false, stopBreadcrumbs: null,
        currentView: 'my', layout: 'list', sort: 'newest', selectedId: '', actionId: '', uploadProgress: 0,
        newMenu: false, folderMode: 'create', folderName: '', folderTarget: null, folderParentId: '', folderError: '', folderSaving: false,
        archivePath: {}, archiveFolders: [], archiveBreadcrumbs: [], archiveLeaf: false, archiveNotice: '',
        documentUrl(document) {
            const url = `/api/v1/documents/${document._id}/download`;
            return this.currentView === 'audit' && this.archiveLeaf ? `${url}?${new URLSearchParams(this.archivePath)}` : url;
        },
        navigateCrumb(crumb) { return crumb.auditPath !== undefined ? this.navigateAudit(crumb.auditPath) : this.navigate(crumb.college, crumb.unit, crumb.category); },
        navigateAudit(path = {}) {
            this.newMenu = false;
            this.navigationHistory = this.navigationHistory.slice(0, this.navigationIndex + 1);
            this.navigationHistory.push({college:path.collegeId || '',unit:path.unitId || '',category:'',auditPath:{...path}});
            this.navigationIndex = this.navigationHistory.length - 1;
            return this.openLocation(this.navigationHistory[this.navigationIndex]);
        },
        get currentFolderPermissions() {
            if (this.currentView !== 'my' || !this.unit) return {};
            return this.category ? (this.category.folderPermissions?.[this.unitId] || {upload: this.unit.canCreate}) : (this.unit.folderPermissions || {upload:this.unit.canCreate});
        },
        get canNew() { return this.currentFolderPermissions.create || this.currentFolderPermissions.upload; },
        folderPermission(folder, action) { return !!folder.folderPermissions?.[this.unitId]?.[action]; },
        get moveParents() {
            return this.tree.categories.filter(folder => folder._id !== this.folderTarget?._id && folder.collegeId === this.collegeId
                && (!folder.departmentId || folder.departmentId === this.unitId) && folder.unitType === this.unit?.unitType && this.folderPermission(folder, 'create'));
        },
        async openFolderDialog(mode = 'create', folder = null) {
            this.newMenu = false; this.folderMode = mode; this.folderTarget = folder;
            this.folderName = folder?.name || ''; this.folderParentId = folder?.parentId || this.categoryId || ''; this.folderError = '';
            this.$refs.folderDialog.showModal(); await this.$nextTick();
            (mode === 'move' ? this.$refs.folderParent : this.$refs.folderName).focus();
        },
        async saveFolder() {
            this.folderSaving = true; this.folderError = '';
            try {
                const data = {unitId:this.unitId, name:this.folderName, parentId:this.folderMode === 'move' ? this.folderParentId || null : this.categoryId || null};
                if (this.folderMode === 'create') await window.axios.post('/api/v1/documents/folders', data);
                else await window.axios.patch(`/api/v1/documents/folders/${this.folderTarget._id}`, {...data,operation:this.folderMode});
                this.$refs.folderDialog.close(); await this.refreshTree(); await this.load(); this.notice = 'Folder saved.';
            } catch (error) { this.folderError = this.message(error); await this.refreshTree(); }
            finally { this.folderSaving = false; }
        },
        async deleteFolder(folder) {
            if (!window.confirm(`Delete the empty folder “${folder.name}”? Folders containing records or subfolders cannot be deleted.`)) return;
            try {
                await window.axios.delete(`/api/v1/documents/folders/${folder._id}`, {data:{unitId:this.unitId}});
                await this.refreshTree(); this.notice = 'Empty folder removed.';
            } catch (error) { this.error = this.message(error); }
        },
        views: [
            {id:'my', name:'My Documents', icon:'M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8 M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'},
            {id:'shared', name:'Shared with Me', icon:'M2 9V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1 M2 13h10 m-3 3 3-3-3-3'},
            {id:'audit', name:'Audit Evidence', icon:'M9 2h6a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2 m1 10 2 2 4-4'},
            {id:'recent', name:'Recent', icon:'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0 M12 6v6l4 2'},
            {id:'starred', name:'Starred', icon:'M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z'},
        ],
        get viewName() { return this.views.find(view => view.id === this.currentView)?.name || 'My Documents'; },
        setLayout(value) {
            this.layout = value;
            try { localStorage.setItem('complitrack.qms.layout', value); } catch { /* Storage may be disabled. */ }
        },
        switchView(value) {
            this.currentView = value; this.selectedId = ''; this.actionId = '';
            this.archivePath = {}; this.archiveFolders = []; this.archiveBreadcrumbs = []; this.archiveLeaf = false; this.archiveNotice = '';
            this.collegeId = ''; this.unitId = ''; this.categoryId = ''; this.search = ''; this.recordType = '';
            this.navigationHistory = [{college:'', unit:'', category:''}]; this.navigationIndex = 0;
            return this.load(true);
        },
        async toggleStar(document) {
            try {
                const {data} = await window.axios.patch(`/api/v1/documents/${document._id}/star`, {starred: !document.isStarred});
                document.isStarred = data.isStarred;
                if (this.currentView === 'starred') await this.load();
            } catch (error) { this.error = this.message(error); }
        },
        fileType(document) {
            if (document.recordType !== 'file') return document.recordType === 'physical-original' ? 'Original' : 'Reference';
            return document.originalName?.split('.').pop()?.toUpperCase() || 'File';
        },
        moveSelection(event) {
            if (!['ArrowDown', 'ArrowUp', 'ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key) || event.target.closest('button,a,input,select,summary')) return;
            const cards = [...this.$refs.fileBrowser.querySelectorAll('.qms-document')];
            if (!cards.length) return;
            event.preventDefault();
            const current = cards.indexOf(event.target.closest('.qms-document'));
            const index = event.key === 'Home' ? 0 : event.key === 'End' ? cards.length - 1 : Math.max(0, Math.min(cards.length - 1, current + (['ArrowDown','ArrowRight'].includes(event.key) ? 1 : -1)));
            this.selectedId = this.documents[index]._id; cards[index].focus();
        },
        comparisonIds: ['', ''], comparisonVersions: [],
        editorMessage: '', editorSession: null, editorPollTimer: null, editorSavedCount: 0, editorPollUntil: 0,
        editorTitle: '', editorLocation: '', editorMode: 'view', editorSaving: false,
        async saveEditor() {
            if (this.editorMode !== 'edit' || !this.editorSession || this.editorSaving) return;
            this.editorSaving = true;
            this.editorMessage = 'Requesting save…';
            try {
                const { data } = await window.axios.post(`/api/v1/document-editor/${this.editorSession}/save`);
                this.editorMessage = data.message;
            } catch (error) { this.editorMessage = this.message(error); }
            finally { this.editorSaving = false; }
        },
        canEditDocument() { return this.detail?.canEdit === true; },
        async openInEditor(document, mode = 'view') {
            if (await this.inspect(document, false)) return this.editDocument(mode);
        },
        async editDocument(mode = 'edit') {
            this.error = ''; this.editorMessage = 'Connecting to the document server…';
            try {
                const { data } = await window.axios.post(`/api/v1/documents/${this.detail._id}/editor`, {mode}, {params:this.currentView === 'audit' ? this.archivePath : {}});
                clearTimeout(this.editorPollTimer);
                this.editorSession = data.sessionId; this.editorSavedCount = 0;
                this.editorTitle = this.detail.originalName || this.detail.title;
                this.editorLocation = this.breadcrumbs.map(crumb=>crumb.name).join(' / ') + ' · ' + this.detail.departmentName;
                this.editorMode = mode;
                this.$refs.editor.showModal();
                await this.$nextTick();
                await openDocumentEditor(this.$refs.editorBody, data, text => { this.editorMessage = text; });
                this.pollEditor();
            } catch (error) { this.error = error.response ? this.message(error) : (error.message || this.message(error)); this.editorMessage = this.error; }
        },
        async pollEditor() {
            const session = this.editorSession;
            if (!session) return;
            try {
                const { data } = await window.axios.get(`/api/v1/document-editor/${session}/status`);
                if (session !== this.editorSession) return;
                if (data.savedVersions.length > this.editorSavedCount) {
                    this.editorSavedCount = data.savedVersions.length;
                    this.editorMessage = `Version ${data.savedVersions.at(-1).versionNumber} saved. Earlier versions are available for comparison.`;
                    await this.inspect({ _id: data.currentId }, false);
                    await this.load();
                }
                if (data.lastError) this.editorMessage = data.lastError;
            } catch { this.editorMessage = 'Save status is unavailable. Check version history before closing or retrying.'; }
            if (session === this.editorSession && (this.$refs.editor.open || Date.now() < this.editorPollUntil)) {
                this.editorPollTimer = setTimeout(() => this.pollEditor(), 3000);
            }
        },
        closeEditor() {
            closeDocumentEditor(this.$refs.editorBody);
            this.editorPollUntil = Date.now() + 90000;
            clearTimeout(this.editorPollTimer);
            this.pollEditor();
            this.notice = this.editorMode === 'view' ? 'Read-only preview closed.' : 'The editor is closing. Saved revisions will appear in version history when processing finishes.';
        },
        get comparableVersions() { return this.versions.filter(version => version.canDownload); },
        async compareVersions() {
            this.comparisonVersions = [...this.comparableVersions];
            if (this.comparisonVersions.length < 2) return;
            this.comparisonIds = [this.comparisonVersions[1]._id, this.comparisonVersions[0]._id];
            this.$refs.comparison.showModal();
            await this.$nextTick();
            this.loadComparison(0); this.loadComparison(1);
        },
        loadComparison(index) {
            const version = this.comparisonVersions.find(version => version._id === this.comparisonIds[index]);
            return renderComparisonPane(index === 0 ? this.$refs.compareLeft : this.$refs.compareRight, version);
        },
        clearComparison() {
            clearComparisonPane(this.$refs.compareLeft); clearComparisonPane(this.$refs.compareRight);
            this.comparisonIds = ['', '']; this.comparisonVersions = [];
        },
        destroy() { this.stopBreadcrumbs?.(); this.clearComparison(); clearTimeout(this.editorPollTimer); this.editorSession = null; closeDocumentEditor(this.$refs.editorBody); },
        async init() {
            try { const saved = localStorage.getItem('complitrack.qms.layout'); if (['grid','list'].includes(saved)) this.layout = saved; } catch { /* Use the default layout. */ }
            await this.refreshTree(); this.currentView = this.tree.preferredView || this.currentView; await this.load();
            this.initialLoading = false;
            await this.$nextTick();
            const observer = new ResizeObserver(() => {
                if (!this.$refs.breadcrumbNav.clientWidth) return;
                this.compactPath = this.$refs.breadcrumbMeasure.getBoundingClientRect().width > this.$refs.breadcrumbNav.clientWidth - 28;
            });
            observer.observe(this.$refs.breadcrumbNav);
            observer.observe(this.$refs.breadcrumbMeasure);
            this.stopBreadcrumbs = () => observer.disconnect();
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
            try { this.tree = (await window.axios.get('/api/v1/documents/tree')).data; }
            catch (error) { this.error = this.message(error); }
        },
        async load(reset = false, requestedPage = this.page) {
            if (this.tree.canViewDocuments === false && this.currentView !== 'audit') {
                this.documents = []; this.meta = { total: 0, lastPage: 1 }; this.page = 1; this.loading = false;
                return;
            }
            const page = reset ? 1 : Math.max(1, Number(requestedPage) || 1);
            const number = ++this.requestNumber;
            this.loading = true; this.error = '';
            try {
                if (this.currentView === 'audit') {
                    const {data} = await window.axios.get('/api/v1/documents/audit-folders', {params:this.archivePath});
                    if (number !== this.requestNumber) return;
                    this.archiveFolders = data.folders; this.archiveBreadcrumbs = data.breadcrumbs;
                    this.archiveLeaf = data.leaf; this.archiveNotice = data.notice || '';
                    if (!this.archiveLeaf || this.tree.canViewDocuments === false) {
                        this.documents = []; this.meta = {total:0,lastPage:1,currentPage:1}; this.page=1;
                        return;
                    }
                }
                const params = { page, perPage: 12, search: this.search || undefined, unitId: this.unitId || undefined,
                    categoryId: this.categoryId || undefined, recordType: this.recordType || undefined, collegeId: this.collegeId || undefined,
                    view: this.currentView, sort: this.sort, ...(this.currentView === 'audit' ? this.archivePath : {}) };
                const response = await window.axios.get('/api/v1/documents', { params });
                if (number !== this.requestNumber) return;
                this.documents = response.data.data; this.meta = response.data.meta;
                this.page = Number(this.meta.currentPage) || page;
            } catch (error) { if (number === this.requestNumber) {
                this.error = this.message(error);
                if (this.currentView === 'audit') { this.documents=[];this.archiveFolders=[];this.archiveLeaf=false;this.meta={total:0,lastPage:1}; }
            } }
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
        get breadcrumbs() {
            if (this.currentView === 'audit') return (this.archiveBreadcrumbs.length ? this.archiveBreadcrumbs : [{key:'audit-root',name:'Audit Plans',path:{}}])
                .map(crumb => ({key:crumb.key,name:crumb.name,auditPath:crumb.path}));
            const trail = [{ key: 'campus', name: this.viewName, college: '', unit: '', category: '' }];
            if (this.college) trail.push({ key: 'college:' + this.collegeId, name: this.college.code || this.college.name, college: this.collegeId, unit: '', category: '' });
            if (this.unit) trail.push({ key: 'unit:' + this.unitId, name: this.unit.name, college: this.collegeId, unit: this.unitId, category: '' });
            return trail.concat(this.categoryTrail.map(crumb => ({ key: 'category:' + crumb._id, name: crumb.name, college: this.collegeId, unit: this.unitId, category: crumb._id })));
        },
        get visibleBreadcrumbs() { return this.compactPath ? this.breadcrumbs.slice(-1) : this.breadcrumbs; },
        get folders() { return this.tree.units.filter(unit => (!this.collegeId || unit.collegeId === this.collegeId) && (!this.search || unit.name.toLowerCase().includes(this.search.toLowerCase()))); },
        get categories() {
            if (!this.unit) return [];
            return this.tree.categories.filter(category => category.collegeId === this.unit.collegeId && category.unitType === this.unit.unitType
                && (!category.departmentId || category.departmentId === this.unitId)
                && category.parentId === (this.categoryId || null) && (!this.search || `${category.code} ${category.name}`.toLowerCase().includes(this.search.toLowerCase())));
        },
        get canCreate() { return this.tree.units.some(unit => unit.canCreate && (!this.unitId || unit._id === this.unitId)); },
        get formCategories() {
            const unit = this.tree.units.find(unit => unit._id === this.form.unitId);
            return this.tree.categories.filter(category => unit && category.collegeId === unit.collegeId && category.unitType === unit.unitType
                && (!category.departmentId || category.departmentId === unit._id) && (!category.folderPermissions || category.folderPermissions[unit._id]?.upload));
        },
        navigate(college = '', unit = '', category = '') {
            const fromAudit = this.currentView === 'audit';
            if (fromAudit) { this.currentView = 'my'; this.navigationHistory = [{college:'',unit:'',category:''}]; this.navigationIndex=0; }
            if (this.collegeId === college && this.unitId === unit && this.categoryId === category) return fromAudit ? this.load(true) : undefined;
            this.navigationHistory = this.navigationHistory.slice(0, this.navigationIndex + 1);
            this.navigationHistory.push({ college, unit, category });
            this.navigationIndex = this.navigationHistory.length - 1;
            return this.openLocation({ college, unit, category });
        },
        openLocation({ college, unit, category, auditPath }) {
            this.collegeId = college; this.unitId = unit; this.categoryId = category;
            if (this.currentView === 'audit') this.archivePath = {...(auditPath || {})};
            return this.load(true);
        },
        navigateHistory(direction) {
            const index = this.navigationIndex + direction;
            if (index < 0 || index >= this.navigationHistory.length) return;
            this.navigationIndex = index;
            return this.openLocation(this.navigationHistory[index]);
        },
        navigateUp() {
            if (this.currentView === 'audit') {
                const path={...this.archivePath}; delete path[['planId','auditorId','campusId','collegeId','unitId'].filter(key=>path[key]).at(-1)];
                return this.navigateAudit(path);
            }
            if (this.categoryId) return this.navigate(this.collegeId, this.unitId, this.category?.parentId || '');
            if (this.unitId) return this.navigate(this.collegeId);
            if (this.collegeId) return this.navigate();
        },
        async refreshLocation() { await this.refreshTree(); return this.load(); },
        date(value) { return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'Not recorded'; },
        type(value) { return ({file: 'Digital file', 'digital-reference': 'Document reference', 'physical-original': 'Physical original'})[value] || value; },
        label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()); },
        async inspect(document, open = true) {
            this.error = ''; this.notice = '';
            try {
                const response = (await window.axios.get(`/api/v1/documents/${document._id}`, {params:this.currentView === 'audit' ? this.archivePath : {}})).data;
                this.detail = response.data; this.versions = response.versions; this.activity = response.activity;
                this.accessOptions = response.accessOptions || { accounts: [], audits: [] }; this.auditId = '';
                if (open && !this.$refs.details.open) this.$refs.details.showModal();
                return true;
            } catch (error) { this.error = this.message(error); return false; }
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
            this.saving = true; this.error = ''; this.uploadProgress = 0;
            try {
                const data = new FormData();
                Object.entries(this.form).forEach(([key, value]) => data.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value || ''));
                data.append('purpose', 'qms');
                const file = this.$refs.file.files[0]; if (file && this.form.recordType === 'file') data.append('file', file);
                const url = this.previous ? `/api/v1/documents/${this.previous._id}/versions` : '/api/v1/documents/upload';
                const result = await window.axios.post(url, data, { onUploadProgress: event => {
                    if (event.total) this.uploadProgress = Math.min(100, Math.round(event.loaded / event.total * 100));
                } });
                this.$refs.upload.close(); await this.refreshTree(); await this.load();
                if (this.$refs.details.open) await this.inspect(result.data.data, false);
                this.notice = this.previous ? 'New version saved. Earlier versions are preserved.' : 'Archive record saved.';
            } catch (error) { this.error = this.message(error); }
            finally { this.saving = false; }
        },
        async permissions() {
            this.saving = true;
            try {
                await window.axios.patch(`/api/v1/documents/${this.detail._id}/permissions`, { visibility: this.detail.visibility || 'owner',
                    downloadAllowed: !!this.detail.downloadAllowed, accessUserIds: this.detail.accessUserIds || [] });
                this.notice = 'Document permissions updated.'; await this.refreshTree(); await this.load();
            } catch (error) { this.error = this.message(error); } finally { this.saving = false; }
        },
        async share(revoke = '') {
            this.saving = true;
            try {
                const base = `/api/v1/documents/${this.detail._id}/share`;
                const response = revoke ? await window.axios.delete(`${base}/${revoke}`) : await window.axios.post(base, { auditId: this.auditId });
                await this.inspect(this.detail, false); this.notice = response.data.message;
            } catch (error) { this.error = this.message(error); } finally { this.saving = false; }
        },
        async manageOrganization() {
            try { this.organization = (await window.axios.get('/api/v1/documents/organization')).data; this.$refs.organization.showModal(); }
            catch (error) { this.error = this.message(error); }
        },
        selectUser() {
            const user = this.organization.users.find(user => user._id === this.selectedUser);
            this.responsibility = user?.responsibility || 'faculty_staff'; this.scopeUnits = user?.unitIds || []; this.scopeColleges = user?.collegeIds || [];
            this.cluster = user?.cluster || '';
        },
        async assign() {
            this.saving = true;
            try {
                const collegeRole = ['college_dean', 'college_secretary'].includes(this.responsibility);
                const clusterRole = this.responsibility === 'cluster_administrator';
                await window.axios.patch(`/api/v1/documents/organization/${this.selectedUser}`, { responsibility: this.responsibility,
                    cluster: clusterRole ? this.cluster : null,
                    unitIds: collegeRole || clusterRole ? [] : this.scopeUnits, collegeIds: collegeRole ? this.scopeColleges : [] });
                this.$refs.organization.close(); this.notice = 'Position updated.'; await this.refreshTree(); await this.load();
            } catch (error) { this.error = this.message(error); } finally { this.saving = false; }
        },
        async configureCategory(category) {
            const name = window.prompt('Category name (provisional organization)', category.name);
            if (!name?.trim()) return;
            try { await window.axios.patch(`/api/v1/documents/categories/${category._id}`, { name: name.trim() }); await this.refreshTree(); }
            catch (error) { this.error = this.message(error); }
        },
    }));
});
