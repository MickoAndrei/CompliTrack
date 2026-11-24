@extends('layouts.app')
@section('title', 'Audit Plans')
@section('heading', 'Audit Plans')
@section('subtitle', 'Prepare audit plans and record explicit approval before assigning auditors.')
@push('styles')<link rel="stylesheet" href="{{ asset('css/audit-plans.css') }}?v={{ filemtime(public_path('css/audit-plans.css')) }}">@endpush
@section('content')
<section class="plan-page attention-page" x-data="auditPlans" :aria-busy="loading">
    <p x-page-notice role="alert" x-show="error" x-text="error"></p><p x-page-notice x-show="notice" x-text="notice"></p>
    <div class="attention-stats" aria-label="Audit plan summary" :class="{'is-loading': loading}">
        @foreach ([['', 'Total Plans', 'clipboard-list', 'blue'], ['submitted', 'Pending Approval', 'clock', 'purple'], ['accepted', 'Approved', 'clipboard-check', 'blue'], ['returned', 'Returned', 'undo-2', 'danger']] as [$key, $label, $icon, $color])
            <article class="metric-stat-card"><div class="metric-info"><span class="metric-label">{{ $label }}</span><strong class="metric-number color-dark" x-text="loading ? '—' : countStatus('{{ $key }}')"></strong></div><div class="metric-icon-box {{ $color }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></div></article>
        @endforeach
    </div>
    <div class="plan-register">
    <div class="attention-tabs" role="group" aria-label="Plan status">
        @foreach (['' => 'All Plans', 'draft' => 'Draft', 'submitted' => 'Pending Approval', 'accepted' => 'Approved', 'returned' => 'Returned'] as $key => $label)
            <button type="button" @click="statusFilter = '{{ $key }}'; page = 1" :aria-pressed="statusFilter === '{{ $key }}'" :class="{'is-active': statusFilter === '{{ $key }}'}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="plan-filter-toolbar">
        <div class="attention-log-filters plan-filter-group">
            <label>Search audit plans<input type="search" placeholder="Search title or reference…" x-model="search" @input="page = 1"></label>
            <label>Filter by unit<select x-model="unitFilter" @change="page = 1"><option value="">All units</option><template x-for="unit in data.units" :key="unit.id"><option :value="unit.id" x-text="unit.name"></option></template></select></label>
            <label>Sort by<select x-model="sortBy" @change="page = 1"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="title">Title A–Z</option></select></label>
        </div>
        <button type="button" class="plan-primary plan-prepare-button" x-cloak x-show="data.canPrepare" :disabled="loading || saving" @click="edit()"><i data-lucide="plus" aria-hidden="true"></i> Prepare Audit Plan</button>
    </div>
    <div class="plan-results">
    <p class="plan-loading-status" role="status" x-show="loading">Loading audit plans…</p>
    <div class="attention-empty" x-cloak x-show="!loading && !error && !filteredPlans.length"><i data-lucide="search-x" aria-hidden="true"></i><h3>No matching audit plans</h3><p>Try another filter.</p></div>
    <div class="attention-table-wrap" x-show="loading || filteredPlans.length">
        <table class="attention-log-table audit-plans-table"><thead><tr><th>Audit Plan</th><th>Covered Units</th><th>Schedule</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @for ($row = 0; $row < 5; $row++)
                <tr class="plan-skeleton-row" x-show="loading" aria-hidden="true">
                    <td><span class="plan-skeleton"></span><span class="plan-skeleton plan-skeleton-short"></span></td>
                    <td><span class="plan-skeleton"></span></td><td><span class="plan-skeleton"></span></td><td><span class="plan-skeleton"></span></td><td><span class="plan-skeleton"></span></td>
                </tr>
            @endfor
            <template x-for="row in visiblePlans" :key="row.id"><tr x-show="!loading">
                <td data-label="Audit Plan"><strong x-text="row.title"></strong><small x-text="row.reference"></small></td>
                <td data-label="Covered Units" x-text="unitNames(row)"></td>
                <td data-label="Schedule" x-text="schedule(row)"></td>
                <td data-label="Status"><span class="plan-state" :class="'plan-state-' + row.plan.status" x-text="row.stateLabel"></span></td>
                <td data-label="Action"><button type="button" class="attention-button" @click="openDetails(row)">View details</button></td>
            </tr></template>
        </tbody></table>
    </div>
    </div>
    <nav class="attention-pagination" aria-label="Audit plan pages" x-cloak x-show="loading || (!error && filteredPlans.length)" :style="loading ? 'visibility:hidden' : ''">
        <span x-text="`${filteredPlans.length} record${filteredPlans.length === 1 ? '' : 's'} · Page ${page} of ${lastPage}`"></span>
        <div><x-page-size model="perPage" change="page = 1" /><button type="button" class="attention-button" @click="page--" :disabled="page <= 1">Previous</button><button type="button" class="attention-button" @click="page++" :disabled="page >= lastPage">Next</button></div>
    </nav>
    <p class="plan-note">Only explicitly approved plans are available for new auditor assignments. Approval requires explicit permission.</p>
    </div>
    <dialog class="plan-editor plan-details" x-ref="details" aria-labelledby="plan-details-heading" @cancel="if (saving) $event.preventDefault()">
        <template x-if="detailRow"><article x-data="{ get row() { return detailRow } }">
            <header class="plan-toolbar"><div><h2 id="plan-details-heading" x-text="row.title"></h2><small x-text="row.reference"></small></div><button type="button" class="plan-details-close" aria-label="Close audit plan details" :disabled="saving" @click="$refs.details.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12" /></svg></button></header>
            <div class="plan-details-body">
            <p class="plan-state" x-text="row.stateLabel"></p>
            <p x-page-notice role="alert" x-show="error" x-text="error"></p>
            <p x-page-notice x-show="notice" x-text="notice"></p>
            <dl class="plan-summary"><dt>Scope</dt><dd x-text="row.plan.header.scope"></dd><dt>Criteria</dt><dd x-text="row.plan.header.criteria"></dd><dt>Schedule</dt><dd x-text="row.plan.header.scheduleStart + ' to ' + row.plan.header.scheduleEnd"></dd><dt>Covered units</dt><dd x-text="row.plan.header.unitIds.map(id => data.units.find(unit => unit.id === id)?.name || id).join(', ')"></dd><dt>Authorized Audit Team Leaders</dt><dd x-text="row.plan.header.reviewerIds.map(id => data.reviewers.find(person => person.id === id)?.name || id).join(', ')"></dd><dt>Independence procedure</dt><dd x-text="row.plan.header.procedureReference"></dd></dl>
            <p role="note" x-show="row.legacyWarning" x-text="row.legacyWarning"></p><section class="plan-history" aria-labelledby="plan-history-heading"><h3 id="plan-history-heading">Decision and revision history</h3><p x-show="!(row.plan.history || []).length">No decisions or revisions recorded yet.</p><template x-for="(entry, index) in row.plan.history" :key="index"><p><strong x-text="entry.decision"></strong> · <span x-text="entry.actorName"></span> · <time  :datetime="entry.at" :title="entry.at" x-text="date(entry.at, {precise:true})"></time><br><span x-text="entry.remarks"></span></p></template></section>
            <div class="plan-actions"><button type="button" class="plan-secondary" x-show="row.canEdit" :disabled="saving" @click="$refs.details.close(); edit(row)">Edit Draft</button></div>
            <form class="plan-action-form" x-show="row.canEdit || row.canRevise || row.canDecide" @submit.prevent>
                <label>Remarks<textarea x-model="remarks[row.id]" required maxlength="3000" rows="2"></textarea></label>
                <div class="plan-actions">
                    <button type="submit" class="plan-primary" x-show="row.canEdit" :disabled="saving" @click="if ($el.form.reportValidity()) act(row, 'submit')">Submit for Approval</button>
                    <button type="submit" class="plan-secondary" x-show="row.canRevise" :disabled="saving" @click="if ($el.form.reportValidity()) act(row, 'revise')">Revise Plan</button>
                    <button type="submit" class="plan-secondary" x-show="row.canDecide" :disabled="saving" @click="if ($el.form.reportValidity()) act(row, 'return')">Return for Revision</button>
                    <button type="submit" class="plan-primary" x-show="row.canDecide" :disabled="saving" @click="if ($el.form.reportValidity()) act(row, 'approve')">Approve Plan</button>
                </div>
                <p class="plan-note" x-show="row.canRevise">Revising removes this plan from new assignment choices until it is approved again. Existing assignments retain their approved snapshot.</p>
            </form>
            </div>
        </article></template>
    </dialog>
    <dialog class="plan-editor plan-prepare-editor" x-ref="editor" aria-labelledby="plan-editor-title" @cancel="if (saving) $event.preventDefault()">
        <form @submit.prevent="save()" class="plan-prepare-form">
            <header class="plan-toolbar"><h2 id="plan-editor-title" x-text="editing ? 'Edit Audit Plan' : 'Prepare Audit Plan'"></h2><button type="button" class="plan-details-close" aria-label="Close audit plan editor" :disabled="saving" @click="$refs.editor.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12" /></svg></button></header>
            <div class="plan-prepare-body">
            <p x-page-notice role="alert" x-show="error" x-text="error"></p>
            <section class="plan-form-section">
                <h3>Audit Information</h3>
                <label x-show="!editing">Controlled audit-plan document<select x-model="selected" @change="draft.confirmPlanDocument = false; draft.classificationReason = ''" required :disabled="!!editing"><option value="">Select the controlled audit plan document</option><template x-for="archive in documentChoices" :key="archive.id"><option :value="archive.id" x-text="`${archive.code} · ${archive.title} · ${archive.type || 'Legacy method'} · v${archive.version}${archive.eligible ? '' : ' · Classification required'}`"></option></template></select></label>
                <button type="button" class="plan-text-button" x-show="!editing" @click="showLegacy = !showLegacy; if (!showLegacy && needsClassification) { selected = ''; draft.confirmPlanDocument = false; }" x-text="showLegacy ? 'Show verified documents only' : 'Advanced: show legacy documents'"></button>
                <div class="plan-classification" x-show="needsClassification" x-cloak>
                    <p>Select only the actual controlled audit-plan document. Attendance, reports, syllabi and corrective evidence are not audit plans.</p>
                    <button type="button" class="plan-secondary" :aria-pressed="!!draft.confirmPlanDocument" @click="draft.confirmPlanDocument = !draft.confirmPlanDocument" x-text="draft.confirmPlanDocument ? 'Classification confirmed — undo' : 'Confirm this is a controlled audit plan'"></button>
                    <label>Classification reason<textarea x-model="draft.classificationReason" :required="needsClassification" :disabled="!needsClassification" minlength="10" maxlength="1000" rows="2"></textarea></label>
                </div>
            </section>
            <section class="plan-form-section">
                <h3>Audit Coverage</h3>
                <div class="plan-pick" x-data="{ expanded: false, query: '' }" @click.outside="expanded = false" @keydown.escape.stop="expanded = false" x-id="['pick-panel','pick-search']">
                    <div class="plan-pick-heading"><span class="plan-field-label">Departments / offices covered <span aria-hidden="true">*</span></span><button type="button" class="plan-text-button" @click="draft.unitIds = data.units.map(item => item.id)">Select all</button></div>
                    <div class="plan-pick-box">
                        <div class="plan-tags"><template x-for="id in draft.unitIds" :key="id"><span class="plan-tag"><span x-text="data.units.find(item => item.id === id)?.name || id"></span><button type="button" @click="draft.unitIds = draft.unitIds.filter(value => value !== id)" :aria-label="'Remove ' + (data.units.find(item => item.id === id)?.name || id)">×</button></span></template></div>
                        <button type="button" class="plan-pick-toggle" @click="expanded = !expanded; query = ''; if (expanded) $nextTick(() => $refs.search.focus())" :aria-expanded="expanded" :aria-controls="$id('pick-panel')"><span x-text="draft.unitIds.length ? draft.unitIds.length + ' selected · Change selection' : 'Select departments / offices'"></span><span aria-hidden="true">▾</span></button>
                        <div x-show="expanded" x-cloak :id="$id('pick-panel')" class="plan-pick-options">
                            <label :for="$id('pick-search')" class="sr-only">Search departments / offices covered</label><input type="search" x-ref="search" :id="$id('pick-search')" x-model="query" placeholder="Search…">
                            <template x-for="item in data.units.filter(item => item.name.toLowerCase().includes(query.trim().toLowerCase()))" :key="item.id"><button type="button" class="plan-pick-option" :aria-pressed="draft.unitIds.includes(item.id)" @click="toggleSelection('unitIds', item.id)"><span x-text="item.name"></span><span aria-hidden="true" x-text="draft.unitIds.includes(item.id) ? '✓' : '+'"></span></button></template>
                            <p class="plan-note" x-show="!data.units.some(item => item.name.toLowerCase().includes(query.trim().toLowerCase()))">No matching options.</p>
                        </div>
                    </div>
                </div>
                <div class="plan-pick" x-data="{ expanded: false, query: '' }" @click.outside="expanded = false" @keydown.escape.stop="expanded = false" x-id="['pick-panel','pick-search']">
                    <div class="plan-pick-heading"><span class="plan-field-label">Authorized Audit Team Leaders for assignments <span aria-hidden="true">*</span></span></div>
                    <div class="plan-pick-box">
                        <div class="plan-tags"><template x-for="id in draft.reviewerIds" :key="id"><span class="plan-tag"><span x-text="data.reviewers.find(item => item.id === id)?.name || id"></span><button type="button" @click="draft.reviewerIds = draft.reviewerIds.filter(value => value !== id)" :aria-label="'Remove ' + (data.reviewers.find(item => item.id === id)?.name || id)">×</button></span></template></div>
                        <button type="button" class="plan-pick-toggle" @click="expanded = !expanded; query = ''; if (expanded) $nextTick(() => $refs.search.focus())" :aria-expanded="expanded" :aria-controls="$id('pick-panel')"><span x-text="draft.reviewerIds.length ? draft.reviewerIds.length + ' selected · Change selection' : 'Select audit team leaders'"></span><span aria-hidden="true">▾</span></button>
                        <div x-show="expanded" x-cloak :id="$id('pick-panel')" class="plan-pick-options">
                            <label :for="$id('pick-search')" class="sr-only">Search authorized audit team leaders for assignments</label><input type="search" x-ref="search" :id="$id('pick-search')" x-model="query" placeholder="Search…">
                            <template x-for="item in data.reviewers.filter(item => item.name.toLowerCase().includes(query.trim().toLowerCase()))" :key="item.id"><button type="button" class="plan-pick-option" :aria-pressed="draft.reviewerIds.includes(item.id)" @click="toggleSelection('reviewerIds', item.id)"><span x-text="item.name"></span><span aria-hidden="true" x-text="draft.reviewerIds.includes(item.id) ? '✓' : '+'"></span></button></template>
                            <p class="plan-note" x-show="!data.reviewers.some(item => item.name.toLowerCase().includes(query.trim().toLowerCase()))">No matching options.</p>
                        </div>
                    </div>
                </div>
            </section>
            <section class="plan-form-section">
                <h3>Audit Schedule</h3>
                <div class="plan-dates">
                    <div class="plan-date-field"><label for="plan-start-text">Start date * <small class="plan-date-hint">(MM/DD/YYYY)</small></label><span class="plan-date-control"><input id="plan-start-text" type="text" inputmode="numeric" placeholder="MM/DD/YYYY" :value="displayDate(draft.scheduleStart)" @input="$el.setCustomValidity('')" @change="setDate('scheduleStart', $event.target)" required pattern="[0-9]{2}/[0-9]{2}/[0-9]{4}" x-ref="startText"><span class="plan-calendar"><span aria-hidden="true">▦</span><input type="date" aria-label="Choose start date from calendar" x-model="draft.scheduleStart" @change="$refs.startText.setCustomValidity(''); $refs.startText.value = displayDate(draft.scheduleStart)"></span></span></div>
                    <div class="plan-date-field"><label for="plan-end-text">End date * <small class="plan-date-hint">(MM/DD/YYYY)</small></label><span class="plan-date-control"><input id="plan-end-text" type="text" inputmode="numeric" placeholder="MM/DD/YYYY" :value="displayDate(draft.scheduleEnd)" @input="$el.setCustomValidity('')" @change="setDate('scheduleEnd', $event.target)" required pattern="[0-9]{2}/[0-9]{2}/[0-9]{4}" x-ref="endText"><span class="plan-calendar"><span aria-hidden="true">▦</span><input type="date" aria-label="Choose end date from calendar" x-model="draft.scheduleEnd" :min="draft.scheduleStart || null" @change="$refs.endText.setCustomValidity(''); $refs.endText.value = displayDate(draft.scheduleEnd)"></span></span></div>
                </div>
                <p class="plan-date-hint">End date must be on or after the start date.</p>
            </section>
            <section class="plan-form-section">
                <h3>Audit Requirements</h3>
                <label>Scope<textarea x-model="draft.scope" required maxlength="3000" rows="3"></textarea></label>
                <label>Criteria<textarea x-model="draft.criteria" required maxlength="3000" rows="3"></textarea></label>
                <label>Applicable independence / conflict procedure<input type="text" x-model="draft.procedureReference" required minlength="5" maxlength="1000" placeholder="Procedure code, title and revision"></label>
                <label>Revision remarks<textarea x-model="draft.remarks" required maxlength="3000" rows="2"></textarea></label>
            </section>
            </div>
            <footer class="plan-prepare-footer"><p class="plan-note">Assignments become available after authorized approval.</p><button type="submit" class="plan-primary" :disabled="saving || !selected">Save Draft</button></footer>
        </form>
    </dialog>
</section>
@endsection
