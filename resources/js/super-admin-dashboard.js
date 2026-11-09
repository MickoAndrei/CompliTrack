export function formatArchiveBytes(value) {
    const bytes = Math.max(0, Number(value) || 0);
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = bytes ? Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), 4) : 0;
    return `${new Intl.NumberFormat(undefined, { maximumFractionDigits: index ? 2 : 0 }).format(bytes / (1024 ** index))} ${units[index]}`;
}
document.addEventListener('alpine:init', () => {
    window.Alpine.data('superAdminDashboard', () => ({
        loading: true, error: '', data: null, health: null, healthError: '', healthLoading: false, healthRefreshTimer: null,
        init() {
            this.load(); this.loadHealth();
            this.healthRefreshTimer = window.setInterval(() => this.loadHealth(), 60000);
        },
        destroy() { window.clearInterval(this.healthRefreshTimer); },
        async load() {
            this.loading = true; this.error = '';
            try { this.data = (await window.axios.get('/api/v1/system/dashboard')).data.data; }
            catch { this.error = 'Dashboard records could not be loaded. Retry to get current data.'; }
            finally { this.loading = false; this.$nextTick(() => window.renderIcons?.()); }
        },
        async loadHealth() {
            if (this.healthLoading) return;
            this.healthLoading = true; this.healthError = ''; this.health = null;
            try { this.health = (await window.axios.get('/api/v1/system/dashboard/health')).data.data; }
            catch { this.healthError = 'Health checks could not be completed.'; }
            finally { this.healthLoading = false; }
        },
        bytes: formatArchiveBytes,
        trendMax() { return Math.max(4, Math.ceil(Math.max(0, ...(this.data?.userGrowth || []).map(month => Number(month.count) || 0)) / 4) * 4); },
        trendX(index) { const count = this.data?.userGrowth?.length || 0; return count < 2 ? 300 : 12 + index * 576 / (count - 1); },
        trendY(value) { return 188 - Math.max(0, Number(value) || 0) / this.trendMax() * 176; },
        trendPoints() { return (this.data?.userGrowth || []).map((month, index) => `${this.trendX(index)},${this.trendY(month.count)}`).join(' '); },
        date(value) { return value ? new Date(value).toLocaleString() : 'No recorded date'; },
        status(value) { return ({ draft: 'Scheduled / draft', ready: 'In progress', submitted: 'Awaiting ATL review', returned: 'Returned for revision', accepted: 'Completed', open: 'Open', in_progress: 'In progress' })[value] || value || 'Unknown'; },
    }));
    window.Alpine.data('superAdminOrganization', () => ({
        loading: true, error: '', tree: { units: [], colleges: [] }, users: [],
        async init() {
            try {
                const [tree, organization] = await Promise.all([window.axios.get('/api/v1/archives/tree'), window.axios.get('/api/v1/archives/organization')]);
                this.tree = tree.data; this.users = organization.data.users;
            } catch { this.error = 'Organizational responsibilities could not be loaded.'; }
            finally { this.loading = false; }
        },
        names(responsibility, id, college = false) {
            return this.users.filter(user => user.responsibility === responsibility && (college ? user.collegeIds : (user.unitIds.length ? user.unitIds : [user.departmentId])).includes(id)).map(user => user.name).join(', ') || 'Not assigned';
        },
    }));
});
