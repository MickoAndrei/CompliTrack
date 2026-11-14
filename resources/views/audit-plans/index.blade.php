@extends('layouts.app')
@section('title', 'Audit Plans')
@section('heading', 'Audit Plans')
@section('subtitle', 'Prepare audit plans and record explicit approval before assigning auditors.')
@push('styles')<link rel="stylesheet" href="{{ asset('css/audit-plans.css') }}?v={{ filemtime(public_path('css/audit-plans.css')) }}">@endpush
@section('content')
<section class="plan-page" x-data="auditPlans">
    <p x-page-notice role="alert" x-show="error" x-text="error"></p><p x-page-notice x-show="notice" x-text="notice"></p>
    <div class="plan-toolbar"><p>Approval is an explicit permission. Preparing a plan or leading an audit does not grant approval authority.</p><button type="button" class="plan-primary" x-show="data.canPrepare" :disabled="loading || saving" @click="edit()">Prepare Audit Plan</button></div>
    <p x-show="loading" role="status">Loading audit plans...</p>
    <p class="plan-panel" x-show="!loading && !data.plans.length">No audit plans are available. Existing archive records remain unchanged.</p>
    <template x-for="row in data.plans" :key="row.id">
        <article class="plan-panel">
            <header class="plan-toolbar"><div><h2 x-text="row.title"></h2><small x-text="row.reference"></small></div><strong class="plan-state" x-text="row.stateLabel"></strong></header>
            <dl class="plan-summary"><dt>Scope</dt><dd x-text="row.plan.header.scope"></dd><dt>Criteria</dt><dd x-text="row.plan.header.criteria"></dd><dt>Schedule</dt><dd x-text="row.plan.header.scheduleStart + ' to ' + row.plan.header.scheduleEnd"></dd><dt>Covered units</dt><dd x-text="row.plan.header.unitIds.map(id => data.units.find(unit => unit.id === id)?.name || id).join(', ')"></dd><dt>Authorized Audit Team Leaders</dt><dd x-text="row.plan.header.reviewerIds.map(id => data.reviewers.find(person => person.id === id)?.name || id).join(', ')"></dd><dt>Independence procedure</dt><dd x-text="row.plan.header.procedureReference"></dd></dl>
            <details class="plan-history"><summary>Decision and revision history</summary><template x-for="(entry, index) in row.plan.history" :key="index"><p><strong x-text="entry.decision"></strong> · <span x-text="entry.actorName"></span> · <time x-text="entry.at"></time><br><span x-text="entry.remarks"></span></p></template></details>
            <div class="plan-actions"><button type="button" class="plan-secondary" x-show="row.canEdit" :disabled="saving" @click="edit(row)">Edit Draft</button></div>
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
        </article>
    </template>
    <dialog class="plan-editor" x-ref="editor" aria-labelledby="plan-editor-title" @cancel="if (saving) $event.preventDefault()">
        <form @submit.prevent="save()"><header class="plan-toolbar"><h2 id="plan-editor-title">Prepare Audit Plan</h2><button type="button" class="plan-secondary" :disabled="saving" @click="$refs.editor.close()">Close</button></header>
            <p x-page-notice role="alert" x-show="error" x-text="error"></p>
            <label x-show="!editing">Existing plan archive<select x-model="selected" required :disabled="!!editing"><option value="">Select the controlled audit plan document</option><template x-for="archive in data.archives" :key="archive.id"><option :value="archive.id" x-text="archive.title"></option></template></select></label>
            <fieldset><legend>Departments / offices covered</legend><template x-for="unit in data.units" :key="unit.id"><label class="plan-check"><input type="checkbox" :value="unit.id" x-model="draft.unitIds"><span x-text="unit.name"></span></label></template></fieldset>
            <fieldset><legend>Authorized Audit Team Leaders for assignments</legend><template x-for="person in data.reviewers" :key="person.id"><label class="plan-check"><input type="checkbox" :value="person.id" x-model="draft.reviewerIds"><span x-text="person.name"></span></label></template></fieldset>
            <div class="plan-dates"><label>Start date<input type="date" x-model="draft.scheduleStart" required></label><label>End date<input type="date" x-model="draft.scheduleEnd" :min="draft.scheduleStart" required></label></div>
            <label>Scope<textarea x-model="draft.scope" required maxlength="3000" rows="3"></textarea></label><label>Criteria<textarea x-model="draft.criteria" required maxlength="3000" rows="3"></textarea></label>
            <label>Applicable independence / conflict procedure<input type="text" x-model="draft.procedureReference" required maxlength="1000"></label>
            <label>Revision remarks<textarea x-model="draft.remarks" required maxlength="3000" rows="2"></textarea></label>
            <button type="submit" class="plan-primary" :disabled="saving || !selected">Save Draft</button>
        </form>
    </dialog>
</section>
@endsection
