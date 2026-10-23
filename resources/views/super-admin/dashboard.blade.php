@extends('layouts.app')
@section('title', 'Super Admin Dashboard')
@section('heading', 'Compliance Overview')
@section('content')
<section class="app-view-panel active super-admin-dashboard" x-data="dashboardPage({ roleId: 0 })" x-init="load()">
    <template x-if="error"><div class="dashboard-error" role="alert"><span class="dashboard-error-text" x-text="error"></span><div class="dashboard-error-actions"><button type="button" class="btn-error-retry" @click="load()">Retry</button></div></div></template>

    <div class="stat-cards-grid">
        <article class="metric-stat-card"><div class="metric-info"><span class="metric-label">Total Users</span><span class="metric-number color-purple" x-text="loading ? '—' : (stats.totalUsers ?? 0)"></span></div><div class="metric-icon-box purple"><i data-lucide="users"></i></div></article>
        <article class="metric-stat-card"><div class="metric-info"><span class="metric-label">Total Departments</span><span class="metric-number color-slate" x-text="loading ? '—' : (stats.totalDepts ?? 0)"></span></div><div class="metric-icon-box slate"><i data-lucide="building-2"></i></div></article>
        <article class="metric-stat-card"><div class="metric-info"><span class="metric-label">Storage Used</span><span class="metric-number color-dark" x-text="loading ? '—' : `${stats.storageUsedGb ?? 0} GB`"></span></div><div class="metric-icon-box blue"><i data-lucide="hard-drive"></i></div></article>
        <article class="metric-stat-card"><div class="metric-info"><span class="metric-label">Open CARs</span><span class="metric-number color-danger" x-text="loading ? '—' : (stats.openCars ?? 0)"></span></div><div class="metric-icon-box danger"><i data-lucide="triangle-alert"></i></div></article>
    </div>

    <div class="charts-grid-row">
        <article class="chart-card"><div class="chart-header-flex"><h3 class="chart-card-title">Compliance per Department</h3><div class="chart-filter-tabs" role="group" aria-label="Department compliance order"><button type="button" :class="{ 'active': departmentComplianceView === 'most' }" @click="departmentComplianceView = 'most'">Most Compliant</button><button type="button" :class="{ 'active': departmentComplianceView === 'least' }" @click="departmentComplianceView = 'least'">Least Compliant</button><button type="button" :class="{ 'active': departmentComplianceView === 'all' }" @click="departmentComplianceView = 'all'">All Departments</button></div></div>
            <div class="chart-canvas-container super-admin-chart" :class="{ 'is-scrollable': departmentComplianceItems().length > 5 }" x-show="!loading && reportData?.deptCompliance?.length">
                <template x-for="item in departmentComplianceItems()" :key="item.departmentCode"><div class="super-admin-bar-item"><span class="super-admin-bar-value" x-text="`${item.compliancePercentage}%`"></span><div class="super-admin-bar-track"><span :style="`height:${Math.max(3, Math.min(100, item.compliancePercentage))}%`"></span></div><span class="super-admin-bar-label" x-text="item.departmentCode"></span></div></template>
            </div>
            <div class="chart-canvas-container chart-empty" x-show="!loading && !reportData?.deptCompliance?.length">No department compliance data yet.</div>
            <div class="chart-canvas-container chart-skeleton" x-show="loading" aria-label="Loading department chart"></div>
        </article>
        <article class="chart-card"><div class="chart-header-flex"><h3 class="chart-card-title">Monthly Compliance Trend <span class="chart-range-readout" x-show="reportData?.userGrowth?.length" x-text="`(0 → ${userTrendMax(reportData?.userGrowth || [])})`"></span></h3><span class="chart-trend-readout">Users · Last six months</span></div>
            <div class="chart-canvas-container super-admin-trend" x-show="!loading && reportData?.userGrowth?.length">
                <svg class="super-admin-trend-svg" viewBox="0 0 420 180" preserveAspectRatio="none" aria-hidden="true">
                    <line x1="0" :y1="trendY(0, reportData?.userGrowth || [])" x2="420" :y2="trendY(0, reportData?.userGrowth || [])" stroke="#cbd5e1" stroke-width="1.5"/>
                    <line x1="0" :y1="trendY(userTrendMax(reportData?.userGrowth || []) * 0.25, reportData?.userGrowth || [])" x2="420" :y2="trendY(userTrendMax(reportData?.userGrowth || []) * 0.25, reportData?.userGrowth || [])" stroke="#cbd5e1" stroke-width="1.5"/>
                    <line x1="0" :y1="trendY(userTrendMax(reportData?.userGrowth || []) * 0.5, reportData?.userGrowth || [])" x2="420" :y2="trendY(userTrendMax(reportData?.userGrowth || []) * 0.5, reportData?.userGrowth || [])" stroke="#cbd5e1" stroke-width="1.5"/>
                    <line x1="0" :y1="trendY(userTrendMax(reportData?.userGrowth || []) * 0.75, reportData?.userGrowth || [])" x2="420" :y2="trendY(userTrendMax(reportData?.userGrowth || []) * 0.75, reportData?.userGrowth || [])" stroke="#cbd5e1" stroke-width="1.5"/>
                    <line x1="0" :y1="trendY(userTrendMax(reportData?.userGrowth || []), reportData?.userGrowth || [])" x2="420" :y2="trendY(userTrendMax(reportData?.userGrowth || []), reportData?.userGrowth || [])" stroke="#cbd5e1" stroke-width="1.5"/>
                    <template x-for="tick in userTrendTicks(reportData?.userGrowth || [])" :key="tick"><text x="3" :y="trendY(tick, reportData?.userGrowth || []) - 3" fill="#475569" font-size="10" x-text="Math.round(tick)"></text></template>
                    <polyline :points="trendLinePoints(reportData?.userGrowth || [])" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <template x-for="(month, index) in reportData?.userGrowth || []" :key="month.month"><circle :cx="trendX(index, (reportData?.userGrowth || []).length)" :cy="trendY(month.count, reportData?.userGrowth || [])" r="4" fill="#2563eb" stroke="#fff" stroke-width="2" :title="`${month.count} users`"/></template>
                </svg>
                <template x-for="month in reportData?.userGrowth || []" :key="month.month"><div class="super-admin-trend-item"><span class="super-admin-trend-label" x-text="month.month"></span></div></template>
            </div>
            <div class="chart-canvas-container chart-empty" x-show="!loading && !reportData?.userGrowth?.length">No monthly user data yet.</div>
            <div class="chart-canvas-container chart-skeleton" x-show="loading" aria-label="Loading trend chart"></div>
        </article>
    </div>

    <article class="content-card">
        <div class="card-head-row"><h3 class="card-title-header">Recent Non-Compliant Findings</h3></div>
        <div class="responsive-table-wrapper">
            <table class="data-table"><thead><tr><th>Department Office</th><th>Auditee Assigned</th><th>Date Logged</th><th>Status</th></tr></thead>
                <tbody>
                    <template x-if="loading"><tr><td colspan="4" class="empty-cell">Loading findings…</td></tr></template>
                    <template x-if="!loading && recentChecks.length === 0"><tr><td colspan="4" class="empty-cell">No non-compliant findings have been recorded.</td></tr></template>
                    <template x-for="check in recentChecks" :key="check._id"><tr><td class="td-strong" x-text="check.departmentName || '—'"></td><td x-text="check.auditeeName || '—'"></td><td x-text="check.createdAt ? new Date(check.createdAt).toLocaleDateString() : '—'"></td><td><span class="badge-status" :class="check.correctiveStatus" x-text="(check.correctiveStatus || 'open').replace('_', ' ')"></span></td></tr></template>
                </tbody>
            </table>
        </div>
    </article>
</section>
@endsection
