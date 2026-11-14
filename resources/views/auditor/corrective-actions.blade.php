@extends('layouts.app')
@section('title', 'Corrective Actions')
@section('heading', 'Corrective Actions')
@section('subtitle', 'Monitor findings, submitted evidence, and verification progress.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/corrective-actions.css') }}?v={{ filemtime(public_path('css/corrective-actions.css')) }}">
@endpush
@section('content')
<section class="car-page" x-data="correctiveActions" :aria-busy="loading">
    <div class="car-summary" aria-label="Corrective action summary">
        @foreach ([['total', 'Total Actions', 'clipboard-list'], ['inProgress', 'In Progress', 'clock'], ['awaitingVerification', 'Awaiting Verification', 'clipboard-check'], ['closed', 'Closed', 'circle-check']] as [$key, $label, $icon])
        <article class="car-metric"><div><p>{{ $label }}</p><strong x-text="summary?.{{ $key }} ?? '—'"></strong></div><i data-lucide="{{ $icon }}" aria-hidden="true"></i></article>
        @endforeach
    </div>
    <div class="car-records">
        <header class="car-section-heading"><h2>Corrective Action Records</h2><span x-text="`${meta.total || 0} records`"></span></header>
        <div class="car-filters">
            <label class="car-search"><span class="sr-only">Search corrective actions</span><i data-lucide="search" aria-hidden="true"></i><input type="search" x-model="search" @input.debounce.350ms="load(1)" maxlength="255" placeholder="Search audit item, department, auditee, or reference..."></label>
            <label><span class="sr-only">Department</span><select aria-label="Filter by department" x-model="departmentId" @change="load(1)"><option value="">All departments</option><template x-for="unit in departments" :key="unit.id"><option :value="unit.id" x-text="unit.name"></option></template></select></label>
            <label><span class="sr-only">Status</span><select aria-label="Filter by status" x-model="status" @change="load(1)"><option value="">All statuses</option><option value="open">Open</option><option value="in_progress">In Progress</option><option value="awaiting_verification">Awaiting Verification</option><option value="closed">Closed</option><option value="returned_for_revision">Returned for revision</option></select></label>
        </div>
        <p class="car-summary-note">Summary totals follow your search and department filter across all pages.</p>
        <p x-page-notice="'success'" role="status" x-show="notice" x-cloak x-text="notice"></p>
        <div x-page-notice role="alert" x-show="error" x-cloak><p x-text="error"></p><button type="button" class="car-secondary" @click="load()">Retry</button></div>
        <div x-show="loading"><span class="sr-only" role="status">Loading corrective actions</span><x-dashboard-skeleton type="activity" /></div>
        <div class="car-list" x-show="!loading && !error" x-cloak>
            <template x-for="row in rows" :key="row._id"><article class="car-record">
                <div class="car-section-heading"><div><p class="car-eyebrow">Audit Item</p><h3 x-text="row.auditItem"></h3><p class="car-reference" x-text="`Reference: ${row.checkCode}`"></p></div><x-status-badge status="" class="car-status" ::data-status="row.displayStatus" x-text="row.statusLabel" /></div>
                <p class="car-assignment"><span><i data-lucide="building-2" aria-hidden="true"></i><span x-text="row.departmentName"></span></span><span><i data-lucide="user-round" aria-hidden="true"></i><span x-text="row.auditeeName"></span></span></p>
                <p class="car-finding" x-text="row.finding"></p>
                <footer><span x-text="row.dueDate ? `Due ${date(row.dueDate)}` : 'Due date not recorded'"></span><button type="button" class="car-secondary" @click="openDetails(row)" aria-haspopup="dialog">View Details <span aria-hidden="true">→</span><span class="sr-only" x-text="` for ${row.checkCode}`"></span></button></footer>
            </article></template>
            <div class="car-empty" x-show="!rows.length"><i data-lucide="clipboard-check" aria-hidden="true"></i><h3>No corrective actions match these filters</h3><p>Try another search, department, or status.</p><button type="button" class="car-secondary" @click="clearFilters()">Clear filters</button></div>
        </div>
        <x-pagination-controls label="Corrective action pages" />
    </div>
    <dialog class="car-dialog" x-ref="details" aria-labelledby="car-details-title" @close="closeDetails()" @cancel="if (saving) $event.preventDefault()">
        <header class="car-dialog-header"><div><h2 id="car-details-title">Corrective Action Details</h2><p x-text="detail ? `${detail.checkCode} · ${detail.departmentName}` : 'Loading audit item…'"></p></div><button type="button" class="car-close" aria-label="Close corrective action details" :disabled="saving" @click="$refs.details.close()">×</button></header>
        <div class="car-dialog-body">
            <p role="status" x-show="detailLoading">Loading corrective action details…</p>
            <p x-page-notice role="alert" x-show="detailError" x-text="detailError"></p>
            <template x-if="detail && !detailLoading"><div>
                <div class="car-detail-title"><h3 x-text="detail.auditItem"></h3><x-status-badge status="" class="car-status" ::data-status="detail.displayStatus" x-text="detail.statusLabel" /></div>
                <div class="car-detail-grid"><section><h3>Finding Details</h3><dl><dt>Original finding</dt><dd x-text="detail.finding"></dd><dt>Applicable standard</dt><dd x-text="detail.referenceStandard || 'Not recorded'"></dd><dt>Auditor remarks</dt><dd x-text="detail.auditorRemarks || 'Not recorded'"></dd><dt>Assigned auditor</dt><dd x-text="detail.auditorName"></dd></dl></section><section><h3>Responsible Auditee</h3><dl><dt>Assigned person</dt><dd x-text="detail.auditeeName"></dd><dt>Department</dt><dd x-text="detail.departmentName"></dd><dt>Finding recorded</dt><dd x-text="date(detail.createdAt)"></dd><dt>Due date</dt><dd x-text="date(detail.dueDate)"></dd></dl></section></div>
                <section class="car-detail-section"><h3>Permitted Evidence</h3><p class="car-summary-note">Evidence submissions require auditor review. Submission alone does not close a corrective action.</p>
                    <template x-for="(response, index) in detail.responses" :key="index"><article class="car-response"><strong x-text="label(response.method)"></strong><p x-text="response.reference"></p><p x-text="response.notes"></p><p x-show="response.availability" x-text="`Reported availability: ${label(response.availability)}. Physical inspection must be verified separately.`"></p><small x-text="`${date(response.at)} · ${response.actor || 'Actor not recorded'}`"></small></article></template>
                    <ul class="car-files"><template x-for="file in detail.evidence" :key="file.id"><li><span x-text="file.name"></span><a x-show="file.canDownload" :href="`/api/v1/archives/${file.id}/download`" :data-file-name="file.name" data-evidence-preview class="car-secondary">Preview evidence</a><small x-show="!file.canDownload">File preview is not permitted</small></li></template></ul>
                    <p x-show="!detail.responses.length && !detail.evidence.length">No permitted corrective evidence is recorded.</p>
                </section>
                <section class="car-detail-section"><h3>CAR Status Timeline</h3><p class="car-summary-note">Recorded events include available timestamps and actors. Pending stages are expectations, not completed events.</p><ol class="car-timeline"><template x-for="(event, index) in detail.timeline" :key="index"><li :data-state="event.returned ? 'returned' : event.state"><span class="car-timeline-dot" aria-hidden="true"></span><div><strong x-text="event.label"></strong><span class="car-stage" x-text="event.returned ? 'Returned for revision' : ({current:'Current',completed:'Completed',upcoming:'Pending'}[event.state] || event.state)"></span><p x-text="event.kind === 'upcoming' ? 'Not yet recorded' : `${date(event.at)} · ${event.actor || 'Actor not recorded'}${event.role ? ` (${event.role})` : ''}`"></p><p x-show="event.remarks" x-text="event.remarks"></p></div></li></template></ol></section>
                <section class="car-detail-section"><h3>Auditor Verification</h3><p class="car-summary-note">Only the assigned auditor in the authorized department can use the existing CAR transitions. Record the verification performed before closure.</p>
                    <form @submit.prevent="submitTransition()" x-show="detail.allowedTransitions.length"><label>Verification / action notes<textarea x-model="remarks" maxlength="5000" rows="3" :required="detail.correctiveStatus !== 'open'" :disabled="saving" placeholder="Record evidence reviewed, checks performed, and the outcome."></textarea></label><div class="car-verification-actions"><button type="submit" class="car-primary" :disabled="saving" x-text="saving ? 'Saving…' : actionLabel(detail.allowedTransitions[0])"></button></div></form>
                    <p x-show="!detail.allowedTransitions.length">This recorded status has no authorized transition in the current workflow.</p>
                </section>
            </div></template>
        </div>
    </dialog>
</section>
@include('auditee.evidence-preview-modal')
@endsection
