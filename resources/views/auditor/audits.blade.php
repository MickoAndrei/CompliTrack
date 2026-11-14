@extends('layouts.app')
@section('title', 'My Assigned Audits')
@section('heading', 'My Assigned Audits')
@section('subtitle', 'Manage your checklists, monitor progress, and track submissions.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/my-assigned-audits.css') }}?v={{ filemtime(public_path('css/my-assigned-audits.css')) }}">
@endpush
@section('content')
<div class="maa-page" data-my-audits>
    @include('auditor.audit-feedback')
    @if($loadError ?? false)
    <section class="maa-register maa-empty" role="alert"><h3>Assigned audits unavailable</h3><p>Your assignments could not be loaded. Please retry; no audit records have been changed.</p><a href="{{ request()->fullUrl() }}" class="maa-button">Retry loading audits</a></section>
    @else
    @include('auditor.partials.assigned-audit-summary')
    <section class="maa-register" aria-labelledby="my-audits-title">
        <a href="/auditor/historical-records" class="maa-history-link">Historical Compliance Records</a>
        <h2 id="my-audits-title">My Audits</h2>
        <form action="/audits" method="GET" class="maa-filters" data-my-audit-filters role="search" aria-label="Filter assigned audits">
            <label class="maa-search"><span class="sr-only">Search audit title, reference, or department</span><i data-lucide="search" aria-hidden="true"></i><input type="search" name="search" maxlength="160" placeholder="Search audits or departments…" value="{{ $filters['search'] }}"></label>
            <label><span class="sr-only">Department or office</span><select name="departmentId"><option value="">All departments & offices</option>@foreach($departments as $department)<option value="{{ $department['id'] }}" @selected($filters['departmentId'] === $department['id'])>{{ $department['name'] }}</option>@endforeach</select></label>
            <label><span class="sr-only">Audit status</span><select name="status"><option value="">All statuses</option>@foreach(['draft' => 'Draft', 'ready' => 'Ready', 'in_progress' => 'In Progress', 'submitted' => 'Submitted', 'returned' => 'Returned', 'accepted' => 'Accepted / Completed'] as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <button type="button" class="maa-button" data-my-audit-reset>Clear Filters</button>
        </form>
        <div class="maa-error" role="alert" data-my-audit-error hidden><span data-my-audit-error-text></span><button type="button" class="maa-button" data-my-audit-retry>Retry</button></div>
        @include('auditor.partials.assigned-audit-results')
        <div class="maa-loading" role="status" data-my-audit-loading hidden>
            <span class="sr-only">Updating assigned audits…</span>
            <div class="maa-cards" aria-hidden="true">
                @for($i = 0; $i < 2; $i++)
                <div class="maa-skeleton-card">
                    <div class="maa-card-heading"><span class="maa-skeleton-title"></span><span class="maa-skeleton-badge"></span></div>
                    <span class="maa-skeleton-reference"></span><span class="maa-skeleton-meta"></span>
                    <span class="maa-skeleton-progress"></span><span class="maa-skeleton-action"></span>
                </div>
                @endfor
            </div>
        </div>
    </section>
    @endif
</div>
@endsection
