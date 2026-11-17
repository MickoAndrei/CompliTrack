// Mirrors App\Support\AuditStatus; stored API lifecycle values remain unchanged.
export function auditStatusLabel(status) {
    return ({draft: 'Draft', ready: 'In Progress', in_progress: 'In Progress',
        submitted: 'Submitted — Awaiting ATL Review', returned: 'Returned for Revision', accepted: 'Accepted / Completed'})[status] || String(status || '').replaceAll('_', ' ');
}
