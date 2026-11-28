@extends('layouts.app')
@section('title', 'Audit Plans')
@section('heading', 'Audit Plans')
@section('subtitle', 'Prepare audit plans and record explicit approval before assigning auditors.')
@push('styles')<link rel="stylesheet" href="{{ asset('css/audit-plans.css') }}?v={{ filemtime(public_path('css/audit-plans.css')) }}">@endpush
@section('body-class', 'ct-v2-page')
@section('content')
<section class="plan-page attention-page" x-data="auditPlans" :aria-busy="loading">
    <p x-page-notice role="alert" x-show="error" x-text="error"></p><p x-page-notice x-show="notice" x-text="notice"></p>
<header class="ct-page-heading"><div><h1>Audit plans</h1><p>View audit plans within your authorized institutional scope.</p></div><div class="ct-heading-actions"><button class="ct-secondary" type="button" x-show="data.canApprove" @click="statusFilter='submitted'; page=1">Review pending plan</button><button class="ct-primary" type="button" x-show="data.canPrepare" :disabled="loading || saving" @click="edit()">Prepare audit plan</button></div></header>
    <div class="ct-register plan-register">
    <div class="ct-toolbar"><label class="ct-search"><i data-lucide="search" aria-hidden="true"></i><span class="sr-only">Search audit plans</span><input type="search" placeholder="Search audit plans…" x-model="search" @input="page=1"></label>
    <select aria-label="Status" x-model="statusFilter" @change="page=1"><option value="">All statuses</option><option value="draft">Draft</option><option value="submitted">Pending approval</option><option value="accepted">Approved</option><option value="returned">Returned</option></select>
    <select aria-label="Covered unit" x-model="unitFilter" @change="page=1"><option value="">All units</option><template x-for="unit in data.units" :key="unit.id"><option :value="unit.id" x-text="unit.name"></option></template></select>
    <select aria-label="Sort by" x-model="sortBy" @change="page=1"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="title">Title A–Z</option></select></div>
    <p role="status" x-show="loading">Loading audit plans…</p>
    <div class="ct-table-wrap"><table class="ct-table"><thead><tr><th>Plan reference</th><th>Audit cycle / title</th><th>Scope</th><th>Schedule</th><th>Status</th></tr></thead><tbody>
    <template x-for="row in visiblePlans" :key="row.id"><tr x-show="!loading"><td><button type="button" class="ct-record-link" @click="openDetails(row)" x-text="row.reference || row.title"></button></td><td x-text="row.title"></td><td x-text="unitNames(row)"></td><td x-text="schedule(row)"></td><td><span class="plan-state" :class="'plan-state-'+row.plan.status" x-text="row.stateLabel"></span></td></tr></template>
    <tr x-show="!loading && !filteredPlans.length"><td colspan="5">No matching audit plans.</td></tr></tbody></table></div>
    <nav class="ct-pagination" aria-label="Audit plan pages" x-show="!loading"><x-page-size model="perPage" change="page = 1"/><span class="ct-page-count" x-text="filteredPlans.length ? `Showing ${(page-1)*perPage+1}–${Math.min(page*perPage,filteredPlans.length)} of ${filteredPlans.length}` : '0 records'"></span><button type="button" class="ct-secondary" @click="page--" :disabled="page<=1" aria-label="Previous page">‹</button><span class="ct-page-current" x-text="page"></span><button type="button" class="ct-secondary" @click="page++" :disabled="page>=lastPage" aria-label="Next page">›</button><span x-text="`Page ${page} of ${lastPage}`"></span></nav>
    <section class="ct-panel"><h2>Approval and planning</h2><p class="ct-muted">Audit plan approval depends on the authorized institutional workflow. Draft records are not official assignments.</p></section></div>
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
