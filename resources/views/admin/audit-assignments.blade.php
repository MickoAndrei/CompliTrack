@extends('layouts.app')
@section('title', 'Audit Assignments')
@section('heading', 'Audit Assignments')
@section('subtitle', 'Assign auditors to departments and offices based on authorized audit plans.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/audit-assignments.css') }}?v={{ filemtime(public_path('css/audit-assignments.css')) }}">
@endpush
@section('content')
<div class="space-y-6 atl-assignment-page" data-assignment-page>
    @include('auditor.audit-feedback')
    <header class="atl-actions" style="justify-content:flex-end"><button type="button" class="atl-primary" data-new-assignment>+ New Audit Assignment</button></header>
    <div class="assignment-stats">
        <article><span>Scheduled / preparing</span><strong>{{ $counts['draft'] }}</strong></article>
        <article><span>Authorized plans available</span><strong>{{ $plans->count() }}</strong></article>
        <article><span>In Progress</span><strong>{{ $counts['ready'] }}</strong></article>
    </div>
    <section class="assignment-register"><h2>Assignment Register</h2>
        <form action="/audits" method="GET" class="atl-filters" data-assignment-filters><label>Status<select name="status"><option value="">All statuses</option>@foreach(['draft' => 'Scheduled / preparing', 'ready' => 'In Progress', 'submitted' => 'Submitted', 'returned' => 'Returned', 'accepted' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label><a href="/audits" class="atl-secondary">Reset Filter</a></form>
        <p data-assignment-filter-message role="status" aria-live="polite" hidden></p>
        <div data-assignment-loading role="status" hidden>
            <span class="sr-only">Loading assignments...</span>
            <div class="assignment-table-scroll" aria-hidden="true"><table>
                <thead><tr>@foreach(['Audit / Plan', 'Department / Office', 'Auditor', 'Schedule', 'Scope / Criteria', 'Status', 'Assignment'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@foreach(range(1, 10) as $row)<tr>@foreach(range(1, 7) as $column)<td><span class="assignment-skeleton"></span><span class="assignment-skeleton assignment-skeleton-short"></span></td>@endforeach</tr>@endforeach</tbody>
            </table></div>
        </div>
        <div data-assignment-results aria-busy="false">
        <div class="assignment-table-scroll"><table><thead><tr><th>Audit / Plan</th><th>Department / Office</th><th>Auditor</th><th>Schedule</th><th>Scope / Criteria</th><th>Status</th><th>Assignment</th></tr></thead><tbody>
        @forelse($audits as $audit)
            <tr><td><strong>{{ $audit->auditCode }}</strong><p>{{ $audit->title }}</p><small>{{ $audit->planTitle ?: 'Legacy record — plan link not recorded' }}</small></td><td>{{ $audit->departmentName }}</td><td>{{ ($people[(string) $audit->auditorId] ?? null)?->fullName ?? \App\Models\User::find($audit->auditorId)?->fullName ?? 'Account unavailable' }}</td><td>{{ $audit->schedule }}</td><td><button type="button" class="assignment-scope-trigger" data-view-audit-scope data-scope-details="{{ json_encode(['audit' => $audit->auditCode, 'scope' => $audit->scope, 'criteria' => $audit->criteria]) }}" aria-haspopup="dialog" aria-controls="audit-scope-dialog">View scope and criteria</button></td><td><span class="atl-status">{{ ['draft' => 'Scheduled / preparing', 'ready' => 'In Progress', 'accepted' => 'Completed'][$audit->status] ?? ucfirst($audit->status) }}</span></td><td>
            @if($audit->planId && in_array($audit->status, ['draft', 'ready']) && !collect($audit->history ?? [])->contains('action', 'submit'))
                <button type="button" class="atl-secondary" data-edit-assignment data-assignment="{{ json_encode(['id' => (string) $audit->getKey(), 'version' => $audit->version, 'planId' => (string) $audit->planId, 'departmentId' => (string) $audit->departmentId, 'auditorId' => (string) $audit->auditorId, 'title' => $audit->title, 'schedule' => $audit->schedule]) }}">Change assignment</button>
            @else<span class="atl-muted">{{ $audit->planId ? 'Assignment locked' : 'Legacy assignment retained' }}</span>@endif
            </td></tr>
        @empty<tr><td colspan="7">No audit assignments match this register.</td></tr>@endforelse
        </tbody></table></div>
        {{ $audits->links() }}
        </div>
    </section>
    <dialog id="audit-assignment-dialog" class="assignment-dialog" aria-labelledby="assignment-dialog-title" data-reopen="{{ $errors->any() ? '1' : '0' }}" data-old="{{ json_encode(old()) }}">
        <header class="atl-section-heading"><h2 id="assignment-dialog-title">New Audit Assignment</h2><button type="button" class="atl-secondary" data-close-assignment aria-label="Close assignment modal">Close</button></header>
        <form method="POST" action="/audits" class="assignment-modal-form">@csrf
            <input type="hidden" name="assignmentId"><input type="hidden" name="version">
            @if($plans->isEmpty())<p role="status" class="atl-evidence-note">No audit plans have confirmed assignment authorization in your permitted archives. Prepare and submit a plan in <a href="/audit-plans">Audit Plans</a>; an explicitly permitted approver must approve it before it can be selected. Existing assignments remain available.</p>@endif
            <label>Authorized Audit Plan<select name="planId" required><option value="">Select an authorized plan</option>@foreach($plans as $plan)<option value="{{ $plan->getKey() }}" data-plan="{{ json_encode(array_replace($plan->assignmentAuthorization, ['unitIds' => array_map('strval', $plan->assignmentAuthorization['unitIds'])])) }}">{{ $plan->title ?: $plan->originalName }}</option>@endforeach</select></label>
            <label>Assignment title<input name="title" required maxlength="200"></label>
            <fieldset><legend>Auditable Unit Type</legend><label><input type="radio" name="unitType" value="department" checked> Department</label><label><input type="radio" name="unitType" value="office"> Office</label></fieldset>
            <label>Department / Office to Audit<select name="departmentId" required><option value="">Select a unit covered by the plan</option>@foreach($units as $unit)<option value="{{ $unit->getKey() }}" data-unit-type="{{ $unit->unitType === 'academic_department' ? 'department' : 'office' }}">{{ $unit->departmentName }}</option>@endforeach</select></label>
            <p class="atl-muted">CET has five academic departments. The Office of the Dean is a college office. Only verified units within the selected plan are available.</p>
            <label>Assigned Auditor<select name="auditorId" required aria-describedby="assignment-auditor-help"><option value="">Select an eligible auditor</option>@foreach($auditors as $auditor)<option value="{{ $auditor->getKey() }}" data-units="{{ json_encode($units->filter(fn ($unit) => $assignment->eligible($auditor, $unit))->map(fn ($unit) => (string) $unit->getKey())->values()) }}">{{ $auditor->fullName }}</option>@endforeach</select></label>
            <p id="assignment-auditor-help" class="atl-muted" data-assignment-auditor-help role="status" aria-live="polite">Select an authorized plan and a department or office to see eligible auditors.</p>
            <label>Scheduled Audit Date<input type="date" name="schedule" required></label>
            <label>Audit Scope<textarea data-plan-scope readonly rows="3"></textarea></label>
            <label>Audit Criteria<textarea data-plan-criteria readonly rows="3"></textarea></label>
            <p data-plan-procedure class="atl-muted"></p>
            <label>Independence / conflict check notes<textarea name="independenceNotes" maxlength="2000" required rows="3"></textarea></label>
            <label class="assignment-confirm"><input type="checkbox" name="independenceConfirmed" value="1" required> I verified independence and conflicts under the referenced procedure, including responsibility for the work being audited.</label>
            <label data-change-reason hidden>Reason for assignment change<textarea name="changeReason" maxlength="2000" rows="3"></textarea></label>
            <footer class="atl-actions"><button type="button" class="atl-secondary" data-close-assignment>Cancel</button><button type="submit" class="atl-primary" data-save-assignment>Assign Auditor</button></footer>
        </form>
    </dialog>
</div>
<dialog id="audit-scope-dialog" class="assignment-scope-dialog" aria-labelledby="audit-scope-title">
    <header><h2 id="audit-scope-title">Audit Scope and Criteria</h2><button type="button" class="atl-secondary" data-close-audit-scope aria-label="Close scope and criteria">Close</button></header>
    <p class="atl-muted" data-scope-audit></p>
    <h3>Scope</h3><p class="assignment-scope-text" data-scope-content></p>
    <h3>Criteria</h3><p class="assignment-scope-text" data-criteria-content></p>
</dialog>
@endsection
