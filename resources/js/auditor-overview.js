document.addEventListener('alpine:init', () => {
    window.Alpine.data('auditorOverview', () => ({
        loading: true, error: '', stats: {}, changes: [], assignments: [],
        get classifiedTotal() { return (this.stats.compliantChecks || 0) + (this.stats.nonCompliantChecks || 0); },
        get compliantPercent() { return this.classifiedTotal ? this.stats.compliantChecks / this.classifiedTotal * 100 : 0; },
        async load() {
            this.loading = true; this.error = '';
            try {
                const { data } = await window.axios.get('/api/v1/auditor/metrics');
                this.stats = data.data;
                this.changes = data.changes || [];
                this.assignments = data.assignments || [];
            } catch { this.error = 'Unable to load your audit overview. Please try again.'; }
            finally { this.loading = false; this.$nextTick(() => window.renderIcons?.()); }
        },
    }));
});
