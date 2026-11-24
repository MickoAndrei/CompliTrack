const tabs = ['overview', 'checklist', 'requests', 'interviews', 'review'];

export function workspaceHistoryViewport(root) {
    const readiness = root.querySelector('[data-submission-readiness]');
    const card = root.querySelector('.aw-history');
    const viewport = root.querySelector('.aw-history-scroll');
    const timeline = root.querySelector('.aw-timeline');
    if (!readiness || !card || !viewport || !timeline) return () => {};
    const resize = () => {
        if (!readiness.getClientRects().length) return;
        const nodes = [...timeline.querySelectorAll('.aw-event')];
        const fifth = nodes[Math.min(4, nodes.length - 1)];
        const style = window.getComputedStyle(card);
        const available = readiness.getBoundingClientRect().height
            - (viewport.getBoundingClientRect().top - card.getBoundingClientRect().top)
            - parseFloat(style.paddingBottom) - parseFloat(style.borderBottomWidth);
        const fiveNodes = fifth ? fifth.getBoundingClientRect().bottom - timeline.getBoundingClientRect().top + 6 : available;
        viewport.style.maxHeight = `${Math.max(48, Math.min(available, fiveNodes))}px`;
    };
    const observer = new ResizeObserver(resize);
    observer.observe(readiness);
    observer.observe(timeline);
    window.addEventListener('resize', resize);
    resize();
    return () => { observer.disconnect(); window.removeEventListener('resize', resize); };
}

export function workspaceLocation(hash, count) {
    const [section, rawItem, panel] = hash.replace(/^#/, '').split('/');
    return { tab: section === 'evidence-requests' ? 'requests' : tabs.includes(section) ? section : 'checklist',
        selected: Math.max(0, Math.min(Math.max(0, count - 1), Number.parseInt(rawItem, 10) || 0)),
        panel: panel === 'evidence' ? 'evidence' : 'evaluation' };
}

export function auditWorkspace(count) {
    return {
        ...workspaceLocation(window.location.hash, count), count, search: '', dirty: new Set(), saving: false,
        question: '', reference: '', editing: -1, method: 'digital', evidenceTarget: -1, opener: null, root: null,
        init() {
            this.root = this.$el;
            this.stopHistoryViewport = workspaceHistoryViewport(this.root);
            this.method = this.root.dataset.oldMethod || 'digital';
            this.onHash = () => Object.assign(this, workspaceLocation(window.location.hash, count));
            this.onLeave = event => { if (this.dirty.size && !this.saving) { event.preventDefault(); event.returnValue = ''; } };
            window.addEventListener('hashchange', this.onHash);
            window.addEventListener('beforeunload', this.onLeave);
            this.root.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('keydown', event => {
                if (event.key !== 'Tab') return;
                const controls = [...dialog.querySelectorAll('button, a[href], input, select, textarea, [tabindex]')]
                    .filter(control => !control.disabled && control.tabIndex >= 0 && control.getClientRects().length);
                const first = controls[0], last = controls.at(-1);
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
            }));
            this.root.querySelectorAll('form').forEach(form => {
                const initial = this.fingerprint(form);
                const change = () => {
                    const next = new Set(this.dirty);
                    if (this.fingerprint(form) === initial) next.delete(form); else next.add(form);
                    this.dirty = next;
                };
                form.addEventListener('input', change);
                form.addEventListener('change', change);
                form.addEventListener('submit', event => {
                    if (this.saving) { event.preventDefault(); return; }
                    const others = [...this.dirty].some(entry => entry !== form);
                    if ((others && !window.confirm('Other forms contain unsaved changes. Continue and discard those changes?')) ||
                        (form.dataset.confirm && !window.confirm(form.dataset.confirm))) { event.preventDefault(); return; }
                    this.saving = true;
                });
            });
            const recovery = this.root.dataset.recovery;
            if (recovery) this.$nextTick(() => {
                this.go(recovery === 'interview' ? 'interviews' : recovery === 'request' ? 'requests' : ['submit', 'accept', 'return'].includes(recovery) ? 'review' : 'checklist', Number(this.root.dataset.oldItem) || 0);
                const id = { add: 'question', edit: 'question', evidence: 'evidence', interview: 'interview', request: 'request' }[recovery];
                if (id) {
                    this.editing = recovery === 'edit' ? this.selected : -1;
                    this.question = this.root.dataset.oldQuestion || '';
                    this.reference = this.root.dataset.oldReference || '';
                    this.openModal(id);
                }
            });
        },
        fingerprint(form) { return new URLSearchParams(new FormData(form)).toString(); },
        go(tab, item = this.selected, panel = this.panel) {
            this.tab = tab; this.selected = item; this.panel = panel;
            const hash = `#${tab}/${item}/${panel}`;
            if (window.location.hash !== hash) window.location.hash = hash;
        },
        tabKey(event) {
            const buttons = [...event.currentTarget.querySelectorAll('[role=tab]')];
            let index = buttons.indexOf(event.target);
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            index = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
            buttons[index].click(); buttons[index].focus();
        },
        matches(text) { return text.toLocaleLowerCase().includes(this.search.trim().toLocaleLowerCase()); },
        openModal(name) {
            if (name === 'evidence') {
                const form = this.root.querySelector('#workspace-evidence-dialog form');
                if (this.evidenceTarget >= 0 && this.evidenceTarget !== this.selected) {
                    if (this.dirty.has(form) && !window.confirm('Discard the unsaved evidence review for the previous item?')) return;
                    form.reset(); this.method = 'digital';
                    const next = new Set(this.dirty); next.delete(form); this.dirty = next;
                }
                this.evidenceTarget = this.selected;
            }
            this.opener = document.activeElement;
            this.root.querySelector(`#workspace-${name}-dialog`)?.showModal();
        },
        closeModal(event) {
            const dialog = event.target.closest('dialog');
            if (this.dirty.has(dialog.querySelector('form')) && !window.confirm('Close this form? Your entries will be kept until you leave this page.')) return;
            dialog.close(); this.opener?.focus();
        },
        prepare(index = -1, question = '', reference = '') {
            const form = this.root.querySelector('#workspace-question-dialog form');
            if (this.dirty.has(form) && this.editing !== index && !window.confirm('Discard the unsaved checklist question?')) return;
            if (!this.dirty.has(form) || this.editing !== index) {
                this.editing = index; this.question = question; this.reference = reference;
                const next = new Set(this.dirty); next.delete(form); this.dirty = next;
            }
            this.openModal('question');
        },
        destroy() { this.stopHistoryViewport?.(); window.removeEventListener('hashchange', this.onHash); window.removeEventListener('beforeunload', this.onLeave); },
    };
}

if (typeof document !== 'undefined') document.addEventListener('alpine:init', () => window.Alpine.data('auditWorkspace', auditWorkspace));
