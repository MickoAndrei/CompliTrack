@extends('layouts.app')
@section('title', 'Reports')
@section('heading', 'Compliance Reports')
@section('content')
<section class="admin-dashboard admin-reports view-section active" x-data="dashboardPage({ roleId: 1, heading: 'Compliance Reports', subheading: 'CET compliance performance and corrective-action trends.' })" x-init="load()">
    <p class="atl-muted">These reports summarize recorded compliance checks and CARs. <a href="/admin/dashboard#atl-compliance-title">View accepted audit checklist compliance on the ATL dashboard.</a></p>
    <div class="report-summary-grid stats-grid">
        @foreach([['totalChecks','Checks reviewed','clipboard-check'], ['complianceRate','Check compliance rate','chart-no-axes-combined'], ['openCars','Open corrective actions','circle-alert'], ['totalDepts','Departments covered','building-2']] as [$key,$label,$icon])
        <article class="stat-card"><div class="admin-stat-heading"><h3>{{ $label }}</h3><span class="stat-icon {{ $key === 'openCars' ? 'amber' : 'blue' }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span></div><strong class="admin-stat-number" x-text="loading ? '—' : {{ $key === 'complianceRate' ? '(stats.totalChecks ? `${stats.complianceRate ?? 0}%` : \'Not Yet Assessed\')' : '(stats.'.$key.' ?? 0)' }}"></strong></article>
        @endforeach
    </div>
    <template x-if="error"><div x-page-notice class="card report-error" role="alert"><p x-text="error"></p><button type="button" class="btn-gold" @click="load()">Retry report</button></div></template>
    <div class="charts-grid report-charts">
        <article class="card chart-card"><div class="card-header"><h4>Recorded Check Compliance by Department</h4><span class="muted-label">Recorded compliance checks</span></div>
            <div class="report-bars" x-show="!loading && reportData?.deptCompliance?.length">
                <template x-for="department in reportData?.deptCompliance || []" :key="department.departmentCode">
                    <div class="report-bar-row"><div class="report-bar-label"><span x-text="department.departmentName"></span><strong x-text="department.assessedChecks === 0 ? 'Not Yet Assessed' : `${department.compliancePercentage}%`"></strong></div><div class="report-bar-track"><span :style="`width: ${Math.min(100, department.compliancePercentage)}%`"></span></div></div>
                </template>
            </div>
            <p x-show="!loading && !reportData?.deptCompliance?.length" class="empty-report">No department results are available yet.</p>
            <div x-show="loading" class="report-loading" aria-label="Loading report"></div>
        </article>
        <article class="card chart-card"><div class="card-header"><h4>Monthly Compliance Trend</h4><span class="muted-label">Last six months</span></div>
            <div class="report-trend" x-show="!loading && reportData?.monthlyComplianceTrend?.length">
                <template x-for="month in reportData?.monthlyComplianceTrend || []" :key="month.month">
                    <div class="report-trend-column"><strong x-text="month.assessedChecks === 0 ? 'Not Yet Assessed' : `${month.compliancePercentage}%`"></strong><span class="report-trend-bar" :style="`height: ${month.assessedChecks === 0 ? 0 : Math.max(2, Math.min(100, month.compliancePercentage))}%`"></span><small x-text="month.month"></small></div>
                </template>
            </div>
            <p x-show="!loading && !reportData?.monthlyComplianceTrend?.length" class="empty-report">No monthly results are available yet.</p>
            <div x-show="loading" class="report-loading" aria-label="Loading report"></div>
        </article>
    </div>
</section>
@endsection
