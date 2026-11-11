@extends('layouts.app')
@section('title', 'My Dashboard')
@section('heading', 'My Dashboard')
@section('subtitle', 'Your audit results, evidence, and follow-ups.')
@section('content')
@php
    $openCars = $checks->where('result', 'non_compliant')->whereIn('correctiveStatus', ['open', 'in_progress']);
    $closedCars = $checks->where('result', 'non_compliant')->where('correctiveStatus', 'closed');
    $pendingRequests = $requests->whereIn('status', ['requested', 'needs_information']);
    $readyRequests = $pendingRequests->whereIn('auditStatus', ['draft', 'ready', 'returned']);
    $actions = $openCars->map(fn ($check) => ['title' => 'Corrective evidence required', 'description' => 'Review the finding and provide authorized supporting evidence.', 'code' => $check->checkCode, 'status' => $check->correctiveStatus, 'url' => '/auditee/finding-details/'.$check->getKey()])
        ->values()->concat($readyRequests->map(fn ($entry) => ['title' => $entry['status'] === 'needs_information' ? 'Provide more information' : 'Respond to evidence request', 'description' => $entry['description'], 'code' => $entry['auditCode'], 'status' => $entry['status'], 'url' => '/auditee/evidence-requests#request-'.$entry['id']]));
    // Notifications already describe result and CAR updates; omit matching result rows.
    $notifiedChecks = $notifications->pluck('relatedCheckId')->filter()->map(fn ($id) => (string) $id);
    $activity = $notifications->map(fn ($note) => ['title' => $note->title, 'detail' => $note->message, 'at' => $note->createdAt, 'url' => '/auditee/notifications'])
        ->values()->concat($checks->reject(fn ($check) => $notifiedChecks->contains((string) $check->getKey()))->map(fn ($check) => ['title' => $check->checkCode, 'detail' => $check->result === 'compliant' ? 'Legacy check: compliant' : 'Legacy check: non-conformance', 'at' => $check->createdAt, 'url' => '/auditee/finding-details/'.$check->getKey()]))
        ->sortByDesc('at')->take(5);
