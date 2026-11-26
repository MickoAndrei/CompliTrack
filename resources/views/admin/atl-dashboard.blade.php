<section class="atl-workspace admin-dashboard" x-data="atlWorkspace" @atl-review-saved.window="load()" :aria-busy="loading">
    <p x-page-notice x-cloak x-show="error" class="atl-error" role="alert" x-text="error"></p>

    <div class="stats-grid" :class="{'atl-stats-loading': loading}">
        @foreach([['activeAudits', 'Active Audits', 'clipboard-list'], ['pendingReviews', 'Pending Audit Reviews', 'clipboard-check'], ['openCars', 'Open CARs', 'triangle-alert'], ['overdueCars', 'Overdue Corrective Actions', 'clock-alert']] as [$key, $label, $icon])
        <article class="stat-card"><div class="admin-stat-info"><span class="admin-stat-label">{{ $label }}</span><strong class="admin-stat-number" x-text="loading ? '—' : (data?.stats.{{ $key }} ?? '—')"></strong></div><span class="stat-icon {{ in_array($key, ['openCars', 'overdueCars']) ? 'danger' : 'blue' }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span></article>
        @endforeach
    </div>
    <style>
        .atl-workspace .atl-card {padding:12px 14px}
        .atl-workspace .atl-card > .atl-section-heading {gap:8px;margin-bottom:8px}
        .atl-visual-grid {display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:8px;align-items:stretch;margin-top:8px}
        .atl-chart-stack {display:grid;grid-template-rows:repeat(2,minmax(0,1fr));gap:8px;min-width:0}
        .atl-visual-grid .atl-compliance-card {display:flex;flex-direction:column;justify-content:flex-start;padding:12px 14px}
        .atl-visual-grid .atl-compliance-card .atl-panel-content {display:grid;gap:.65rem;align-content:center;flex:1}
        .atl-visual-grid .atl-compliance-row {padding:0;margin:0;border:0}
        .atl-visual-grid .atl-compliance-row .atl-section-heading {margin:0 0 .25rem;gap:.5rem;align-items:center}
        .atl-visual-grid .atl-compliance-row h3 {font-size:.8rem;line-height:1.3;margin:0}
        .atl-visual-grid .atl-compliance-row strong {font-size:.78rem;white-space:nowrap}
        .atl-visual-grid .atl-compliance-track {height:7px;margin:0}
        .atl-visual-grid .atl-compliance-note {font-size:.72rem;margin:.25rem 0}
        .atl-chart-stack .atl-card {padding:10px 14px}
        .atl-chart-stack .atl-card > .atl-section-heading {margin-bottom:3px}
        .atl-chart-stack .atl-muted {font-size:.72rem;margin:0}
        .atl-chart-stack .atl-visual-chart {gap:.8rem;padding:.5rem 0 0;flex-wrap:nowrap}
        .atl-chart-stack .atl-ring {width:84px;height:84px}
        .atl-chart-stack .atl-ring-center {width:64px;height:64px}
        .atl-chart-stack .atl-ring-center strong {font-size:1.3rem}
        .atl-chart-stack .atl-ring-center small {font-size:.55rem}
        .atl-chart-stack .atl-chart-legend {gap:.35rem;min-width:0}
        .atl-chart-stack .atl-chart-legend div {font-size:.73rem}
        .atl-chart-stack .atl-chart-legend strong {font-size:.82rem}
        .atl-attention-wide {grid-column:1/-1}
        .atl-visual-grid .atl-card {min-width:0;margin:0}
        .atl-visual-chart {display:flex;align-items:center;justify-content:center;gap:1.5rem;flex-wrap:wrap;padding:1rem 0}
        .atl-ring {width:132px;height:132px;flex:none;border-radius:50%;display:grid;place-items:center;background:#e2e8f0}
        .atl-ring-center {width:100px;height:100px;border-radius:50%;display:grid;align-content:center;text-align:center;background:var(--atl-card-bg,#fff);color:#172b4d}
        .atl-ring-center strong {font-size:1.9rem;line-height:1.1}
        .atl-ring-center small {font-size:.7rem;color:#64748b}
        .atl-chart-legend {display:grid;gap:.75rem;min-width:145px;flex:0 1 auto}
        .atl-chart-legend div {display:flex;align-items:center;gap:.5rem;font-size:.875rem}
        .atl-chart-legend strong {margin-left:auto;min-width:2ch;text-align:right;font-variant-numeric:tabular-nums;font-size:1rem}
        .atl-swatch {width:.65rem;height:.65rem;border-radius:3px;flex:none}
        .atl-quick-action {display:flex;align-items:center;gap:.75rem;padding:8px 0;border-bottom:1px solid #e2e8f0}
        .atl-quick-action:last-child {border-bottom:0}
        .atl-quick-action strong {font-size:1.35rem;min-width:2rem}
        .atl-quick-action a {margin-left:auto;white-space:nowrap}
        .atl-loading-slot {display:grid;min-width:0}
        .atl-loading-slot > * {grid-area:1/1;min-width:0}
        .atl-content-hidden {visibility:hidden;pointer-events:none}
        .atl-compliance-slot {flex:1;min-height:190px}
        .atl-chart-slot {min-height:92px}
        .atl-attention-slot {min-height:144px}
        .atl-workspace .atl-assignment-link {margin-top:8px}
        .atl-assignment-link > .atl-section-heading {margin-bottom:0!important}
        .atl-assignment-link .atl-muted {margin-bottom:0}
        .atl-loading-placeholder {align-self:stretch;align-content:center}
        .atl-compliance-placeholder {display:grid;gap:.65rem}
        .atl-compliance-placeholder .atl-skeleton-heading {margin-bottom:.4rem}
        .atl-chart-placeholder {display:flex;align-items:center;justify-content:center;gap:.8rem;padding-top:.5rem}
        .atl-skeleton-ring {width:84px;height:84px;border-radius:50%;flex:none}
        .atl-skeleton-legend {display:grid;gap:.5rem;width:140px}
        .atl-attention-placeholder {display:grid;grid-template-rows:repeat(3,1fr);gap:1px}
        .atl-attention-placeholder > div {display:flex;align-items:center;gap:.75rem;border-bottom:1px solid #e2e8f0}
        .atl-attention-placeholder > div:last-child {border-bottom:0}
        .atl-stats-loading .admin-stat-number {position:relative;color:transparent;min-width:2ch}
        .atl-stats-loading .admin-stat-number::after {content:'';position:absolute;inset:15% 0;background:#e8edf4;border-radius:5px}
        @media(prefers-reduced-motion:reduce){.atl-workspace .atl-skeleton{animation:none}}
        @media(max-width:760px){.atl-visual-grid{grid-template-columns:1fr}.atl-chart-stack{grid-template-rows:auto auto}}
    </style>
    <div class="atl-visual-grid">
    <section class="atl-card atl-compliance-card" aria-labelledby="atl-compliance-title" :aria-busy="loading"><div class="atl-section-heading atl-panel-header"><div><h2 id="atl-compliance-title">Department Compliance</h2><p class="atl-muted atl-compliance-note">Accepted audits · C ÷ (C + N)</p></div><a class="atl-secondary" href="/admin/reports">Reports</a></div>

        <div class="atl-loading-slot atl-compliance-slot">
        <div class="atl-loading-placeholder atl-compliance-placeholder" x-show="loading" aria-hidden="true">
            @for($row = 0; $row < 5; $row++)
            <div><div class="atl-skeleton-heading"><span class="atl-skeleton atl-skeleton-name"></span><span class="atl-skeleton" style="width:3rem"></span></div><span class="atl-skeleton atl-skeleton-track"></span></div>
            @endfor
        </div>
        <div class="atl-panel-content" :class="{'atl-content-hidden': loading}" :aria-hidden="loading" x-cloak>
        <template x-for="department in data?.departmentCompliance || []" :key="department.id"><article class="atl-compliance-row"><div class="atl-section-heading"><h3 x-text="department.name"></h3><strong x-text="department.percentage === null ? 'Not Yet Assessed' : `${department.percentage}%`"></strong></div><div class="atl-compliance-track" :class="{'is-unassessed': department.percentage === null}" role="img" :title="`${department.completedAudits} accepted audits · C ${department.counts.C} · N ${department.counts.N} · O ${department.counts.O}`" :aria-label="department.percentage === null ? 'Not Yet Assessed' : `${department.percentage}% compliance`"><span :style="`width:${department.percentage ?? 0}%`"></span></div></article></template>
        <p x-cloak x-show="!loading && !error && !data?.departmentCompliance.length" class="atl-empty">No verified CET departments are available in your scope.</p>
        </div>
        </div>
        <p class="atl-muted atl-compliance-note">CAR closure does not change the original evaluation.</p>
    </section>

        <div class="atl-chart-stack">
        <section class="atl-card" aria-labelledby="atl-status-title" :aria-busy="loading">
            <div class="atl-section-heading"><h2 id="atl-status-title">Audit Status</h2><a class="atl-secondary" href="/audits">Assignments</a></div>
            <p class="atl-muted">All audit assignments within your scope</p>
            <div class="atl-loading-slot atl-chart-slot">
            <div class="atl-loading-placeholder atl-chart-placeholder" x-show="loading" aria-hidden="true">
                <span class="atl-skeleton atl-skeleton-ring"></span>
                <div class="atl-skeleton-legend"><span class="atl-skeleton"></span><span class="atl-skeleton"></span><span class="atl-skeleton"></span></div>
            </div>
            <div class="atl-visual-chart" :class="{'atl-content-hidden': loading || !data}" :aria-hidden="loading || !data" x-cloak x-data="{ get total() { return Number(data?.assignmentTotal ?? 0) }, get active() { return Number(data?.stats.activeAudits ?? 0) }, get pending() { return Number(data?.stats.pendingReviews ?? 0) } }">
                <div class="atl-ring" aria-hidden="true" :style="total ? `background:conic-gradient(#d6ad48 0% ${pending/total*100}%, #254775 ${pending/total*100}% ${active/total*100}%, #94a3b8 ${active/total*100}% 100%)` : ''">
                    <div class="atl-ring-center"><strong x-text="total"></strong><small>Assignments</small></div>
                </div>
                <div class="atl-chart-legend">
                    <div><span class="atl-swatch" style="background:#d6ad48"></span>Pending review<strong x-text="pending"></strong></div>
                    <div><span class="atl-swatch" style="background:#254775"></span>Other active<strong x-text="Math.max(0,active-pending)"></strong></div>
                    <div><span class="atl-swatch" style="background:#94a3b8"></span>Other statuses<strong x-text="Math.max(0,total-active)"></strong></div>
                </div>
            </div>
            </div>

        </section>
        <section class="atl-card" aria-labelledby="atl-team-title" :aria-busy="loading">
            <div class="atl-section-heading"><h2 id="atl-team-title">Audit Team Overview</h2></div>
            <p class="atl-muted">Active accounts within CET</p>
            <div class="atl-loading-slot atl-chart-slot">
            <div class="atl-loading-placeholder atl-chart-placeholder" x-show="loading" aria-hidden="true">
                <span class="atl-skeleton atl-skeleton-ring"></span>
                <div class="atl-skeleton-legend"><span class="atl-skeleton"></span><span class="atl-skeleton"></span><span class="atl-skeleton"></span></div>
            </div>
            <div class="atl-visual-chart" :class="{'atl-content-hidden': loading || !data}" :aria-hidden="loading || !data" x-cloak x-data="{ get auditors() { return Number(data?.people.auditors ?? 0) }, get auditees() { return Number(data?.people.auditees ?? 0) }, get total() { return this.auditors+this.auditees } }">
                <div class="atl-ring" aria-hidden="true" :style="total ? `background:conic-gradient(#254775 0% ${auditors/total*100}%, #d6ad48 ${auditors/total*100}% 100%)` : ''">
                    <div class="atl-ring-center"><strong x-text="total"></strong><small>Active accounts</small></div>
                </div>
                <div class="atl-chart-legend">
                    <div><span class="atl-swatch" style="background:#254775"></span>Total Auditors<strong x-text="auditors"></strong></div>
                    <div><span class="atl-swatch" style="background:#d6ad48"></span>Total Auditees<strong x-text="auditees"></strong></div>
                </div>
            </div>
            </div>

        </section>
        </div>
        <section class="atl-card atl-attention-wide" aria-labelledby="atl-attention-title" :aria-busy="loading">
            <div class="atl-section-heading"><h2 id="atl-attention-title">Requires Attention</h2></div>
            <div class="atl-loading-slot atl-attention-slot">
            <div class="atl-loading-placeholder atl-attention-placeholder" x-show="loading" aria-hidden="true">
                @for($row = 0; $row < 3; $row++)
                <div><span class="atl-skeleton" style="width:2rem;height:1.35rem"></span><span class="atl-skeleton" style="width:10rem"></span><span class="atl-skeleton" style="width:3rem;margin-left:auto"></span></div>
                @endfor
            </div>
            <div :class="{'atl-content-hidden': loading || !data}" :aria-hidden="loading || !data" x-cloak>
                <div class="atl-quick-action"><strong style="color:#b6852f" x-text="data?.stats.pendingReviews ?? 0"></strong><span>Pending audit reviews</span><a class="atl-secondary" href="/audits?view=reviews">View</a></div>
                <div class="atl-quick-action"><strong style="color:#b84e4b" x-text="data?.stats.overdueCars ?? 0"></strong><span>Overdue CARs</span><a class="atl-secondary" href="/admin/compliance-records?monitor=1&overdue=1">View</a></div>
                <div class="atl-quick-action"><strong style="color:#254775" x-text="Math.max(0,(data?.stats.openCars ?? 0)-(data?.stats.overdueCars ?? 0))"></strong><span>Other open CARs</span><a class="atl-secondary" href="/admin/compliance-records?monitor=1">View</a></div>
            </div>
            </div>

        </section>
    </div>
    <section class="atl-card atl-assignment-link" aria-labelledby="atl-progress-title">
        <div class="atl-section-heading"><div><h2 id="atl-progress-title">Audit Assignment Progress</h2><p class="atl-muted">View checklist completion, individual assignments and returned submissions in the audit workspace.</p></div><a class="attention-button" href="/audits">Manage assignments</a></div>
    </section>
</section>
