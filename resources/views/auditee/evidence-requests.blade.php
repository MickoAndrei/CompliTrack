@extends('layouts.app')
@section('title', 'My Audits & Evidence Requests')
@section('heading', 'My Audits & Evidence Requests')
@section('subtitle', 'Review requests, share permitted evidence, and track your responses.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/auditee-evidence.css') }}?v={{ filemtime(public_path('css/auditee-evidence.css')) }}">
@endpush
@section('content')
<div class="workflow-page evidence-page" data-evidence-page>
    @include('auditor.audit-feedback')
    <nav class="evidence-tabs" aria-label="Evidence purpose"><a href="/auditee/evidence-requests" aria-current="page"><i data-lucide="clipboard-list" aria-hidden="true"></i>During Audit</a><a href="/auditee/upload"><i data-lucide="file-check-2" aria-hidden="true"></i>Corrective Evidence</a></nav>
    <div class="evidence-summary" data-evidence-summary aria-label="Your request summary">
        @foreach([['Active Requests', 'active', 'files', 'All requests still in progress'], ['Awaiting Response', 'awaiting', 'clock-3', 'New requests and requests for more information'], ['Submitted', 'submitted', 'send', 'Submitted responses, including those under review'], ['Completed', 'completed', 'circle-check', 'Requests marked reviewed by your auditor']] as [$label, $key, $icon, $hint])
        <article class="evidence-summary-card"><span class="evidence-summary-icon"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span><div><p>{{ $label }}</p><strong>{{ $summary[$key] }}</strong><span class="sr-only">{{ $hint }}</span></div></article>
        @endforeach
    </div>
    <section class="evidence-workspace" aria-labelledby="evidence-requests-heading">
        <div class="evidence-section-heading"><div><h2 id="evidence-requests-heading">Your evidence requests</h2><p>Requests addressed to you in your department.</p></div><span class="evidence-count" data-evidence-count>{{ $requests->total() }} {{ $requests->total() === 1 ? 'request' : 'requests' }}</span></div>
        <form method="GET" action="/auditee/evidence-requests" class="evidence-toolbar" role="search">
            <label class="evidence-search"><span class="sr-only">Search evidence requests</span><i data-lucide="search" aria-hidden="true"></i><input type="search" name="search" maxlength="120" value="{{ request('search') }}" placeholder="Search requests, audits, or auditors"></label>
            <label><span class="sr-only">Request status</span><select name="requestStatus" aria-label="Request status"><option value="">All request statuses</option>@foreach(['requested' => 'Awaiting Response', 'needs_information' => 'More information needed', 'response_submitted' => 'Submitted (including under review)', 'under_review' => 'Under Review', 'reviewed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected(request('requestStatus') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="sr-only">Audit status</span><select name="auditStatus" aria-label="Audit status"><option value="">All audit statuses</option>@foreach(['draft','ready','returned','submitted','accepted'] as $status)<option value="{{ $status }}" @selected(request('auditStatus') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
            <button type="submit" class="auditor-gold-button">Apply filters</button>
            @if(request()->filled('search') || request()->filled('requestStatus') || request()->filled('auditStatus'))<a class="evidence-reset" href="/auditee/evidence-requests">Clear</a>@endif
        </form>
        <div data-evidence-list>
            <div class="evidence-request-grid">
                @forelse($requests as $record)
                @php($detailUrl = '/auditee/evidence-requests?'.http_build_query(['auditId' => $record['auditId'], 'requestId' => $record['id']]))
                <article class="evidence-request-card" id="request-{{ $record['id'] }}">
                    <div class="evidence-card-top"><span class="evidence-card-icon"><i data-lucide="file-text" aria-hidden="true"></i></span><span class="evidence-status" data-status="{{ $record['status'] }}">{{ $record['statusLabel'] }}</span></div>
                    <div><p class="evidence-audit-reference">{{ $record['auditCode'] }} <span aria-hidden="true">&middot;</span> {{ $record['referenceStandard'] }}</p><h3>{{ $record['title'] }}</h3></div>
                    <p class="evidence-description">{{ $record['description'] }}</p>
                    <dl class="evidence-card-meta"><div><dt><i data-lucide="building-2" aria-hidden="true"></i>Department</dt><dd>{{ $record['department'] ?: 'Not recorded' }}</dd></div><div><dt><i data-lucide="user-round" aria-hidden="true"></i>Requesting auditor</dt><dd>{{ $record['auditor'] }}</dd></div>@if($record['dueDate'])<div><dt><i data-lucide="calendar-days" aria-hidden="true"></i>Due date</dt><dd>{{ $record['dueDate'] }}</dd></div>@endif</dl>
                    @if($record['status'] === 'needs_information')<p class="evidence-card-note">Your auditor has requested more information.</p>@elseif($record['hasDraft'])<p class="evidence-card-note">You have a saved draft.</p>@endif
                    <div class="evidence-card-actions"><a href="{{ $detailUrl }}" data-evidence-open aria-haspopup="dialog" class="workflow-secondary">View Request<span class="sr-only">: {{ $record['title'] }}</span></a>@if($record['canRespond'])<a href="{{ $detailUrl }}#response" data-evidence-open data-evidence-response aria-haspopup="dialog" class="auditor-gold-button">{{ $record['hasDraft'] ? 'Continue Draft' : 'Provide Evidence' }}<span class="sr-only">: {{ $record['title'] }}</span></a>@endif</div>
                </article>
                @empty
                <div class="evidence-empty"><span class="evidence-empty-icon"><i data-lucide="{{ request()->hasAny(['search', 'requestStatus', 'auditStatus']) ? 'search-x' : 'clipboard-list' }}" aria-hidden="true"></i></span><h3>{{ request()->hasAny(['search', 'requestStatus', 'auditStatus']) ? 'No matching requests' : 'No evidence requests yet' }}</h3><p>{{ request()->hasAny(['search', 'requestStatus', 'auditStatus']) ? 'Try another search or clear your filters to see your requests.' : 'When your auditor requests evidence from you, it will appear here. You can provide a document reference, arrange an original for inspection, or upload a file when authorized.' }}</p><div class="workflow-actions">@if(request()->hasAny(['search', 'requestStatus', 'auditStatus']))<a href="/auditee/evidence-requests" class="auditor-gold-button">Clear filters</a>@else<a href="/auditee/my-findings" class="workflow-secondary">My Audit Results</a><a href="/auditee/help#respond" class="auditor-gold-button">How to provide evidence</a>@endif</div></div>
                @endforelse
            </div>
            <div class="evidence-pagination">{{ $requests->links() }}</div>
        </div>
        <p class="evidence-privacy"><i data-lucide="shield-check" aria-hidden="true"></i>Share only authorized evidence. Internal audit notes and protected documents remain restricted.</p>
    </section>
    <dialog id="evidence-request-modal" class="evidence-modal" aria-labelledby="evidence-modal-title" @if(request()->filled('requestId')) data-initial-request="{{ '/auditee/evidence-requests?'.http_build_query(request()->only(['auditId', 'requestId'])) }}" @endif>
        <header class="evidence-modal-header"><div><p class="evidence-eyebrow">During Audit</p><h2 id="evidence-modal-title">Evidence Request Details</h2></div><button type="button" class="evidence-modal-close" data-evidence-close aria-label="Close evidence request">&times;</button></header>
        <div class="evidence-modal-body" data-evidence-body tabindex="-1"></div>
        <footer class="evidence-modal-footer"><p role="status" data-evidence-message></p><button type="button" class="workflow-secondary" data-evidence-close>Close</button></footer>
    </dialog>
</div>
@endsection