@endphp
<div class="workflow-page auditee-dashboard">
    @include('auditee.organizational-oversight')
    <div class="auditor-summary-grid">
        @foreach([['Audit Results', $published->count(), 'clipboard-check', '/auditee/my-findings', 'Shared accepted checklist decisions'], ['Open CARs', $openCars->count(), 'triangle-alert', '/auditee/my-findings?actionable=1', 'Legacy checks requiring follow-up'], ['Pending Evidence', $pendingRequests->count(), 'files', '/auditee/evidence-requests', 'Requests awaiting your response'], ['Closed CARs', $closedCars->count(), 'check-check', '/auditee/my-findings?archived=1', 'Closed legacy corrective actions']] as [$label, $count, $icon, $url, $hint])
        <a class="auditor-summary-card" href="{{ $url }}"><div><h3>{{ $label }}</h3><i data-lucide="{{ $icon }}" @class(['auditor-red' => $label === 'Open CARs', 'auditor-green' => $label === 'Closed CARs']) aria-hidden="true"></i></div><strong>{{ $count }}</strong><p class="sr-only">{{ $hint }}</p>@if($label === 'Audit Results')<p class="sr-only">{{ $checks->count() }} legacy checks reported separately</p>@endif</a>
        @endforeach
    </div>
    <section class="workflow-card" aria-labelledby="next-actions-title">
        <div class="dashboard-section-heading"><h2 id="next-actions-title">My Next Actions</h2><span class="dashboard-badge">{{ $actions->count() }} {{ $actions->count() === 1 ? 'action' : 'actions' }}</span></div>
        @forelse($actions->take(4) as $action)
        <article class="dashboard-record dashboard-action"><div><h3>{{ $action['title'] }}</h3><p>{{ $action['code'] }} <span class="dashboard-badge">{{ ucfirst(str_replace('_', ' ', $action['status'])) }}</span></p><p>{{ $action['description'] }}</p></div><a class="dashboard-action-link" @if(str_starts_with($action['url'], '/auditee/finding-details/')) data-result-modal-open data-result-modal-tab="corrective" aria-haspopup="dialog" @endif href="{{ $action['url'] }}{{ str_starts_with($action['url'], '/auditee/finding-details/') ? '#corrective' : '' }}">View action <span aria-hidden="true">&rarr;</span><span class="sr-only"> for {{ $action['code'] }}</span></a></article>
        @empty
        <div class="dashboard-empty"><i data-lucide="circle-check" aria-hidden="true"></i><div><h3>You're up to date</h3><p>No corrective actions or evidence requests are ready for your response.</p></div></div>
        @endforelse
        @if($actions->count() > 4)<div class="workflow-actions"><a href="/auditee/my-findings?actionable=1">All corrective actions</a><a href="/auditee/evidence-requests">All evidence requests</a></div>@endif
    </section>
    <div class="workflow-grid">
        <section class="workflow-card" aria-labelledby="compliance-title">
            <div class="dashboard-section-heading"><h2 id="compliance-title">Compliance Summary</h2><a href="/auditee/my-findings">All results</a></div>
            @php
                $compliant = $published->where('result', 'C')->count();
                $nonConforming = $published->where('result', 'N')->count();
                $observations = $published->where('result', 'O')->count();
                $decisionTotal = $compliant + $nonConforming + $observations;
                $compliantPercent = $decisionTotal ? $compliant / $decisionTotal * 100 : 0;
                $classifiedPercent = $decisionTotal ? ($compliant + $nonConforming) / $decisionTotal * 100 : 0;
            @endphp
            <div class="auditor-compliance-chart dashboard-compliance-chart">
                <div class="auditor-donut" style="background: {{ $decisionTotal ? 'conic-gradient(#10b981 0% '.$compliantPercent.'%, #ef4444 '.$compliantPercent.'% '.$classifiedPercent.'%, #f5b942 '.$classifiedPercent.'% 100%)' : '#cbd5e1' }}" role="img" aria-label="Shared accepted checklist decisions: {{ $compliant }} compliant, {{ $nonConforming }} non-conforming, {{ $observations }} observations; {{ $decisionTotal }} total">
                    <div><strong>{{ $decisionTotal }}</strong></div>
                </div>
                <div class="auditor-chart-legend">
                    <p><span class="auditor-legend-dot compliant" aria-hidden="true"></span><span>C: {{ $compliant }}<span class="sr-only"> Compliant</span></span></p>
                    <p><span class="auditor-legend-dot noncompliant" aria-hidden="true"></span><span>N: {{ $nonConforming }}<span class="sr-only"> Non-Conformance</span></span></p>
                    <p><span class="auditor-legend-dot observation" aria-hidden="true"></span><span>O: {{ $observations }}<span class="sr-only"> Observation</span></span></p>
                </div>
            </div>
            <p class="workflow-muted">Illustrative combined counts; legacy results may need separate reporting.</p>
        </section>
        <section class="workflow-card dashboard-evidence-card" aria-labelledby="evidence-title">
            <div class="dashboard-section-heading"><h2 id="evidence-title">Evidence Requests</h2><a href="/auditee/evidence-requests">View all</a></div>
            @forelse($pendingRequests->sortByDesc('createdAt')->take(3) as $entry)
            <article class="dashboard-record"><h3><a href="/auditee/evidence-requests#request-{{ $entry['id'] }}">{{ $entry['auditCode'] }}</a></h3><p>{{ $entry['description'] }}</p><span class="dashboard-badge">{{ in_array($entry['auditStatus'], ['draft', 'ready', 'returned']) ? ucfirst(str_replace('_', ' ', $entry['status'])) : 'Read-only: audit under review or locked' }}</span></article>
            @empty
            <div class="dashboard-empty"><i data-lucide="inbox" aria-hidden="true"></i><div><h3>No pending requests</h3><p>New audit evidence requests appear here.</p></div></div>
            @endforelse
        </section>
    </div>
    <section class="workflow-card" aria-labelledby="activity-title">
        <div class="dashboard-section-heading"><h2 id="activity-title">Recent Activity</h2><a href="/auditee/notifications">All notifications</a></div>
        @forelse($activity as $entry)
        <article class="dashboard-record"><div class="dashboard-section-heading"><h3><a @if(str_starts_with($entry['url'], '/auditee/finding-details/')) data-result-modal-open aria-haspopup="dialog" @endif href="{{ $entry['url'] }}">{{ $entry['title'] }}</a></h3>@if($entry['at'])<time datetime="{{ $entry['at']->toIso8601String() }}">{{ $entry['at']->format('M j, Y') }}</time>@endif</div><p>{{ $entry['detail'] }}</p></article>
        @empty
        <div class="dashboard-empty"><i data-lucide="bell" aria-hidden="true"></i><div><h3>No activity yet</h3><p>New results and audit updates will appear here.</p></div></div>
        @endforelse
    </section>
</div>
@include('auditee.result-modal')
@endsection
