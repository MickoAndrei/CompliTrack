@props(['roleId', 'heading' => null, 'subheading' => null])
@php($panelClass = match ((int) $roleId) { 0 => 'app-view-panel active', 1 => 'view-section active', 2, 3 => 'view-panel active', default => '' })
<section class="{{ $panelClass }}" x-data="dashboardPage({ roleId: {{ (int) $roleId }}, heading: @js($heading), subheading: @js($subheading) })" x-init="load()">
    <template x-if="error">
        <div class="mb-5">
            <x-alert-banner type="error"><span x-text="error"></span></x-alert-banner>
            <button type="button" @click="load()" class="mt-3 rounded-xl bg-ustp-navy px-4 py-2 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy">Retry</button>
        </div>
    </template>
    <template x-if="roleId === 3 && !loading && stats.openOverSevenDays > 0">
        <div class="mb-5"><x-alert-banner type="error"><span x-text="`${stats.openOverSevenDays} open finding${stats.openOverSevenDays === 1 ? '' : 's'} overdue by more than 7 days. Please upload corrective evidence.`"></span></x-alert-banner></div>
    </template>
    <div class="{{ match ((int) $roleId) { 0 => 'stat-cards-grid', 1 => 'stats-grid', 2, 3 => 'kpi-grid', default => 'grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4' } }}">
        <template x-for="card in cards" :key="card.key">
            <article class="{{ match ((int) $roleId) { 0 => 'metric-stat-card', 1 => 'stat-card', 2, 3 => 'kpi-card', default => 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70' } }}">
                @if ((int) $roleId === 1)
                    <span class="stat-icon blue"><i :data-lucide="card.icon"></i></span>
                    <div class="stat-info"><h3 x-text="loading ? '—' : (stats[card.key] ?? 0)"></h3><p x-text="card.label"></p></div>
                @elseif ((int) $roleId === 0)
                    <div class="metric-info"><span class="metric-label" x-text="card.label"></span><span class="metric-number color-purple" x-text="loading ? '—' : (stats[card.key] ?? 0)"></span></div>
                    <div class="metric-icon-box purple"><i :data-lucide="card.icon"></i></div>
                @else
                    <div class="kpi-header">
                        <span class="kpi-label" x-text="card.label"></span>
                        <span class="kpi-icon-box kpi-icon-badge"><i :data-lucide="card.icon"></i></span>
                    </div>
                    <div class="kpi-number" x-text="loading ? '—' : (stats[card.key] ?? 0)"></div>
                @endif
            </article>
        </template>
    </div>
    <div x-show="roleId === 3 && !loading" class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        <div class="flex items-center justify-between"><div><p class="text-sm text-ustp-muted">Personal compliance rate</p><p class="mt-1 font-heading text-3xl font-bold text-ustp-navy" x-text="`${stats.complianceRate || 0}%`"></p></div><i data-lucide="chart-no-axes-column-increasing" class="size-8 text-ustp-gold"></i></div>
        <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-ustp-navy transition-all" :style="`width: ${Math.min(100, stats.complianceRate || 0)}%`"></div></div>
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="{{ in_array((int) $roleId, [0, 3], true) ? 'content-card' : (in_array((int) $roleId, [1, 2], true) ? 'card' : 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70') }}">
            <div class="mb-4 flex items-center justify-between">
                <div><h3 class="font-heading text-lg font-semibold text-ustp-dark">Recent compliance checks</h3><p class="text-sm text-ustp-muted">Latest activity available to your role</p></div>
                <a :href="checksUrl" class="text-sm font-semibold text-ustp-navy hover:underline">View all</a>
            </div>
            <template x-if="loading"><div class="h-24 animate-pulse rounded-xl bg-slate-100"></div></template>
            <template x-if="!loading && recentChecks.length === 0"><p class="rounded-xl bg-ustp-light p-5 text-center text-sm text-ustp-muted">No records yet.</p></template>
            <div x-show="!loading && recentChecks.length" class="{{ (int) $roleId === 2 ? 'table-container' : 'overflow-x-auto' }}">
                <table class="min-w-full text-left text-sm {{ (int) $roleId === 0 || (int) $roleId === 3 ? 'data-table' : ((int) $roleId === 2 ? 'compliance-table' : '') }}">
                    <thead class="text-xs uppercase text-ustp-muted"><tr><th class="py-2 pr-4">Check</th><th class="py-2 pr-4">Department</th><th class="py-2 pr-4">Result</th><th class="py-2">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="check in recentChecks" :key="check._id"><tr><td class="py-3 pr-4 font-medium" x-text="check.checkCode"></td><td class="py-3 pr-4" x-text="check.departmentName || '—'"></td><td class="py-3 pr-4 capitalize" x-text="(check.result || '').replace('_', ' ')"></td><td class="py-3 capitalize" x-text="(check.correctiveStatus || '—').replace('_', ' ')"></td></tr></template>
                    </tbody>
                </table>
            </div>
        </article>
        <article class="{{ in_array((int) $roleId, [0, 3], true) ? 'content-card' : (in_array((int) $roleId, [1, 2], true) ? 'card' : 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70') }}">
            <h3 class="font-heading text-lg font-semibold text-ustp-dark">Quick actions</h3>
            <p class="mt-1 text-sm text-ustp-muted">Go directly to common tasks.</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <template x-for="action in actions" :key="action.href"><a :href="action.href" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-ustp-navy hover:bg-ustp-light"><i :data-lucide="action.icon" class="size-5 text-ustp-navy"></i><span class="text-sm font-semibold" x-text="action.label"></span></a></template>
            </div>
        </article>
    </div>
    <div x-show="reportData && [0, 1].includes(roleId)" class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
            <h3 class="font-heading text-lg font-semibold">Compliance by department</h3>
            <template x-if="reportData?.deptCompliance?.length">
                <div class="mt-4 space-y-4">
                    <template x-for="item in reportData.deptCompliance" :key="item.departmentCode">
                        <div><div class="mb-1 flex justify-between gap-4 text-sm"><span x-text="item.departmentName"></span><span class="font-semibold" x-text="`${item.compliancePercentage}%`"></span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-ustp-navy" :style="`width: ${Math.min(100, item.compliancePercentage)}%`"></div></div></div>
                    </template>
                </div>
            </template>
            <p x-show="!reportData?.deptCompliance?.length" class="mt-4 text-sm text-ustp-muted">No department compliance data yet.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
            <h3 class="font-heading text-lg font-semibold">Monthly compliance trend</h3>
            <template x-if="reportData?.monthlyComplianceTrend?.length">
                <div class="mt-4 flex h-44 items-end gap-3 border-b border-slate-100 pb-2">
                    <template x-for="month in reportData.monthlyComplianceTrend" :key="month.month">
                        <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"><div class="w-full max-w-10 rounded-t-lg bg-ustp-gold" :style="`height: ${Math.max(4, Math.min(100, month.compliancePercentage))}%`" :title="`${month.compliancePercentage}%`"></div><span class="text-xs text-ustp-muted" x-text="month.month"></span></div>
                    </template>
                </div>
            </template>
            <p x-show="!reportData?.monthlyComplianceTrend?.length" class="mt-4 text-sm text-ustp-muted">No trend data available.</p>
        </article>
    </div>
</section>
