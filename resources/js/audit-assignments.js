import { onDocumentReady } from './dom-ready';

onDocumentReady(() => {
    const filters = document.querySelector('[data-assignment-filters]');
    const results = document.querySelector('[data-assignment-results]');
    const loading = document.querySelector('[data-assignment-loading]');
    const message = document.querySelector('[data-assignment-filter-message]');
    let pendingFilter;
    const loadResults = async url => {
        pendingFilter?.abort();
        const request = new AbortController();
        pendingFilter = request;
        results.setAttribute('aria-busy', 'true');
        results.hidden = true;
        loading.hidden = false;
        message.hidden = true;
        message.textContent = '';
        try {
            const response = await fetch(url, { signal: request.signal, credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw Error('Assignments unavailable');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.querySelector('[data-assignment-results]');
            if (!updated) throw Error('Assignment table missing');
            if (request.signal.aborted || pendingFilter !== request) return;
            results.replaceChildren(...updated.childNodes);
            window.history.replaceState(window.history.state, '', url);
            filters.querySelector('[name="status"]').value = url.searchParams.get('status') || '';
        } catch (error) {
            if (request.signal.aborted || pendingFilter !== request) return;
            filters.querySelector('[name="status"]').value = new URL(window.location.href).searchParams.get('status') || '';
            message.hidden = false;
            message.textContent = 'Unable to update assignments. The previous results are still shown. Select a status or Reset Filter to retry.';
        } finally {
            if (pendingFilter === request) {
                results.setAttribute('aria-busy', 'false');
                results.hidden = false;
                loading.hidden = true;
            }
        }
    };
    if (filters && results) {
        filters.addEventListener('submit', event => {
            event.preventDefault();
            const url = new URL(filters.action);
            const status = filters.querySelector('[name="status"]').value;
            if (status) url.searchParams.set('status', status);
            loadResults(url);
        });
        filters.querySelector('[name="status"]').addEventListener('change', () => filters.requestSubmit());
        const navigate = event => {
            const link = event.target.closest('a[href]');
            if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const url = new URL(link.href);
            if (url.origin !== window.location.origin || url.pathname !== '/audits') return;
            event.preventDefault();
            loadResults(url);
        };
        filters.addEventListener('click', navigate);
        results.addEventListener('click', navigate);
        window.addEventListener('pagehide', () => pendingFilter?.abort());
    }
    const scopeDialog = document.querySelector('#audit-scope-dialog');
    if (scopeDialog) {
        let scopeOpener;
        document.addEventListener('click', event => {
            const button = event.target.closest('[data-view-audit-scope]');
            if (!button) return;
            const details = JSON.parse(button.dataset.scopeDetails);
            scopeOpener = button;
            scopeDialog.querySelector('[data-scope-audit]').textContent = details.audit || '';
            scopeDialog.querySelector('[data-scope-content]').textContent = details.scope || 'Not recorded';
            scopeDialog.querySelector('[data-criteria-content]').textContent = details.criteria || 'Not recorded';
            scopeDialog.showModal();
        });
        scopeDialog.querySelector('[data-close-audit-scope]').addEventListener('click', () => scopeDialog.close());
        scopeDialog.addEventListener('close', () => {
            if (scopeOpener?.isConnected) scopeOpener.focus({ preventScroll: true });
        });
    }
    const dialog = document.querySelector('#audit-assignment-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const field = name => form.elements.namedItem(name);
    let opener;
    const filterOptions = (select, allowed) => {
        let count = 0;
        for (const option of select.options) {
            if (!option.value) continue;
            option.hidden = option.disabled = !allowed(option);
            if (!option.disabled) count++;
        }
        if (select.selectedOptions[0]?.disabled) select.value = '';
        return count;
    };
    const refresh = () => {
        const plan = JSON.parse(field('planId').selectedOptions[0]?.dataset.plan || '{}');
        filterOptions(field('departmentId'), option => (plan.unitIds || []).map(String).includes(option.value) && option.dataset.unitType === field('unitType').value);
        const auditors = field('auditorId');
        const eligibleCount = filterOptions(auditors, option => JSON.parse(option.dataset.units).includes(field('departmentId').value));
        const help = dialog.querySelector('[data-assignment-auditor-help]');
        let placeholder = 'Select an eligible auditor';
        let explanation = 'Select the responsible auditor. Home-unit and recorded conflicts are excluded.';
        if (!field('planId').value) {
            placeholder = 'Select an audit plan first';
            explanation = 'Select an authorized audit plan, then choose a department or office covered by it.';
        } else if (!field('departmentId').value) {
            placeholder = 'Select a department or office first';
            explanation = 'Choose a department or office covered by the plan to see its eligible auditors.';
        } else if (!eligibleCount) {
            placeholder = 'No eligible auditors for this unit';
            explanation = 'No independent auditors are currently authorized for this unit. A Super Admin must authorize an auditor from another department or office in Users. Home-unit and recorded conflicts cannot be selected.';
        }
        auditors.options[0].textContent = placeholder;
        auditors.disabled = !field('departmentId').value || !eligibleCount;
        help.textContent = explanation;
        dialog.querySelector('[data-plan-scope]').value = plan.scope || '';
        dialog.querySelector('[data-plan-criteria]').value = plan.criteria || '';
        dialog.querySelector('[data-plan-procedure]').textContent = plan.procedureReference ? `Independence procedure: ${plan.procedureReference}` : '';
        field('schedule').min = plan.scheduleStart || '';
        field('schedule').max = plan.scheduleEnd || '';
        dialog.querySelector('[data-save-assignment]').disabled = !field('planId').value || !field('departmentId').value || !eligibleCount;
    };
    const open = (button, data = {}) => {
        opener = button; form.reset();
        field('assignmentId').value = data.id || data.assignmentId || '';
        const editing = !!field('assignmentId').value;
        form.setAttribute('action', editing ? `/audits/${field('assignmentId').value}/assignment` : '/audits');
        dialog.querySelector('h2').textContent = editing ? 'Change Audit Assignment' : 'New Audit Assignment';
        dialog.querySelector('[data-save-assignment]').textContent = editing ? 'Save Assignment Change' : 'Assign Auditor';
        dialog.querySelector('[data-change-reason]').hidden = !editing;
        field('changeReason').required = editing;
        for (const name of ['planId', 'title', 'schedule', 'version', 'independenceNotes', 'changeReason']) field(name).value = data[name] || '';
        const unitOption = [...field('departmentId').options].find(option => option.value === data.departmentId);
        field('unitType').value = unitOption?.dataset.unitType || 'department';
        refresh(); field('departmentId').value = data.departmentId || ''; refresh();
        field('auditorId').value = data.auditorId || '';
        // Retain context for prepared checklists. Enable before POST to submit values.
        field('planId').disabled = editing;
        field('departmentId').disabled = editing;
        for (const radio of form.querySelectorAll('[name="unitType"]')) radio.disabled = editing;
        dialog.showModal();
    };
    document.querySelector('[data-new-assignment]').addEventListener('click', event => open(event.currentTarget));
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-edit-assignment]');
        if (button) open(button, JSON.parse(button.dataset.assignment));
    });
    form.addEventListener('change', refresh);
    form.addEventListener('submit', () => {
        field('planId').disabled = false;
        field('departmentId').disabled = false;
    });
    dialog.querySelectorAll('[data-close-assignment]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => opener?.focus());
    if (dialog.dataset.reopen === '1') open(document.querySelector('[data-new-assignment]'), JSON.parse(dialog.dataset.old || '{}'));
});
