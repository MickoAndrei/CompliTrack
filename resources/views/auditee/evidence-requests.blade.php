@extends('layouts.app')
@section('title', 'My Audits & Evidence Requests')
@section('heading', 'My Audits & Evidence Requests')
@section('content')
<div class="workflow-page">
    @include('auditor.audit-feedback')
    <nav class="workflow-actions" aria-label="Evidence purpose"><a href="/auditee/evidence-requests" aria-current="page">During Audit</a><a href="/auditee/upload">Corrective Evidence</a></nav>
    <p class="workflow-muted">Only requests addressed to you are visible. Internal checklists, interviews, and auditor notes remain private.</p>
    <form method="GET" class="workflow-filters"><label>Search audit title<input name="search" value="{{ request('search') }}"></label><label>Audit status<select name="auditStatus"><option value="">All</option>@foreach(['draft','ready','returned','submitted','accepted'] as $status)<option @selected(request('auditStatus') === $status)>{{ $status }}</option>@endforeach</select></label><label>Request status<select name="requestStatus"><option value="">All</option>@foreach(['requested','response_submitted','reviewed','needs_information'] as $status)<option value="{{ $status }}" @selected(request('requestStatus') === $status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select></label><button class="auditor-gold-button">Filter</button></form>
    @forelse($audits as $audit)
    <section class="workflow-card"><h2>{{ $audit->auditCode }} — {{ $audit->title }}</h2><p>{{ $audit->departmentName }} · {{ $audit->schedule }} · {{ ucfirst($audit->status) }}</p><p>Assigned auditor: {{ \App\Models\User::find($audit->auditorId)?->fullName }}</p><p>Scope: {{ $audit->scope }}</p>
        @foreach($audit->evidenceRequests ?? [] as $entry)
        @if($entry['auditeeId'] === (string) auth()->id() && (!request('requestStatus') || $entry['status'] === request('requestStatus')))
        <article class="workflow-request" id="request-{{ $entry['id'] }}">
            <h3>{{ $entry['description'] }}</h3><p>Reference standard: {{ $entry['referenceStandard'] }} · <span class="auditor-role-badge">{{ str_replace('_', ' ', $entry['status']) }}</span></p>
            @foreach($entry['responses'] as $response)
            <div class="workflow-response"><strong>{{ $response['method'] }}</strong> · {{ $response['at'] }}<p>{{ $response['reference'] ?? '' }}</p><p>{{ $response['notes'] }}</p>@if(isset($response['availability']))<p>Availability: {{ $response['availability'] }} (not verified)</p>@endif
                @if(isset($response['archiveId']))<a href="/api/v1/archives/{{ $response['archiveId'] }}/download">Download your authorized file</a>@endif
                @if(isset($response['review']))<p>Reviewer feedback: {{ $response['review']['feedback'] }}</p>@endif
            </div>
            @endforeach
            @if(in_array($entry['status'], ['requested','needs_information']) && in_array($audit->status, ['draft','ready','returned']))
                @include('auditee.evidence-response-form', ['responseAction' => '/auditee/evidence-requests/'.$audit->getKey().'/'.$entry['id'], 'canDraft' => true])
            @else<p class="workflow-muted">Responses are read-only while awaiting review or after the audit is locked.</p>@endif
        </article>
        @endif
        @endforeach
    </section>
    @empty
    <section class="auditor-assignment-empty"><i data-lucide="clipboard-list"></i><h2>No Evidence Requests Yet</h2><p>Requests will appear when an assigned auditor addresses one to you.</p></section>
    @endforelse
    {{ $audits->links() }}
</div>
@endsection
