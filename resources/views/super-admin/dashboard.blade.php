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
        <article class="chart-card"><div class="chart-header-flex"><h3 class="chart-card-title">Compliance per Department</h3><span class="chart-filter-hint">CET departments</span></div>
            <div class="chart-canvas-container super-admin-chart" x-show="!loading && reportData?.deptCompliance?.length">
                <template x-for="(item, index) in reportData?.deptCompliance || []" :key="item.departmentCode"><div class="super-admin-bar-item"><span class="super-admin-bar-value" x-text="`${item.compliancePercentage}%`"></span><div class="super-admin-bar-track"><span :style="`height:${Math.max(3, Math.min(100, item.compliancePercentage))}%`"></span></div><span class="super-admin-bar-label" x-text="item.departmentCode"></span></div></template>
            </div>
            <div class="chart-canvas-container chart-empty" x-show="!loading && !reportData?.deptCompliance?.length">No department compliance data yet.</div>
            <div class="chart-canvas-container chart-skeleton" x-show="loading" aria-label="Loading department chart"></div>
        </article>
        <article class="chart-card"><div class="chart-header-flex"><h3 class="chart-card-title">Monthly Compliance Trend</h3><span class="chart-trend-readout">Last six months</span></div>
            <div class="chart-canvas-container super-admin-trend" x-show="!loading && reportData?.monthlyComplianceTrend?.length">
                <svg class="super-admin-trend-svg" viewBox="0 0 420 180" preserveAspectRatio="none" aria-hidden="true"><polyline :points="trendLinePoints(reportData?.monthlyComplianceTrend || [])" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <template x-for="month in reportData?.monthlyComplianceTrend || []" :key="month.month"><div class="super-admin-trend-item"><span class="super-admin-trend-point" :style="`bottom:calc(26px + ${Math.max(0, Math.min(100, month.compliancePercentage)) * 1.35}px)`" :title="`${month.compliancePercentage}%`"></span><span class="super-admin-trend-label" x-text="month.month"></span></div></template>
            </div>
            <div class="chart-canvas-container chart-empty" x-show="!loading && !reportData?.monthlyComplianceTrend?.length">No monthly compliance data yet.</div>
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
