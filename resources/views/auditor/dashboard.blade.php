@extends('layouts.app')
@section('title', 'Auditor Dashboard')
@section('heading', 'Auditor Dashboard')
@section('content')
<section class="auditor-dashboard" x-data="auditorOverview()" x-init="load()" :aria-busy="loading">
    <div x-page-notice x-cloak x-show="error" class="auditor-load-error" role="alert"><span x-text="error"></span><button @click="load()">Retry</button></div>
    <div class="auditor-summary-grid">
        @foreach([['Total Checks', 'totalChecks', 'clipboard-check', ''], ['Compliant', 'compliantChecks', 'circle-check', 'auditor-green'], ['Pending Checks', 'pendingChecks', 'clock-3', ''], ['Nonconformities', 'nonconformities', 'circle-alert', 'auditor-red'], ['Open CARs', 'openCars', 'triangle-alert', 'auditor-red'], ['Closed CARs', 'closedCars', 'check-check', 'auditor-green']] as [$label, $key, $icon, $iconClass])
        <article class="auditor-summary-card"><div><h3>{{ $label }}</h3><i data-lucide="{{ $icon }}" class="{{ $iconClass }}" aria-hidden="true"></i></div><strong x-text="loading ? '—' : (stats.{{ $key }} ?? 0)"></strong></article>
        @endforeach
    </div>
    <section class="auditor-assigned-section">
        <div class="auditor-assigned-heading"><h2>My Assigned Audits</h2><i data-lucide="clipboard-list" aria-hidden="true"></i></div>
        <p x-show="loading">Loading assigned audits…</p><p x-cloak x-show="!loading && !error && !assignments.length">No assigned audits yet. Your audits will appear after an authorized assignment.</p><template x-for="assignment in assignments" :key="assignment.id"><a :href="`/audits/${assignment.id}`" class="auditor-assignment-preview"><strong x-text="`${assignment.code} · ${assignment.title}`"></strong><span x-text="`${assignment.department} · ${assignment.status} · ${assignment.progress.percent}% evaluated`"></span></a></template>
        <div class="auditor-assigned-actions" aria-label="Assigned audit stages"><a href="/audits">View Assigned Audits</a><a href="/auditor/checks">Compliance Checks</a><a href="/auditor/corrective-actions">Corrective Actions</a></div>
    </section>
    <div class="auditor-insights-grid">
        <article class="auditor-insight-card"><h3>N / C / O Overview</h3>
            <div class="auditor-compliance-chart">
                <div class="auditor-donut" :style="(stats.totalChecks || 0) ? `background: conic-gradient(#10b981 0% ${(stats.compliantChecks || 0) / stats.totalChecks * 100}%, #ef4444 ${(stats.compliantChecks || 0) / stats.totalChecks * 100}% ${classifiedTotal / stats.totalChecks * 100}%, #f5b942 ${classifiedTotal / stats.totalChecks * 100}% 100%)` : 'background: #cbd5e1'" role="img" :aria-label="`${stats.compliantChecks || 0} compliant, ${stats.nonCompliantChecks || 0} non-conforming, ${Math.max(0, (stats.totalChecks || 0) - classifiedTotal)} observations`"><div><strong x-text="loading ? '—' : (stats.totalChecks || 0)"></strong></div></div>
                <div class="auditor-chart-legend"><p><span class="auditor-legend-dot compliant"></span><span x-text="`C · ${stats.compliantChecks || 0}`"></span></p><p><span class="auditor-legend-dot noncompliant"></span><span x-text="`N · ${stats.nonCompliantChecks || 0}`"></span></p><p><span class="auditor-legend-dot observation"></span><span x-text="`O · ${Math.max(0, (stats.totalChecks || 0) - classifiedTotal)}`"></span></p></div>
            </div>
        </article>
        <article class="auditor-insight-card"><h3>Recent Activity</h3><p class="auditor-activity-description">Checklist submissions, finding updates, and CAR activity</p><p x-show="loading" class="auditor-empty">Loading activity…</p><p x-cloak x-show="!loading && !changes.length" class="auditor-empty">No recent activity yet.</p><template x-for="change in changes" :key="change.key"><a :href="change.url" class="auditor-status-row"><span class="auditor-status-dot"></span><span class="auditor-status-copy"><span x-text="`${change.code} ${change.label}`"></span><time x-text="new Date(change.at).toLocaleString()"></time></span><span class="auditor-status-badge" :class="change.status" x-text="change.status.replaceAll('_', ' ')"></span></a></template></article>
    </div>
</section>
@endsection
