@extends('layouts.app')
@section('title', 'Super Admin Dashboard')
@section('body-class', 'super-admin-dashboard-page')
@section('heading', 'Super Admin Dashboard')
@section('subtitle', 'College of Engineering and Technology · USTP Claveria')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/super-admin-dashboard.css') }}?v={{ filemtime(public_path('css/super-admin-dashboard.css')) }}">
@endpush
@section('content')
<section class="sa-overview" x-data="superAdminDashboard" :aria-busy="loading">
    <div class="sa-stats">
        @foreach ([['totalUsers', 'Total Users', 'users', 'purple', 'All registered accounts'], ['cetDepartments', 'CET Departments', 'building-2', 'slate', 'Academic departments only'], ['activeAudits', 'Active Audits', 'clipboard-check', 'blue', 'Draft, in progress & awaiting review'], ['openCars', 'Open CARs', 'triangle-alert', 'danger', 'Open and in progress'], ['archivedDocuments', 'Archived Documents', 'archive', 'slate', 'Authorized records, including versions'], ['archiveStorageBytes', 'Archive Storage Usage', 'hard-drive', 'blue', 'Stored file sizes, including versions']] as [$key, $label, $icon, $color, $hint])
        <article class="sa-stat metric-stat-card" title="{{ $hint }}"><div class="metric-info"><span class="metric-label">{{ $label }}</span><span class="sa-skeleton sa-skeleton-value" x-show="loading" aria-hidden="true"></span><strong class="metric-number color-dark" x-show="!loading" x-cloak x-text="!data ? '—' : {{ $key === 'archiveStorageBytes' ? 'bytes(data.stats.archiveStorageBytes)' : 'data.stats.'.$key }}"></strong></div><div class="metric-icon-box {{ $color }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></div></article>
        @endforeach
    </div>
    <div class="sa-top-grid">
    <article class="sa-panel sa-compliance" x-data="{
            get list() { return data?.departmentCompliance || [] },
            get assessed() { return this.list.filter(d => d.percentage !== null) },
            get totalC() { return this.list.reduce((s, d) => s + (d.counts?.C || 0), 0) },
            get totalN() { return this.list.reduce((s, d) => s + (d.counts?.N || 0), 0) },
            get totalO() { return this.list.reduce((s, d) => s + (d.counts?.O || 0), 0) },
            get overall() { const den = this.totalC + this.totalN; return den ? Math.round(this.totalC / den * 100) : null },
            get donutStyle() { const p = this.overall ?? 0; return `background: conic-gradient(#10b981 0 ${p}%, #e8edf5 ${p}% 100%)` },
            get sorted() { return [...this.list].sort((a, b) => (b.percentage ?? -1) - (a.percentage ?? -1)) },
            get lowest() { return this.assessed.length ? [...this.assessed].sort((a, b) => a.percentage - b.percentage)[0] : null },
            barColor(p) { return p === null ? '#cbd5e1' : p < 70 ? '#ef4444' : '#10b981' },
            barClass(p) { return p === null ? 'is-unassessed' : p < 70 ? 'is-attention' : 'is-compliant' }
        }">
        <header><div><h3>Department Compliance</h3><p>Completed audits only · C ÷ (C + N)</p></div><a href="/super-admin/departments">Manage departments</a></header>
        <x-dashboard-skeleton type="comparison" x-show="loading" />
        <div class="sa-compliance-brief" x-show="data && !loading" x-cloak>
            <div class="sa-compliance-top">
                <div class="sa-compliance-donut" :style="donutStyle" role="img" :aria-label="overall === null ? 'No completed audits yet' : `CET average ${overall}% compliance`">
                    <div class="sa-compliance-donut-inner">
                        <strong x-text="overall === null ? '—' : `${overall}%`"></strong>
                        <span>CET Average</span>
                    </div>
                </div>
                <div class="sa-compliance-totals">
                    <div class="sa-compliance-counts" aria-label="Total findings across CET departments">
                        <span class="sa-compliance-count is-c"><b x-text="totalC"></b> C · Compliant</span>
                        <span class="sa-compliance-count is-n"><b x-text="totalN"></b> N · Non-Compliant</span>
                        <span class="sa-compliance-count is-o"><b x-text="totalO"></b> O · Observation</span>
                    </div>
                    <p class="sa-compliance-formula">O is counted separately — it does not lower the %.</p>
                    <p class="sa-compliance-meta"><b x-text="data?.recordSummary.completedAudits"></b> completed audits · <b x-text="data?.recordSummary.legacyChecks"></b> legacy checks · <b x-text="data?.recordSummary.checklistLinkedCars"></b> checklist-linked CARs</p>
                </div>
            </div>
            <ul class="sa-compliance-bars">
                <template x-for="department in sorted" :key="department.id">
                    <li class="sa-compliance-bar-row" :class="{'is-inactive': !department.active}" :title="`${department.name} — C ${department.counts.C} · N ${department.counts.N} · O ${department.counts.O}${!department.active ? ' · Inactive' : ''}`">
                        <span class="sa-cb-code" x-text="department.code"></span>
                        <span class="sa-cb-track" :class="barClass(department.percentage)"><span :style="`width:${department.percentage ?? 0}%; background:${barColor(department.percentage)}`"></span></span>
                        <strong class="sa-cb-pct" :class="barClass(department.percentage)" x-text="department.percentage === null ? 'Not Yet Assessed' : `${department.percentage}%`"></strong>
                    </li>
                </template>
            </ul>
            <p class="sa-compliance-foot" x-show="list.length">Lowest: <span x-text="lowest ? `${lowest.code} ${lowest.percentage}%` : '—'"></span> · Closing a CAR does not change the original finding.</p>
            <p class="sa-compliance-foot" x-show="!list.length">No verified CET academic departments found.</p>
        </div>
    </article>
    <article class="sa-panel sa-growth-panel"><header><div><h3 id="sa-user-growth-title">User Growth Trend</h3><p>Cumulative registered accounts · last six months</p></div><a href="/super-admin/users">Manage users</a></header>
        <x-dashboard-skeleton type="chart" x-show="loading" />
        <p x-show="!loading && data && !data.userGrowth.length" x-cloak>No monthly user data yet.</p>
        <figure class="sa-user-growth" aria-labelledby="sa-user-growth-title" x-show="!loading && data?.userGrowth?.length" x-cloak>
            <div class="sa-trend-axis" aria-hidden="true"><template x-for="tick in [0, 1, 2, 3, 4]" :key="tick"><span :style="`top:${trendY(trendMax() * tick / 4) / 2}%`" x-text="trendMax() * tick / 4"></span></template></div>
            <div class="sa-trend-plot">
                <svg class="sa-trend-svg" viewBox="0 0 600 200" preserveAspectRatio="none" role="img" :aria-label="`User growth: ${(data?.userGrowth || []).map(month => `${month.month}: ${month.count} users`).join(', ')}`">
                    @foreach ([0, 1, 2, 3, 4] as $tick)
                    <line x1="0" x2="600" :y1="trendY(trendMax() * {{ $tick }} / 4)" :y2="trendY(trendMax() * {{ $tick }} / 4)" stroke="#e2e8f0" stroke-width="1" vector-effect="non-scaling-stroke" />
                    @endforeach
                    <polyline class="sa-trend-line" :points="trendPoints()" fill="none" stroke="#00205b" stroke-width="2.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <template x-for="(month, index) in data?.userGrowth || []" :key="month.month"><button type="button" class="sa-trend-point" :class="{'is-first': index === 0, 'is-last': index === data.userGrowth.length - 1}" :style="`left:${trendX(index) / 6}%; top:${trendY(month.count) / 2}%`" :aria-label="`${month.month}: ${month.count} users`"><span class="sa-trend-dot" aria-hidden="true"></span><span class="sa-trend-tooltip" aria-hidden="true" x-text="`${month.month}: ${month.count} users`"></span></button></template>
                <div class="sa-trend-labels" aria-hidden="true"><template x-for="(month, index) in data?.userGrowth || []" :key="month.month"><span :style="`left:${trendX(index) / 6}%`" :title="month.month" x-text="month.month.split(' ')[0]"></span></template></div>
            </div>
        </figure>
    </div>
    <div class="sa-detail-grid">
        <article class="sa-panel sa-db-panel" x-data="{
            get used() { return data?.stats?.archiveStorageBytes ?? 0 },
            get total() { return data?.stats?.archiveStorageCapacityBytes ?? 536870912 },
            get free() { return Math.max(0, this.total - this.used) },
            get pct() { return this.total ? Math.round(this.used / this.total * 100) : 0 },
            get env() { return (data?.databaseEnvironment || data?.stats?.databaseEnvironment || 'REMOTE').toUpperCase() },
            get mongo() { return (health?.components || []).find(s => /mongo|database/i.test(s.name)) },
            get mongoStatus() { return this.mongo?.status || 'Operational' },
            get storageHealth() { return this.pct >= 90 ? 'Critical' : this.pct >= 80 ? 'Warning' : 'Healthy' }
        }">
            <header><div><h3>Database Overview</h3><p>Storage · Health · Environment — live MongoDB</p></div></header>
            <x-dashboard-skeleton type="activity" x-show="loading" />
            <div class="sa-db-body" x-show="data && !loading" x-cloak>
                <div class="sa-db-env">
                    <div class="sa-db-env-left"><span class="sa-db-dot" :class="{'is-ok': mongoStatus === 'Operational'}"></span><div><b x-text="`${env} — Connected`"></b><small>USTP Database Server</small></div></div>
                    <span class="sa-db-badge" x-text="env"></span>
                </div>
                <div class="sa-db-storage">
                    <div class="sa-db-storage-top"><strong><span x-text="bytes(used)"></span> <span>used</span></strong><small>of <span x-text="bytes(total)"></span> total</small></div>
                    <div class="sa-db-split"><span><b class="is-used">● Used: <span x-text="bytes(used)"></span> (<span x-text="pct"></span>%)</b></span><span><b class="is-free">● Free: <span x-text="bytes(free)"></span> (<span x-text="100 - pct"></span>%)</b></span></div>
                    <div class="sa-db-bar" role="img" :aria-label="`${bytes(used)} used of ${bytes(total)}, ${bytes(free)} free`"><span :style="`width:${pct}%; background:#10b981`"></span><span :style="`width:${100 - pct}%; background:#e2e8f0`"></span></div>
                    <div class="sa-db-legend"><span><span x-text="data?.stats?.archivedDocuments ?? 0"></span> archived docs</span><span>·</span><span><b x-text="`${bytes(free)} free`"></b></span></div>
                </div>
                <div class="sa-db-health">
                    <h4>Current health</h4>
                    <div class="sa-db-row"><div class="sa-db-row-left"><span class="sa-db-dot" :class="{'is-ok': mongoStatus === 'Operational'}"></span><div>MongoDB<small>Live connection</small></div></div><span class="sa-db-pill is-green" x-text="mongoStatus"></span></div>
                    <div class="sa-db-row"><div class="sa-db-row-left"><span class="sa-db-dot is-amber"></span><div>Archive storage<small x-text="`${bytes(used)} used`"></small></div></div><span class="sa-db-pill" :class="storageHealth === 'Healthy' ? 'is-amber' : 'is-red'" x-text="storageHealth"></span></div>
                    <div class="sa-db-row"><div class="sa-db-row-left"><span class="sa-db-dot is-ok"></span><div>Last backup<small>Database Control</small></div></div><span class="sa-db-pill is-green">Completed</span></div>
                </div>
                <p class="sa-db-foot"><span x-show="health?.checkedAt" x-text="`Last checked: ${date(health.checkedAt)}`"></span><span>Local available · Switch in Database Control</span></p>
            </div>
        </article>
        <article class="sa-panel sa-health" :aria-busy="healthLoading"><header><div><h3>System Health</h3><p>Refreshes automatically every minute</p></div><button type="button" class="sa-health-refresh" @click="loadHealth()" :disabled="healthLoading" aria-label="Refresh system health"><i data-lucide="refresh-cw" :class="{'is-spinning': healthLoading}" aria-hidden="true"></i></button></header>
        <p x-page-notice class="sa-error" role="alert" x-show="healthError" x-cloak x-text="healthError"></p>
        <x-dashboard-skeleton type="health" x-show="healthLoading" />
        <dl class="system-health-list" x-show="!healthLoading" x-cloak><template x-for="service in health?.components || []" :key="service.name"><div class="system-health-row"><dt x-text="service.name"></dt><dd class="system-health-status" :class="{'is-operational': service.status === 'Operational', 'is-unavailable': service.status === 'Unavailable', 'is-not-configured': ['Not Configured', 'Unknown'].includes(service.status), 'is-configured': service.status === 'Configured'}"><span class="system-health-dot" aria-hidden="true"></span><span x-text="service.status"></span></dd></div></template></dl>
        <p class="sr-only" role="status" x-show="healthLoading">Checking system services…</p>
        <p class="sa-health-message" x-show="!healthLoading && !healthError && !health?.components?.length" x-cloak>No system health data available.</p>
        <div class="sa-health-foot">
            <p class="sa-health-note" x-show="health?.components?.some(service => service.name === 'FCM Push Service' && service.status === 'Not Configured')" x-cloak>FCM push is not configured. In-app realtime notifications use the existing polling service.</p>
            <p class="sa-health-updated" x-show="health?.checkedAt" x-cloak x-text="health ? `Updated ${date(health.checkedAt)}` : ''"></p>
        </div>
    </article>
    </div>

</section>
@endsection