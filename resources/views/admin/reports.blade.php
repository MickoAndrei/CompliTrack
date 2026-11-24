@extends('layouts.app')
@section('title', 'Annual IQA Reports')
@section('heading', 'Annual IQA Reports')
@push('styles')
<style>

.annual-report{display:grid;gap:8px;min-width:0;color:#334155}
.annual-report .ar-toolbar{display:flex;align-items:flex-end;gap:12px;min-width:0;margin:0;padding:0}
.annual-report .ar-toolbar label{display:flex;flex-direction:column;gap:6px;margin:0;min-width:0;font-size:13px;font-weight:600}
.annual-report .ar-year{flex:0 0 112px}.annual-report .ar-plan{flex:1 1 180px}
.annual-report .ar-toolbar input,.annual-report .ar-toolbar select{width:100%;min-width:0;height:40px;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff;color:#334155;font-size:13px}
.annual-report .ar-actions{display:flex;align-items:center;gap:12px;flex-shrink:0}
.annual-report .ar-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:40px;box-sizing:border-box;padding:0 15px;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#00205b;font-size:13px;font-weight:600;white-space:nowrap;text-decoration:none;cursor:pointer}
.annual-report .ar-button svg{width:16px;height:16px}.annual-report .ar-button:hover{background:#f8fafc}.annual-report .ar-export{background:#ffc72c;border-color:#eab308}.annual-report .ar-export:hover{background:#eab308}.annual-report .ar-button:disabled,.annual-report .ar-button[aria-disabled=true]{opacity:.5;cursor:not-allowed;pointer-events:none}
.annual-report .ar-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.annual-report .ar-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;min-width:0}.annual-report .ar-metric{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:80px}.annual-report .ar-metric strong{display:block;font-size:24px;color:#00205b;line-height:1.2}.annual-report .ar-metric small,.annual-report .ar-muted{font-size:12px;color:#64748b}.annual-report .ar-metric i,.annual-report .ar-metric svg{width:22px;height:22px;color:#00205b}.annual-report .ar-tabs{margin:4px 0}.annual-report .ar-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.annual-report .ar-wide{grid-column:1/-1}.annual-report h2{font-size:15px;font-weight:650;margin:0 0 8px;color:#00205b}.annual-report .ar-chart{display:flex;align-items:center;justify-content:center;gap:20px;min-height:116px}.annual-report .ar-ring{width:108px;height:108px;border-radius:50%;display:grid;place-items:center;flex:none}.annual-report .ar-ring-center{background:#fff;border-radius:50%;width:78px;height:78px;display:grid;align-content:center;text-align:center}.annual-report .ar-ring-center strong{font-size:20px;color:#00205b}.annual-report .ar-legend{display:grid;gap:7px;min-width:0;flex:1;max-width:220px;font-size:12px}.annual-report .ar-legend div,.annual-report .ar-unit-line{display:flex;align-items:center;gap:8px}.annual-report .ar-legend strong,.annual-report .ar-unit-line strong{margin-left:auto;white-space:nowrap}.annual-report .ar-dot{width:9px;height:9px;border-radius:3px;flex:none}.annual-report .ar-track{height:7px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin:4px 0 8px}.annual-report .ar-track span{display:block;height:100%;background:#16a34a}.annual-report .ar-unit-line{font-size:12px}.annual-report .ar-meta{font-size:12px;color:#64748b;display:grid;gap:3px}.annual-report .report-scroll{overflow:auto;max-width:100%}.annual-report table{width:100%;border-collapse:collapse;font-size:13px}.annual-report th,.annual-report td{padding:10px 12px;text-align:left;border-bottom:1px solid #e2e8f0;white-space:nowrap}.annual-report th:first-child,.annual-report td:first-child{padding-left:24px;white-space:normal}.annual-report th:last-child,.annual-report td:last-child{padding-right:24px}.annual-report thead th{background:#f8fafc;color:#475569;font-size:12px}.annual-report tbody th{font-weight:500}.annual-report .ar-table-card{padding:0;overflow:hidden}.annual-report .ar-table-card h2{padding:12px 24px;margin:0}.annual-report .ar-skeleton{height:100px;background:#e8edf4;border-radius:10px;animation:ar-pulse 1.5s ease-in-out infinite alternate}@keyframes ar-pulse{to{opacity:.5}}@media(prefers-reduced-motion:reduce){.annual-report .ar-skeleton{animation:none}}
@media(max-width:900px){.annual-report .ar-toolbar{flex-wrap:wrap}.annual-report .ar-actions{margin-left:auto;flex-wrap:wrap;justify-content:flex-end}}@media(max-width:600px){.annual-report .ar-metrics,.annual-report .ar-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.annual-report .ar-grid{grid-template-columns:1fr}.annual-report .ar-actions{width:100%;gap:8px}.annual-report .ar-card{padding:12px}.annual-report .ar-table-card{padding:0}}
@media print{body{height:auto!important;overflow:visible!important;background:white!important}.sidebar,.app-header,.annual-report form{display:none!important}.app-container:has(.annual-report),.main-wrapper:has(.annual-report){display:block!important;width:auto!important;height:auto!important;margin:0!important;overflow:visible!important}#main-content:has(.annual-report){height:auto!important;max-height:none!important;padding:0!important;overflow:visible!important}.annual-report .report-scroll{overflow:visible}.annual-report table{font-size:8pt}.annual-report td,.annual-report th{padding:3px;white-space:normal}.annual-report thead{display:table-header-group}.annual-report tr{break-inside:avoid}@page{size:A4 landscape;margin:10mm}}

@media print{.annual-report .ar-toolbar,.annual-report .ar-tabs,.annual-report .ar-metrics,.annual-report .ar-overview,.annual-report .ar-status{display:none!important}.annual-report .ar-report-section{display:block!important;margin-bottom:12px}.annual-report .ar-card{border:0;padding:0}.annual-report .ar-table-card h2{padding:6px 0}.annual-report .ar-meta{color:#000}.annual-report .report-scroll{overflow:visible!important}}

/* Report-only overrides: retain shared tabs, not the shared page shell. */
@media screen {
#main-content .annual-report.attention-page {display:grid;gap:8px;background:transparent;border:0;border-radius:0;padding:0;width:100%;min-width:0;container-type:inline-size;}
#main-content .annual-report .ar-metrics {gap:8px;margin:0;}
#main-content .annual-report .ar-metric {min-height:84px;padding:12px 16px;}
#main-content .annual-report .ar-metric-copy {min-width:0;display:grid;gap:4px;}
#main-content .annual-report .ar-metric-copy small {font-size:12px;line-height:1.35;}
#main-content .annual-report .ar-metric-copy strong {font-size:26px;line-height:1.1;font-weight:700;color:#0f172a;}
#main-content .annual-report .ar-metric-icon {width:40px;height:40px;display:grid;place-items:center;flex:0 0 40px;border-radius:10px;color:#2563eb;background:#eff6ff;}
#main-content .annual-report .ar-metric-icon svg {color:inherit;width:20px;height:20px;}
#main-content .annual-report [data-metric="accepted"] .ar-metric-icon,#main-content .annual-report [data-metric="rate"] .ar-metric-icon {color:#16a34a;background:#f0fdf4;}
#main-content .annual-report [data-metric="openCars"] .ar-metric-icon {color:#dc2626;background:#fef2f2;}
#main-content .annual-report .ar-tabs {display:flex;flex-wrap:wrap;gap:7px;margin:0;padding:4px 0;}
#main-content .annual-report .ar-tabs button {border:0;border-radius:999px;padding:8px 15px;background:transparent;color:#64748b;font-size:13px;font-weight:600;line-height:18px;white-space:nowrap;}
#main-content .annual-report .ar-tabs button:hover {background:#f8fafc;}
#main-content .annual-report .ar-tabs button.is-active {background:#eef2f7;color:#00205b;}
#main-content .annual-report .ar-toolbar {display:grid;grid-template-columns:112px minmax(0,1fr) max-content;align-items:end;gap:12px;margin:0;padding:0 0 4px;}
#main-content .annual-report .ar-toolbar label {display:grid;gap:6px;line-height:18px;}
#main-content .annual-report .ar-toolbar :is(input,select) {height:40px;margin:0;line-height:normal;}
#main-content .annual-report .ar-actions {display:flex;flex-wrap:nowrap;align-items:center;justify-content:flex-end;gap:12px;width:auto;margin:0;}
#main-content .annual-report .ar-button {height:40px;min-height:40px;margin:0;padding:0 14px;line-height:1;flex-shrink:0;}
#main-content .annual-report .ar-export {background:#ffc72c;color:#00205b;border-color:#eab308;}
#main-content .annual-report .ar-report-meta {display:flex;flex-wrap:wrap;align-items:center;gap:4px 14px;font-size:11px;line-height:1.4;}
#main-content .annual-report .ar-card h2 {font-size:15px;line-height:20px;font-weight:650;color:#0f172a;}
#main-content .annual-report .ar-chart {min-height:116px;gap:16px;}
#main-content .annual-report .ar-chart + p {margin:6px 0 0;}
#main-content .annual-report .ar-ring-center strong {line-height:1.15;}
#main-content .annual-report .ar-section-heading {display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;}
#main-content .annual-report .ar-section-heading h2 {margin:0;}
#main-content .annual-report .ar-unit-list {display:grid;gap:10px;}
#main-content .annual-report .ar-unit-line {display:grid;grid-template-columns:minmax(0, 43%) minmax(0,1fr) minmax(45px,auto);gap:12px;align-items:center;line-height:1.4;}
#main-content .annual-report .ar-unit-line .ar-track {margin:0;}
#main-content .annual-report .ar-unit-line strong {font-size:12px;text-align:right;white-space:normal;}
#main-content .annual-report .ar-car-number {font-size:22px;font-weight:700;line-height:1.25;color:#2563eb;}
#main-content .annual-report .ar-car-number[data-kind="overdueCars"] {color:#dc2626;}
#main-content .annual-report .ar-car-number[data-kind="awaitingVerificationCars"] {color:#d97706;}
#main-content .annual-report .ar-car-number[data-kind="closedCars"] {color:#16a34a;}
@container (max-width: 710px) {
#main-content .annual-report .ar-toolbar {grid-template-columns:112px minmax(0,1fr);}
#main-content .annual-report .ar-actions {grid-column:1/-1;flex-wrap:wrap;}
#main-content .annual-report .ar-metrics {grid-template-columns:repeat(2,minmax(0,1fr));}
#main-content .annual-report .ar-grid {grid-template-columns:minmax(0,1fr);}
}
@container (max-width: 390px) {
#main-content .annual-report .ar-toolbar {grid-template-columns:minmax(0,1fr);}
#main-content .annual-report .ar-actions {justify-content:flex-start;gap:8px;}
#main-content .annual-report .ar-chart {flex-wrap:wrap;}
#main-content .annual-report .ar-unit-line {grid-template-columns:minmax(0,1fr) auto;gap:5px;}
#main-content .annual-report .ar-unit-line .ar-track {grid-row:2;grid-column:1/-1;}
}
}
@media print {.annual-report.attention-page{padding:0;border:0;background:white;}}

/* Compact overview, scoped to this report. */
.ar-print-method {display:none;}
@media screen {
#main-content:has(.annual-report) {padding-top:8px;}
#main-content .annual-report .ar-metric {min-height:70px;padding:10px 14px;}
#main-content .annual-report .ar-tabs {gap:4px;padding:0;}
#main-content .annual-report .ar-tabs button {padding:6px 12px;}
#main-content .annual-report .ar-toolbar {gap:8px;padding:0;}
#main-content .annual-report .ar-actions {gap:8px;}
#main-content .annual-report .ar-table-card h2 {padding:10px 14px;}
#main-content .annual-report :is(th,td) {padding:8px 10px;}
#main-content .annual-report :is(th,td):first-child {padding-left:14px;}
#main-content .annual-report :is(th,td):last-child {padding-right:14px;}
#main-content .annual-report .ar-report-meta {position:relative;gap:5px 12px;line-height:1.35;}
#main-content .annual-report .ar-method {margin-left:auto;max-width:100%;}
#main-content .annual-report .ar-method summary {cursor:pointer;color:#00205b;font-weight:600;white-space:nowrap;}
#main-content .annual-report .ar-method[open] {flex-basis:100%;margin:0;padding:8px 12px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;}
#main-content .annual-report .ar-method-copy {margin-top:6px;line-height:1.5;}
#main-content .annual-report .ar-method p {margin:6px 0 0;}
#main-content .annual-report .ar-overview > .ar-card {padding:10px 14px;}
#main-content .annual-report .ar-overview h2 {margin-bottom:6px;}
#main-content .annual-report .ar-chart {min-height:94px;gap:16px;padding:0;}
#main-content .annual-report .ar-ring {width:92px;height:92px;}
#main-content .annual-report .ar-ring-center {width:68px;height:68px;}
#main-content .annual-report .ar-ring-center strong {font-size:18px;line-height:1.1;}
#main-content .annual-report .ar-ring-center small {font-size:10px;line-height:1.2;}
#main-content .annual-report .ar-legend {max-width:190px;gap:5px;}
#main-content .annual-report .ar-chart + p {margin:4px 0 0;font-size:11px;line-height:1.3;}
#main-content .annual-report .ar-overview {grid-template-columns:repeat(2,minmax(0,1fr));}
#main-content .annual-report .ar-comparison {grid-column:1/-1;}
#main-content .annual-report .ar-car-summary {grid-column:1/-1;}
#main-content .annual-report .ar-unit-list {gap:6px;}
#main-content .annual-report .ar-unit-line {grid-template-columns:minmax(0,38%) minmax(0,1fr) 115px;gap:10px;min-height:20px;}
#main-content .annual-report .ar-unit-line strong {margin:0;text-align:right;}
#main-content .annual-report .ar-unit-line .ar-unassessed {color:#64748b;font-weight:500;font-size:11px;}
#main-content .annual-report .ar-car-grid {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;}
#main-content .annual-report .ar-car-number {font-size:20px;}
@container (min-width: 960px) {
#main-content .annual-report .ar-comparison {grid-column:1;}
#main-content .annual-report .ar-car-summary {grid-column:2;}
#main-content .annual-report .ar-car-grid {grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
#main-content .annual-report .ar-car-grid > div {border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;}
#main-content .annual-report .ar-unit-line {grid-template-columns:minmax(0,46%) minmax(0,1fr) 100px;gap:8px;}
}
@container (max-width: 710px) {#main-content .annual-report .ar-overview {grid-template-columns:minmax(0,1fr);}}
@container (max-width: 390px) {#main-content .annual-report .ar-unit-line {grid-template-columns:minmax(0,1fr) auto;gap:4px;}#main-content .annual-report .ar-unit-line .ar-track {grid-row:2;grid-column:1/-1;}#main-content .annual-report .ar-car-grid {grid-template-columns:repeat(2,minmax(0,1fr));}#main-content .annual-report .ar-method {margin-left:0;}}
}
@media print {.annual-report .ar-method {display:none!important;}.annual-report .ar-print-method {display:block!important;width:100%;}#main-content .annual-report :is(th,td) {padding:3px!important;}}

</style>
@endpush
@section('content')
<section class="annual-report attention-page" x-data="annualReport" :aria-busy="loading">
    <div class="ar-metrics">
        @foreach ([['planned','Planned Audits','clipboard-list'],['accepted','Completed Audits','clipboard-check'],['rate','Compliance Rate','shield-check'],['openCars','Open CARs','triangle-alert']] as [$key,$label,$icon])
        <article class="ar-card ar-metric" data-metric="{{ $key }}"><div class="ar-metric-copy"><small>{{ $label }}</small><strong x-text="loading || error || !current ? '—' : {{ $key === 'rate' ? 'rate' : 'totals.'.$key }}"></strong></div><span class="ar-metric-icon"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span></article>
        @endforeach
    </div>
    <div class="attention-tabs ar-tabs" role="group" aria-label="Report sections">
        @foreach (['overview'=>'Overview','lifecycle'=>'Audit Lifecycle','compliance'=>'Compliance Results','cars'=>'Corrective Actions','legacy'=>'Legacy Checks'] as $key=>$label)
        <button type="button" @click="tab = '{{ $key }}'" :class="{'is-active': tab === '{{ $key }}'}" :aria-pressed="tab === '{{ $key }}'">{{ $label }}</button>
        @endforeach
    </div>
    <form class="ar-toolbar" @submit.prevent="load()">
        <label class="ar-year">Audit year<input type="number" min="2000" max="2200" required x-model="year" @change="changeYear()"></label>
        <label class="ar-plan">Audit plan<select x-model="planId" @change="load()" :disabled="loading || !validYear"><option value="">All authorized plans</option><template x-for="plan in report?.plans || []" :key="plan.id"><option :value="plan.id" x-text="plan.title"></option></template></select></label>
        <div class="ar-actions">
            <button class="ar-button" type="button" @click="clearFilters()"><i data-lucide="filter-x" aria-hidden="true"></i>Remove Filters</button>
            <button class="ar-button" type="button" @click="window.print()" :disabled="loading || !!error || !current"><i data-lucide="printer" aria-hidden="true"></i>Print</button>
            <a class="ar-button ar-export" :href="!loading && !error && current ? csvUrl : null" :aria-disabled="loading || !!error || !current" :tabindex="loading || error || !current ? -1 : 0" @click="if (loading || error || !current) $event.preventDefault()"><i data-lucide="download" aria-hidden="true"></i>Export CSV</a>
        </div>
    </form>
    <div class="ar-status ar-grid" x-show="loading" role="status" aria-label="Loading annual report"><div class="ar-skeleton"></div><div class="ar-skeleton"></div></div>
    <p class="ar-status ar-muted" x-cloak x-show="!current && !loading && !error">Change or confirm the audit year to refresh the report.</p>
    <div class="ar-status" role="alert" x-cloak x-show="error"><span x-text="error"></span> <button class="ar-button" type="button" @click="load()">Retry</button></div>
    <template x-if="report && !loading && !error && current"><div style="display:grid;gap:8px">
        <div class="ar-meta ar-report-meta"><strong x-text="`Annual IQA report · ${report.year}`"></strong><span x-text="`Generated ${date(report.generatedAt)}`"></span><span x-text="report.planId ? 'Audit plan: ' + (report.plans.find(plan => plan.id === report.planId)?.title || report.planId) : 'Audit plan: All authorized plans'"></span><details class="ar-method"><summary>Report methodology</summary><div class="ar-method-copy" x-text="report.scope"></div><p>Accepted / Completed means the ATL accepted the audit. Checklist readiness is evaluated separately before submission. CAR closure preserves the original N finding. Observations are excluded from the compliance rate denominator.</p></details><div class="ar-print-method" x-text="report.scope"></div></div>
        <div class="ar-grid ar-overview" x-show="tab === 'overview'">
            <article class="ar-card"><h2>Audit Completion</h2><div class="ar-chart"><div class="ar-ring" :style="`background:conic-gradient(#16a34a ${Math.min(100,Number(completion))}%,#e2e8f0 0)`"><div class="ar-ring-center"><strong x-text="completion+'%'"></strong><small class="ar-muted">Completed</small></div></div><div class="ar-legend"><div><span class="ar-dot" style="background:#16a34a"></span>Accepted<strong x-text="totals.accepted"></strong></div><div><span class="ar-dot" style="background:#e2e8f0"></span>Remaining<strong x-text="Math.max(0,totals.assigned-totals.accepted)"></strong></div></div></div><p class="ar-muted">Accepted ÷ assigned audits</p></article>
            <article class="ar-card"><h2>N/C/O Overview</h2><div class="ar-chart"><div class="ar-ring" :style="`background:${ncoStyle}`"><div class="ar-ring-center"><strong x-text="assessed"></strong><small class="ar-muted">Results</small></div></div><div class="ar-legend">@foreach ([['N','Non-Conformance','#dc2626'],['C','Compliant','#16a34a'],['O','Observation','#d97706']] as [$key,$label,$color])<div><span class="ar-dot" style="background:{{ $color }}"></span>{{ $label }}<strong x-text="totals.{{ $key }}"></strong></div>@endforeach</div></div><p class="ar-muted">Accepted checklist results only</p></article>
            <article class="ar-card ar-comparison"><div class="ar-section-heading"><h2>Compliance Overview</h2><span class="ar-muted">Departments &amp; Offices</span></div><div class="ar-unit-list"><template x-for="row in report.rows" :key="row.unitId"><div class="ar-unit-line"><span x-text="row.unit"></span><div class="ar-track"><span :style="`width:${Math.min(100,Math.max(0,Number(row.compliance)||0))}%`"></span></div><strong :class="{'ar-unassessed': row.compliance === null}" x-text="row.compliance === null ? 'Not yet assessed' : row.compliance+'%'"></strong></div></template></div></article>
            <article class="ar-card ar-car-summary"><h2>Corrective Action Summary</h2><div class="ar-car-grid">@foreach ([['openCars','Open'],['overdueCars','Overdue'],['awaitingVerificationCars','For Verification'],['closedCars','Closed']] as [$key,$label])<div><small class="ar-muted">{{ $label }}</small><div class="ar-car-number" data-kind="{{ $key }}" x-text="totals.{{ $key }}"></div></div>@endforeach</div></article>
        </div>
        <section class="ar-card ar-table-card ar-report-section" x-show="tab === 'lifecycle'" aria-label="Audit Lifecycle"><h2>Audit Lifecycle</h2><div class="report-scroll" tabindex="0"><table><thead><tr><th scope="col">Department / Office</th><th scope="col">Planned</th><th scope="col">Assigned</th><th scope="col">In Progress</th><th scope="col">Submitted</th><th scope="col">Returned</th><th scope="col">Accepted / Completed</th></tr></thead><tbody><template x-for="row in report.rows" :key="row.unitId"><tr><th scope="row" x-text="row.unit"></th><td x-text="row.planned"></td><td x-text="row.assigned"></td><td x-text="row.inProgress"></td><td x-text="row.submitted"></td><td x-text="row.returned"></td><td x-text="row.accepted"></td></tr></template></tbody></table></div></section>
        <section class="ar-card ar-table-card ar-report-section" x-show="tab === 'compliance'" aria-label="Compliance Results"><h2>Compliance Results</h2><div class="report-scroll" tabindex="0"><table><thead><tr><th scope="col">Department / Office</th><th scope="col">N</th><th scope="col">C</th><th scope="col">O</th><th scope="col">Compliance</th><th scope="col">Outstanding evidence</th></tr></thead><tbody><template x-for="row in report.rows" :key="row.unitId"><tr><th scope="row" x-text="row.unit"></th><td x-text="row.N"></td><td x-text="row.C"></td><td x-text="row.O"></td><td x-text="row.compliance === null ? 'Not yet assessed' : row.compliance + '%'"></td><td x-text="row.outstandingEvidence"></td></tr></template></tbody></table></div></section>
        <section class="ar-card ar-table-card ar-report-section" x-show="tab === 'cars'" aria-label="Corrective Actions"><h2>Corrective Actions</h2><div class="report-scroll" tabindex="0"><table><thead><tr><th scope="col">Department / Office</th><th scope="col">Open CARs</th><th scope="col">Overdue</th><th scope="col">Awaiting Verification</th><th scope="col">Closed</th><th scope="col">Checklist-linked CARs</th></tr></thead><tbody><template x-for="row in report.rows" :key="row.unitId"><tr><th scope="row" x-text="row.unit"></th><td x-text="row.openCars"></td><td x-text="row.overdueCars"></td><td x-text="row.awaitingVerificationCars"></td><td x-text="row.closedCars"></td><td x-text="row.linkedCars"></td></tr></template></tbody></table></div></section>
        <section class="ar-card ar-table-card ar-report-section" x-show="tab === 'legacy'" aria-label="Legacy Checks"><h2>Legacy Checks</h2><div class="report-scroll" tabindex="0"><table><thead><tr><th scope="col">Department / Office</th><th scope="col">Unknown legacy state</th><th scope="col">Legacy checks</th><th scope="col">Legacy compliant checks</th></tr></thead><tbody><template x-for="row in report.rows" :key="row.unitId"><tr><th scope="row" x-text="row.unit"></th><td x-text="row.unknownCars"></td><td x-text="row.legacyChecks"></td><td x-text="row.legacyCompliant"></td></tr></template></tbody></table></div></section>
        <p class="ar-muted" x-show="!report.rows.length">No authorized units match this plan and year.</p>
        <p class="ar-print-method">Accepted / Completed means the ATL accepted the audit. Checklist readiness is evaluated separately before submission. CAR closure preserves the original N finding. Observations are excluded from the compliance rate denominator.</p>
    </div></template>
</section>
@endsection
