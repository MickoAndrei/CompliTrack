document.addEventListener('alpine:init', () => {
    window.Alpine.data('correctiveActions', () => ({
        rows: [], summary: null, departments: [], meta: { total: 0, lastPage: 1 }, page: 1,
        search: '', departmentId: '', status: '', loading: true, error: '', notice: '',
        detail: null, detailLoading: false, detailError: '', remarks: '', saving: false,
        listRequest: 0, detailRequest: 0, disposed: false,
        init() { this.load(1); },
        destroy() { this.disposed = true; this.listRequest++; this.detailRequest++; },
        message(error) { return Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || 'The request could not be completed. Please retry.'; },
        async load(page = this.page) {
            const request = ++this.listRequest;
            this.loading = true; this.error = '';
            try {
                const { data } = await window.axios.get('/api/v1/compliance-checks', { params: {
                    correctiveActions: 1, page, perPage: 10, search: this.search, departmentId: this.departmentId, status: this.status,
                } });
                if (this.disposed || request !== this.listRequest) return;
                this.rows = data.data; this.summary = data.summary; this.departments = data.departments;
                this.meta = data.meta; this.page = data.meta.currentPage;
            } catch (error) { if (!this.disposed && request === this.listRequest) this.error = this.message(error); }
            finally {
                if (!this.disposed && request === this.listRequest) { this.loading = false; this.$nextTick(() => window.renderIcons?.()); }
            }
        },
        clearFilters() { this.search = ''; this.departmentId = ''; this.status = ''; return this.load(1); },
        async openDetails(row) {
            this.detail = null; this.remarks = ''; this.detailError = ''; this.detailLoading = true;
            const request = ++this.detailRequest;
            if (!this.$refs.details.open) this.$refs.details.showModal();
            try {
                const { data } = await window.axios.get(`/api/v1/compliance-checks/${row._id}`, { params: { correctiveActions: 1 } });
                if (!this.disposed && request === this.detailRequest) this.detail = data.data;
            } catch (error) { if (!this.disposed && request === this.detailRequest) this.detailError = this.message(error); }
            finally { if (!this.disposed && request === this.detailRequest) this.detailLoading = false; }
        },
        closeDetails() { this.detailRequest++; this.detail = null; this.detailLoading = false; this.detailError = ''; this.remarks = ''; },
        async submitTransition() {
            if (this.saving || !this.detail?.allowedTransitions?.length) return;
            const id = this.detail._id, next = this.detail.allowedTransitions[0];
            if (next !== 'in_progress' && !this.remarks.trim()) { this.detailError = 'Record verification or reopening notes before continuing.'; return; }
            this.saving = true; this.detailError = '';
            try {
                await window.axios.patch(`/api/v1/compliance-checks/${id}/status`, { correctiveStatus: next, remarks: this.remarks.trim() || null });
                this.notice = 'Corrective action updated. History and notifications have been retained.';
                await this.openDetails({ _id: id });
                await this.load(this.page);
            } catch (error) { this.detailError = this.message(error); }
            finally { this.saving = false; }
        },
        actionLabel(status) { return ({ in_progress: 'Start corrective action', closed: 'Verify and close CAR', open: 'Reopen CAR with notes' })[status] || 'Record action'; },
        label(value) { return value ? String(value).replaceAll('-', ' ').replaceAll('_', ' ') : 'Not recorded'; },
        date(value) {
            if (!value || Number.isNaN(new Date(value).getTime())) return 'Date not recorded';
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
        },
    }));
});
