@extends('layouts.app')
@section('title', 'Super Admin Dashboard')
@section('heading', 'Compliance Overview')
@section('subtitle', 'CET oversight, organizational records & system health')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/super-admin-dashboard.css') }}?v={{ filemtime(public_path('css/super-admin-dashboard.css')) }}">
@endpush
@section('content')
<section class="sa-overview" x-data="superAdminDashboard" :aria-busy="loading">
    <header class="sa-page-heading"><div><h2>Super Admin Dashboard</h2><p>College of Engineering and Technology · USTP Claveria</p><p x-show="data" x-cloak x-text="data ? `Records refreshed ${date(data.generatedAt)}` : ''"></p></div><button type="button" @click="load(); loadHealth()" :disabled="loading || healthLoading">Refresh overview</button></header>
    <p class="sa-error" role="alert" x-show="error" x-cloak x-text="error"></p>
    <p class="sr-only" role="status" x-show="loading">Loading dashboard records…</p>
    <div class="sa-stats">
        @foreach ([['totalUsers', 'Total Users', 'users', 'purple', 'All registered accounts'], ['cetDepartments', 'CET Departments', 'building-2', 'slate', 'Academic departments only'], ['activeAudits', 'Active Audits', 'clipboard-check', 'blue', 'Draft, in progress & awaiting review'], ['openCars', 'Open CARs', 'triangle-alert', 'danger', 'Open and in progress'], ['archivedDocuments', 'Archived Documents', 'archive', 'slate', 'Authorized records, including versions'], ['archiveStorageBytes', 'Archive Storage Usage', 'hard-drive', 'blue', 'Stored file sizes, including versions']] as [$key, $label, $icon, $color, $hint])
        <article class="sa-stat metric-stat-card" title="{{ $hint }}"><div class="metric-info"><span class="metric-label">{{ $label }}</span><span class="sa-skeleton sa-skeleton-value" x-show="loading" aria-hidden="true"></span><strong class="metric-number color-dark" x-show="!loading" x-cloak x-text="!data ? '—' : {{ $key === 'archiveStorageBytes' ? 'bytes(data.stats.archiveStorageBytes)' : 'data.stats.'.$key }}"></strong></div><div class="metric-icon-box {{ $color }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></div></article>
        @endforeach
    </div>
    <article class="sa-panel">
        <header><div><h3>Department Compliance</h3><p>Completed audits only · C ÷ (C + N). Observations (O) are listed separately.</p></div><a href="/super-admin/departments">Manage departments</a></header>
        <x-dashboard-skeleton type="comparison" x-show="loading" />
        <div class="sa-comparison" x-show="data && !loading" x-cloak>
            <template x-for="department in data?.departmentCompliance || []" :key="department.id">
                <div class="sa-department-row">
                    <div><strong x-text="department.name"></strong><small><span x-text="department.code"></span><span x-show="!department.active"> · Inactive</span></small></div>
                    <div class="sa-comparison-track" :class="{'is-unassessed': department.percentage === null}" role="img" :aria-label="department.percentage === null ? 'Not Yet Assessed' : `${department.percentage}% compliance`"><span :style="`width:${department.percentage ?? 0}%`"></span></div>
                    <div class="sa-comparison-value"><strong x-text="department.percentage === null ? 'Not Yet Assessed' : `${department.percentage}%`"></strong><small x-text="`C ${department.counts.C} · N ${department.counts.N} · O ${department.counts.O}`"></small></div>
                </div>
            </template>
            <p x-show="data && data.departmentCompliance.length === 0">No verified CET academic departments found.</p>
        </div>
        <p class="sa-note">N/C/O counts come from completed checklists. Legacy checks and CARs are counted separately; closing a CAR does not change the original finding.</p>
        <x-dashboard-skeleton type="summary" x-show="loading" />
        <div class="sa-record-summary" x-show="!loading && data" x-cloak><span><b x-text="data?.recordSummary.completedAudits"></b> completed audits</span><span><b x-text="data?.recordSummary.legacyChecks"></b> legacy checks</span><span><b x-text="data?.recordSummary.checklistLinkedCars"></b> checklist-linked CARs</span></div>
    </article>
    <div class="sa-detail-grid">
        <article class="sa-panel"><header><div><h3>Recent Audit Activity</h3><p>Latest updates from recorded audits</p></div><a href="/super-admin/logs">System logs</a></header>
            <x-dashboard-skeleton type="activity" x-show="loading" />
            <p x-show="!loading && data && !data.recentAuditActivity.length" x-cloak>No audit activity recorded.</p>
            <ol class="sa-activity sa-activity--audits" tabindex="0" x-data="dashboardActivityList" x-effect="data; loading; $nextTick(() => observeRows())" x-show="!loading" x-cloak><template x-for="audit in data?.recentAuditActivity || []" :key="audit.id"><li><div><strong x-text="audit.reference"></strong><span class="sa-status" x-text="status(audit.status)"></span></div><p x-text="audit.department"></p><time x-text="date(audit.at)"></time></li></template></ol>
        </article>
        <article class="sa-panel"><header><div><h3>Corrective Actions Requiring Attention</h3><p>Open / in-progress CARs · overdue first, then earliest due</p></div></header>
            <x-dashboard-skeleton type="activity" x-show="loading" />
            <p x-show="!loading && data && !data.correctiveAttention.length" x-cloak>No corrective actions currently require attention.</p>
            <ol class="sa-activity sa-activity--cars" tabindex="0" x-data="dashboardActivityList" x-effect="data; loading; $nextTick(() => observeRows())" x-show="!loading" x-cloak><template x-for="car in data?.correctiveAttention || []" :key="car.id"><li><div><strong x-text="car.reference"></strong><span class="sa-status" :class="{'is-overdue': car.overdue}" x-text="car.overdue ? 'Overdue' : status(car.status)"></span></div><p x-text="`${car.department} · ${car.assignee}`"></p><small x-text="car.source"></small><time x-text="car.dueDate ? `Due ${date(car.dueDate)}` : 'No due date recorded'"></time></li></template></ol>
        </article>
    </div>
    <article class="sa-panel"><header><div><h3 id="sa-user-growth-title">User Growth Trend</h3><p>Cumulative registered accounts · last six months</p></div><a href="/super-admin/users">Manage users</a></header>
        <x-dashboard-skeleton type="chart" x-show="loading" />
        <p x-show="!loading && data && !data.userGrowth.length" x-cloak>No monthly user data yet.</p>
        <figure class="sa-user-growth" aria-labelledby="sa-user-growth-title" x-show="!loading && data?.userGrowth?.length" x-cloak>
            <div class="sa-trend-axis" aria-hidden="true"><template x-for="tick in [0, 1, 2, 3, 4]" :key="tick"><span :style="`top:${trendY(trendMax() * tick / 4) / 2}%`" x-text="trendMax() * tick / 4"></span></template></div>
            <div class="sa-trend-plot">
                <svg class="sa-trend-svg" viewBox="0 0 600 200" preserveAspectRatio="none" role="img" :aria-label="`User growth: ${(data?.userGrowth || []).map(month => `${month.month}: ${month.count} users`).join(', ')}`">
                    @foreach ([0, 1, 2, 3, 4] as $tick)
                    <line x1="0" x2="600" :y1="trendY(trendMax() * {{ $tick }} / 4)" :y2="trendY(trendMax() * {{ $tick }} / 4)" stroke="#e2e8f0" vector-effect="non-scaling-stroke" />
                    @endforeach
                    <polyline class="sa-trend-line" :points="trendPoints()" fill="none" stroke="#00205b" stroke-width="2.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <template x-for="(month, index) in data?.userGrowth || []" :key="month.month"><button type="button" class="sa-trend-point" :class="{'is-first': index === 0, 'is-last': index === data.userGrowth.length - 1}" :style="`left:${trendX(index) / 6}%; top:${trendY(month.count) / 2}%`" :aria-label="`${month.month}: ${month.count} users`"><span class="sa-trend-dot" aria-hidden="true"></span><span class="sa-trend-tooltip" aria-hidden="true" x-text="`${month.month}: ${month.count} users`"></span></button></template>
                <div class="sa-trend-labels" aria-hidden="true"><template x-for="(month, index) in data?.userGrowth || []" :key="month.month"><span :style="`left:${trendX(index) / 6}%`" :title="month.month" x-text="month.month.split(' ')[0]"></span></template></div>
            </div>
        </figure>
    </article>
    <article class="sa-panel sa-health" :aria-busy="healthLoading"><header><div><h3>System Health</h3><p>Refreshes automatically every minute</p></div><button type="button" class="sa-health-refresh" @click="loadHealth()" :disabled="healthLoading" aria-label="Refresh system health"><i data-lucide="refresh-cw" :class="{'is-spinning': healthLoading}" aria-hidden="true"></i></button></header>
        <p class="sa-error" role="alert" x-show="healthError" x-cloak x-text="healthError"></p>
        <x-dashboard-skeleton type="health" x-show="healthLoading" />
        <dl class="system-health-list" x-show="!healthLoading" x-cloak><template x-for="service in health?.components || []" :key="service.name"><div class="system-health-row"><dt x-text="service.name"></dt><dd class="system-health-status" :class="{'is-operational': service.status === 'Operational', 'is-unavailable': service.status === 'Unavailable', 'is-not-configured': ['Not Configured', 'Unknown'].includes(service.status), 'is-configured': service.status === 'Configured'}"><span class="system-health-dot" aria-hidden="true"></span><span x-text="service.status"></span></dd></div></template></dl>
        <p class="sr-only" role="status" x-show="healthLoading">Checking system services…</p>
        <p class="sa-health-message" x-show="!healthLoading && !healthError && !health?.components?.length" x-cloak>No system health data available.</p>
        <p class="sa-health-note" x-show="health?.components?.some(service => service.name === 'FCM Push Service' && service.status === 'Not Configured')" x-cloak>FCM push is not configured. In-app realtime notifications use the existing polling service.</p>
        <p class="sa-health-updated" x-show="health?.checkedAt" x-cloak x-text="health ? `Updated ${date(health.checkedAt)}` : ''"></p>
    </article>
</section>
@endsection
