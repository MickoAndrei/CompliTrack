@extends('layouts.app')
@section('title', 'Reports')
@section('heading', 'Compliance Reports')
@section('content')
<section class="view-section active" x-data="dashboardPage({ roleId: 1, heading: 'Compliance Reports', subheading: 'CET compliance performance and corrective-action trends.' })" x-init="load()">
    <div class="report-summary-grid stats-grid">
        <article class="stat-card"><span class="stat-icon blue"><i data-lucide="clipboard-check"></i></span><div class="stat-info"><h3 x-text="loading ? '—' : (stats.totalChecks ?? 0)"></h3><p>Checks reviewed</p></div></article>
        <article class="stat-card"><span class="stat-icon green"><i data-lucide="chart-no-axes-combined"></i></span><div class="stat-info"><h3 x-text="loading ? '—' : `${stats.complianceRate ?? 0}%`"></h3><p>Compliance rate</p></div></article>
        <article class="stat-card"><span class="stat-icon amber"><i data-lucide="circle-alert"></i></span><div class="stat-info"><h3 x-text="loading ? '—' : (stats.openCars ?? 0)"></h3><p>Open corrective actions</p></div></article>
        <article class="stat-card"><span class="stat-icon purple"><i data-lucide="building-2"></i></span><div class="stat-info"><h3 x-text="loading ? '—' : (stats.totalDepts ?? 0)"></h3><p>Departments covered</p></div></article>
    </div>
    <template x-if="error"><div class="card report-error" role="alert"><p x-text="error"></p><button type="button" class="btn-gold" @click="load()">Retry report</button></div></template>
    <div class="charts-grid report-charts">
        <article class="card chart-card"><div class="card-header"><h4>Compliance by Department</h4><span class="muted-label">Current compliance rate</span></div>
            <div class="report-bars" x-show="!loading && reportData?.deptCompliance?.length">
                <template x-for="department in reportData?.deptCompliance || []" :key="department.departmentCode">
                    <div class="report-bar-row"><div class="report-bar-label"><span x-text="department.departmentName"></span><strong x-text="`${department.compliancePercentage}%`"></strong></div><div class="report-bar-track"><span :style="`width: ${Math.min(100, department.compliancePercentage)}%`"></span></div></div>
                </template>
            </div>
            <p x-show="!loading && !reportData?.deptCompliance?.length" class="empty-report">No department results are available yet.</p>
            <div x-show="loading" class="report-loading" aria-label="Loading report"></div>
        </article>
        <article class="card chart-card"><div class="card-header"><h4>Monthly Compliance Trend</h4><span class="muted-label">Last six months</span></div>
            <div class="report-trend" x-show="!loading && reportData?.monthlyComplianceTrend?.length">
                <template x-for="month in reportData?.monthlyComplianceTrend || []" :key="month.month">
                    <div class="report-trend-column"><strong x-text="`${month.compliancePercentage}%`"></strong><span class="report-trend-bar" :style="`height: ${Math.max(2, Math.min(100, month.compliancePercentage))}%`"></span><small x-text="month.month"></small></div>
                </template>
            </div>
            <p x-show="!loading && !reportData?.monthlyComplianceTrend?.length" class="empty-report">No monthly results are available yet.</p>
            <div x-show="loading" class="report-loading" aria-label="Loading report"></div>
        </article>
    </div>
</section>
@endsection
