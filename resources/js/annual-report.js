import { portalDate } from './portal-date';
document.addEventListener('alpine:init', () => {
    Alpine.data('annualReport', () => ({
        year: new Date().getFullYear(), planId: '', report: null, loading: true, error: '', request: 0, loadedFilter: '',
        init() { this.load(); },
        params() { return { annual: 1, year: this.year, ...(this.planId ? { planId: this.planId } : {}) }; },
        get csvUrl() { return '/api/v1/reports?' + new URLSearchParams({ ...this.params(), format: 'csv' }); },
        get current() { return this.loadedFilter === JSON.stringify(this.params()); },
        date: portalDate,
        async load() {
            const request = ++this.request, params = this.params(); this.loading = true; this.error = '';
            try {
                const { data } = await window.axios.get('/api/v1/reports', { params });
                if (request !== this.request) return;
                this.report = data.data; this.loadedFilter = JSON.stringify(params);
            } catch (error) { if (request === this.request) this.error = error.response?.data?.message || 'Unable to load report. Retry.'; }
            finally { if (request === this.request) this.loading = false; }
        },
        destroy() { this.request++; },
    }));
});
