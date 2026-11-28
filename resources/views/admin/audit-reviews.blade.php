@extends('layouts.app')
@section('title', 'Audit Reviews')
@section('heading', 'Audit Reviews')
@section('subtitle', 'Review submitted audit results and manage revision decisions.')
@section('body-class', 'ct-v2-page')
@section('content')
<div class="review-queue ct-register">
<header class="ct-page-heading"><div><h1>Report review</h1><p>Review reports by assignment and record authorized review decisions.</p></div></header>

    @include('auditor.audit-feedback')
    @if($loadError ?? false)
    <div role="alert" class="atl-error">Unable to load audit reviews. <a href="{{ request()->fullUrl() }}">Retry</a></div>
    @else
    <div class="review-panel ct-flat-register">
        <form method="GET" action="/audits" class="review-filters ct-toolbar" aria-label="Filter review queue" data-review-filters>
            <input type="hidden" name="view" value="reviews">
            <label class="review-search">Search<input name="search" value="{{ request('search') }}" placeholder="Audit reference, title or department" maxlength="160"></label>
            <label>Audit plan<select name="planId"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan['id'] }}" @selected(request('planId') === $plan['id'])>{{ $plan['name'] }}</option>@endforeach</select></label>
            <label>Auditor<select name="auditorId"><option value="">All auditors</option>@foreach($people as $id => $person)<option value="{{ $id }}" @selected(request('auditorId') === $id)>{{ $person->fullName }}</option>@endforeach</select></label>
            <label>Department / office<select name="departmentId"><option value="">All units</option>@foreach($departments as $id => $name)<option value="{{ $id }}" @selected(request('departmentId') === $id)>{{ $name }}</option>@endforeach</select></label>
            <label>Status<select name="status">@foreach(['submitted' => \App\Support\AuditStatus::label('submitted'), 'returned' => \App\Support\AuditStatus::label('returned'), 'accepted' => \App\Support\AuditStatus::label('accepted')] as $state => $label)<option value="{{ $state }}" @selected($status === $state)>{{ $label }}</option>@endforeach</select></label>
            <a href="/audits?view=reviews" class="atl-secondary" data-review-reset>Reset Filter</a>
        </form>
        <p data-review-filter-error role="alert" hidden class="atl-error review-sort"></p>
        <div data-review-results aria-live="polite" aria-busy="false" tabindex="-1">
        <p class="atl-muted review-sort">{{ $audits->total() }} matching audits · Oldest submission first · Missing submission dates appear last</p>
        <div class="review-table-wrap ct-table-wrap"><table class="review-table ct-table"><thead><tr><th>Report</th><th>Audit focus</th><th>Department</th><th>Submitted</th><th>Status</th></tr></thead><tbody>
            @forelse($audits as $audit)
            @php($submission = \App\Services\AuditReviewQueue::submission($audit))
            @php($progress = \App\Services\AuditChecklist::progress($audit->items ?? []))
            <tr><td><a class="ct-record-link" href="/audits/{{ $audit->getKey() }}?review=1" data-atl-review aria-haspopup="dialog">{{ $audit->auditCode }}</a></td><td>{{ $audit->title }}<small>{{ $progress['evaluated'] }}/{{ $progress['total'] }} evaluated</small></td><td>{{ $audit->departmentName }}</td>
                <td data-label="Submitted"><time>{{ \App\Services\AtlWorkspace::date($submission['at']) }}</time><small class="{{ $submission['resubmitted'] ? 'review-revision' : '' }}">{{ $submission['resubmitted'] ? 'Resubmission' : ($submission['at'] ? 'First submission' : 'Submission history unavailable') }}</small></td>
                <td data-label="Status"><span class="review-badge is-{{ $audit->status }}">{{ \App\Support\AuditStatus::label($audit->status) }}</span></td>
</tr>
            @empty<tr><td colspan="5" class="review-empty">No audits match this queue. Adjust the filters or choose another category.</td></tr>@endforelse
        </tbody></table></div>
        <footer class="review-pagination"><nav class="app-pagination ct-pagination" aria-label="Audit review pages"><span class="app-pagination-total">{{ $audits->total() }} {{ $audits->total() === 1 ? 'record' : 'records' }} · Page {{ $audits->currentPage() }} of {{ $audits->lastPage() }}</span><x-server-page-size :paginator="$audits" />@if($audits->onFirstPage())<span class="app-pagination-button" aria-disabled="true">Previous</span>@else<a class="app-pagination-button" href="{{ $audits->previousPageUrl() }}" rel="prev">Previous</a>@endif @if($audits->hasMorePages())<a class="app-pagination-button" href="{{ $audits->nextPageUrl() }}" rel="next">Next</a>@else<span class="app-pagination-button" aria-disabled="true">Next</span>@endif</nav></footer>
        @if($first = $audits->first())
        @php($firstSubmission = \App\Services\AuditReviewQueue::submission($first))
        <div class="ct-chart-grid ct-review-detail"><section class="ct-panel"><header><h2>{{ $first->auditCode }}</h2><span class="review-badge is-{{ $first->status }}">{{ \App\Support\AuditStatus::label($first->status) }}</span></header><p class="ct-muted">{{ $first->title }} · {{ $first->departmentName }}</p><hr><p>Open the authorized review to inspect evidence, findings and decisions.</p><a class="ct-primary" href="/audits/{{ $first->getKey() }}?review=1" data-atl-review aria-haspopup="dialog">Open review details</a></section><section class="ct-panel"><h2>Review history</h2><p>Report submission</p><p class="ct-muted">{{ \App\Services\AtlWorkspace::date($firstSubmission['at']) }}</p><hr><p>{{ \App\Support\AuditStatus::label($first->status) }}</p><p class="ct-muted">Full recorded decisions are available in review details.</p></section></div>
        @endif
        </div>
        <template data-review-skeleton><div class="review-loading" role="status"><span class="sr-only">Loading audit reviews</span>@for($row = 0; $row < 5; $row++)<div class="review-loading-row" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>@endfor</div></template>
    </div>
    @endif
</div>
@endsection
