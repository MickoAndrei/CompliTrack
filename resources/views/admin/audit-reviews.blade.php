@extends('layouts.app')
@section('title', 'Audit Reviews')
@section('heading', 'Audit Reviews')
@section('subtitle', 'Review submitted audit results and manage revision decisions.')
@section('content')
<div class="review-queue">
    @include('auditor.audit-feedback')
    @if($loadError ?? false)
    <div role="alert" class="atl-error">Unable to load audit reviews. <a href="{{ request()->fullUrl() }}">Retry</a></div>
    @else
    <div class="review-summary">
        @foreach(['submitted' => 'Awaiting Review', 'returned' => 'Returned', 'accepted' => 'Reviewed'] as $state => $label)
        <a href="{{ request()->fullUrlWithQuery(['status' => $state, 'page' => 1]) }}"><span>{{ $label }}</span><strong>{{ $counts[$state] }}</strong><small>{{ ['submitted' => 'Ready for your decision', 'returned' => 'With the assigned auditor', 'accepted' => 'Accepted audit results'][$state] }}</small></a>
        @endforeach
    </div>
    <div class="review-panel">
        <form method="GET" action="/audits" class="review-filters" aria-label="Filter review queue" data-review-filters>
            <input type="hidden" name="view" value="reviews">
            <label class="review-search">Search<input name="search" value="{{ request('search') }}" placeholder="Audit reference, title or department" maxlength="160"></label>
            <label>Audit plan<select name="planId"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan['id'] }}" @selected(request('planId') === $plan['id'])>{{ $plan['name'] }}</option>@endforeach</select></label>
            <label>Auditor<select name="auditorId"><option value="">All auditors</option>@foreach($people as $id => $person)<option value="{{ $id }}" @selected(request('auditorId') === $id)>{{ $person->fullName }}</option>@endforeach</select></label>
            <label>Department / office<select name="departmentId"><option value="">All units</option>@foreach($departments as $id => $name)<option value="{{ $id }}" @selected(request('departmentId') === $id)>{{ $name }}</option>@endforeach</select></label>
            <label>Status<select name="status">@foreach(['submitted' => 'Awaiting Review', 'returned' => 'Returned', 'accepted' => 'Reviewed'] as $state => $label)<option value="{{ $state }}" @selected($status === $state)>{{ $label }}</option>@endforeach</select></label>
            <a href="/audits?view=reviews" class="atl-secondary" data-review-reset>Reset Filter</a>
        </form>
        <p data-review-filter-error role="alert" hidden class="atl-error review-sort"></p>
        <div data-review-results aria-live="polite" aria-busy="false" tabindex="-1">
        <p class="atl-muted review-sort">{{ $audits->total() }} matching audits · Oldest submission first · Missing submission dates appear last</p>
        <div class="review-table-wrap"><table class="review-table"><thead><tr><th>Audit</th><th>Assigned auditor</th><th>Submitted</th><th>Status</th><th>Checklist</th><th>Action</th></tr></thead><tbody>
            @forelse($audits as $audit)
            @php($submission = \App\Services\AuditReviewQueue::submission($audit))
            @php($progress = \App\Services\AuditChecklist::progress($audit->items ?? []))
            <tr><td data-label="Audit"><strong>{{ $audit->title }}</strong><small>{{ $audit->auditCode }}</small><span>{{ $audit->departmentName }}</span></td>
                <td data-label="Auditor">{{ $people[(string) $audit->auditorId]?->fullName ?? 'Account unavailable' }}</td>
                <td data-label="Submitted"><time>{{ \App\Services\AtlWorkspace::date($submission['at']) }}</time><small class="{{ $submission['resubmitted'] ? 'review-revision' : '' }}">{{ $submission['resubmitted'] ? 'Resubmission' : ($submission['at'] ? 'First submission' : 'Submission history unavailable') }}</small></td>
                <td data-label="Status"><span class="review-badge is-{{ $audit->status }}">{{ ['submitted' => 'Awaiting Review', 'returned' => 'Returned', 'accepted' => 'Reviewed'][$audit->status] }}</span></td>
                <td data-label="Checklist"><strong>{{ $progress['evaluated'] }}/{{ $progress['total'] }}</strong><small>evaluated</small></td>
                <td><a class="atl-primary" href="/audits/{{ $audit->getKey() }}?review=1" data-atl-review aria-haspopup="dialog" aria-label="Review {{ $audit->auditCode }}">{{ $status === 'submitted' ? 'Review' : 'View review' }}</a></td></tr>
            @empty<tr><td colspan="6" class="review-empty">No audits match this queue. Adjust the filters or choose another category.</td></tr>@endforelse
        </tbody></table></div>
        <div class="review-pagination">{{ $audits->links() }}</div>
        </div>
        <template data-review-skeleton><div class="review-loading" role="status"><span class="sr-only">Loading audit reviews</span>@for($row = 0; $row < 5; $row++)<div class="review-loading-row" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>@endfor</div></template>
    </div>
    @endif
</div>
@endsection
