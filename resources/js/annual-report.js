import { portalDate } from './portal-date';
document.addEventListener('alpine:init', () => {
    Alpine.data('annualReport', () => ({
        year: new Date().getFullYear(), planId: '', report: null, loading: true, error: '', request: 0, loadedFilter: '', tab: 'overview',
        init() { this.load(); },
        params() { return { annual: 1, year: this.year, ...(this.planId ? { planId: this.planId } : {}) }; },
        get csvUrl() { return '/api/v1/reports?' + new URLSearchParams({ ...this.params(), format: 'csv' }); },
        get current() { return this.loadedFilter === JSON.stringify(this.params()); },
        get validYear() { return /^\d{4}$/.test(String(this.year)) && Number(this.year) >= 2000 && Number(this.year) <= 2200; },
        get totals() {
            const keys = ['planned','assigned','inProgress','submitted','returned','accepted','C','N','O','openCars','overdueCars','awaitingVerificationCars','closedCars'];
            return Object.fromEntries(keys.map(key => [key, (this.report?.rows || []).reduce((sum, row) => sum + (Number(row[key]) || 0), 0)]));
        },
        get rate() { const {C,N} = this.totals; return C+N ? (100*C/(C+N)).toFixed(1)+'%' : 'Not yet assessed'; },
        get completion() { const {accepted,assigned} = this.totals; return assigned ? (100*accepted/assigned).toFixed(1) : '0'; },
        get assessed() { const {C,N,O} = this.totals; return C+N+O; },
        get ncoStyle() { const {C,N,O} = this.totals; const t=C+N+O; return t ? `conic-gradient(#dc2626 0% ${N/t*100}%, #16a34a ${N/t*100}% ${(N+C)/t*100}%, #d97706 ${(N+C)/t*100}% 100%)` : '#e2e8f0'; },
        changeYear() { this.planId = ''; this.load(); },
        clearFilters() { this.year = new Date().getFullYear(); this.planId = ''; this.load(); },
        date: portalDate,
        async load() {
            const request = ++this.request;
            if (!this.validYear) { this.loading = false; this.error = 'Enter an audit year between 2000 and 2200.'; return; }
            const params = this.params(); this.loading = true; this.error = '';
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