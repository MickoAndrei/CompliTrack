<section class="atl-workspace admin-dashboard" x-data="atlWorkspace" @atl-review-saved.window="load()" :aria-busy="loading">
    <div class="atl-section-heading" style="justify-content: flex-end"><button type="button" class="atl-secondary" @click="load()" :disabled="loading">Refresh</button></div>
    <p x-page-notice x-cloak x-show="error" class="atl-error" role="alert" x-text="error"></p>
    <p x-show="loading" class="sr-only" role="status">Loading Audit Team Leader workspace…</p>
    <div class="stats-grid">
        @foreach([['activeAudits', 'Active Audits', 'clipboard-list'], ['pendingReviews', 'Pending Audit Reviews', 'clipboard-check'], ['openCars', 'Open CARs', 'triangle-alert'], ['overdueCars', 'Overdue Corrective Actions', 'clock-alert']] as [$key, $label, $icon])
        <article class="stat-card"><div class="admin-stat-info"><span class="admin-stat-label">{{ $label }}</span><strong class="admin-stat-number" x-text="loading ? '—' : (data?.stats.{{ $key }} ?? '—')"></strong></div><span class="stat-icon {{ in_array($key, ['openCars', 'overdueCars']) ? 'danger' : 'blue' }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span></article>
        @endforeach
    </div>
    <div class="atl-secondary-summary" aria-label="CET team summary"><span>Total Auditors <strong x-text="data?.people.auditors ?? '—'"></strong></span><span>Total Auditees <strong x-text="data?.people.auditees ?? '—'"></strong></span><span class="atl-muted">Active accounts in your existing CET user scope</span></div>
    <div class="atl-overview-grid">
    <section class="atl-card atl-attention-card" aria-labelledby="atl-attention-title" :aria-busy="loading"><div class="atl-section-heading atl-panel-header"><h2 id="atl-attention-title">Requires My Attention</h2><a href="/audits?view=reviews">All audit reviews</a></div>
        <x-atl-overview-skeleton x-show="loading" />
        <div class="atl-panel-content" x-show="!loading" x-cloak>
        <p x-cloak x-show="!loading && !error && !data?.attention.length" class="atl-empty">No pending reviews, returned submissions, or overdue CARs in your scope.</p>
        <template x-for="item in visibleAttention" :key="item.kind + item.id"><article class="atl-attention-row"><div><strong x-text="item.reference"></strong><p x-text="item.department"></p><p class="atl-muted" x-text="item.reason"></p></div><a :href="item.kind === 'audit' ? `/audits/${item.id}` : `/admin/compliance-records?monitor=1&car=${item.id}`" :data-atl-review="item.kind === 'audit' ? '' : null" :aria-haspopup="item.kind === 'audit' ? 'dialog' : null" x-text="item.kind === 'audit' ? 'Review audit' : 'Monitor CAR'"></a></article></template>
        </div>
        <nav class="atl-attention-pagination" aria-label="Attention pages" x-show="!loading && attentionPages > 1" x-cloak><button type="button" class="atl-secondary" @click="attentionPage--" :disabled="attentionPage <= 1 || loading">Previous</button><span aria-live="polite" x-text="`Page ${attentionPage} of ${attentionPages}`"></span><button type="button" class="atl-secondary" @click="attentionPage++" :disabled="attentionPage >= attentionPages || loading">Next</button></nav>
        <p class="atl-muted atl-attention-note">Five items per page. <a href="/admin/compliance-records?monitor=1&overdue=1">All overdue corrective actions</a></p>
    </section>
    <section class="atl-card atl-compliance-card" aria-labelledby="atl-compliance-title" :aria-busy="loading"><div class="atl-section-heading atl-panel-header"><h2 id="atl-compliance-title">Department Compliance Overview</h2><a href="/admin/reports">Reports</a></div><p class="atl-muted atl-compliance-note">Accepted audits assigned to you · C ÷ (C + N). Observations shown separately.</p>
        <x-atl-overview-skeleton type="compliance" x-show="loading" />
        <div class="atl-panel-content" x-show="!loading" x-cloak>
        <template x-for="department in data?.departmentCompliance || []" :key="department.id"><article class="atl-compliance-row"><div class="atl-section-heading"><h3 x-text="department.name"></h3><strong x-text="department.percentage === null ? 'Not Yet Assessed' : `${department.percentage}%`"></strong></div><div class="atl-compliance-track" :class="{'is-unassessed': department.percentage === null}" role="img" :aria-label="department.percentage === null ? 'Not Yet Assessed' : `${department.percentage}% compliance`"><span :style="`width:${department.percentage ?? 0}%`"></span></div><p class="atl-muted" x-text="`${department.completedAudits} accepted audits · C ${department.counts.C} · N ${department.counts.N} · O ${department.counts.O}`"></p></article></template>
        <p x-cloak x-show="!loading && !error && !data?.departmentCompliance.length" class="atl-empty">No verified CET departments are available in your scope.</p>
        </div>
        <p class="atl-muted atl-compliance-note">CAR closure does not change the original evaluation.</p>
    </section>
    </div>
    <section class="atl-card atl-progress-card" aria-labelledby="atl-progress-title"><div class="atl-section-heading"><h2 id="atl-progress-title">Audit Assignment Progress</h2><a href="/audits">Manage assignments</a></div>
        <p x-cloak x-show="!loading && !error && !data?.assignments.length" class="atl-empty">No audit assignments in your scope yet.</p>
        <div class="um-table-wrap"><table class="um-table"><thead><tr><th>Audit reference</th><th>Department</th><th>Assigned auditor</th><th>Checklist completion</th><th>Current status</th><th>Review</th></tr></thead><tbody><template x-for="audit in data?.assignments || []" :key="audit.id"><tr><td data-label="Audit reference"><strong x-text="audit.reference"></strong><small x-text="audit.title"></small></td><td data-label="Department" x-text="audit.department"></td><td data-label="Assigned auditor" x-text="audit.auditor"></td><td data-label="Checklist completion"><div><progress :value="audit.progress.evaluated" :max="Math.max(1, audit.progress.total)" :aria-label="`${audit.reference}: ${audit.progress.percent}% checklist complete`"></progress><small x-text="`${audit.progress.evaluated}/${audit.progress.total} evaluated · ${audit.progress.percent}%`"></small></div></td><td data-label="Current status"><span class="atl-status" x-text="label(audit.status)"></span></td><td data-label="Review"><a class="atl-result-link" :href="`/audits/${audit.id}`" data-atl-review aria-haspopup="dialog">View results</a></td></tr></template></tbody></table></div>
        <div class="um-pagination atl-progress-footer"><label>Rows per page <select x-model.number="perPage" @change="load(1)" :disabled="loading"><option value="5">5</option><option value="10">10</option></select></label><span x-text="`${data?.assignmentTotal ?? 0} assignments`"></span><x-pagination-controls label="Assignment progress pages" /></div>
    </section>
</section>
