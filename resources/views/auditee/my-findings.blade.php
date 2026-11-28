@extends('layouts.app')
@section('title', 'My Audit Results')
@section('heading', 'My audit findings')
@section('subtitle', 'Review audit decisions, findings, and corrective actions')
@section('body-class', 'ct-v2-page')
@section('content')
<div class="workflow-page auditee-results ct-register">
<header class="ct-page-heading"><div><h1>My audit findings</h1><p>Findings associated with your account.</p></div></header>

    @include('auditor.audit-feedback')
    @if($loadError ?? false)
        <x-alert-banner>Audit results could not be loaded. Please try again. <a href="{{ request()->fullUrl() }}">Retry loading results</a></x-alert-banner>
    @endif
    @unless($loadError ?? false)
    <section class="workflow-card ct-flat-register" id="auditee-results-card" aria-labelledby="results-title" aria-busy="false">
        <h2 id="results-title" class="sr-only">Results and findings</h2>
        <form method="GET" action="/auditee/my-findings" class="results-filters ct-toolbar">
            <label class="results-search"><span class="sr-only">Search audit items</span><input type="search" name="search" maxlength="120" placeholder="Search audit items…" value="{{ request('search') }}"></label>
            <button type="submit" name="result" value="{{ request('result', '') }}" class="sr-only" tabindex="-1">Search results</button>
            <div class="results-filter-pills" aria-label="Filter results">
                @foreach(['' => 'All', 'non_compliant' => 'Nonconformity', 'compliant' => 'Compliant'] as $value => $label)
                <button type="submit" name="result" value="{{ $value }}" @class(['results-filter-pill', 'is-active' => request('result', '') === $value]) aria-pressed="{{ request('result', '') === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </form>
        <div x-page-notice data-results-error hidden role="alert">Results could not be updated. <button type="button" data-results-retry class="workflow-secondary">Retry</button></div>
        <span class="sr-only" data-results-status role="status"></span>
        <div class="results-table-wrapper ct-table-wrap" role="region" aria-label="Legacy results and findings" tabindex="0">
            <table class="data-table results-table ct-table"><caption class="sr-only">Legacy compliance checks; accepted workspace decisions are listed separately below.</caption><thead><tr><th scope="col">Audit Item</th><th scope="col">Audit Result</th><th scope="col">Corrective Action Status</th></tr></thead><tbody data-results-rows>
            @forelse($checks as $check)
            @php($hasCar = $check->result === 'non_compliant' && in_array($check->correctiveStatus, ['open', 'in_progress', 'awaiting_verification', 'returned_for_revision', 'closed'], true))
            <tr><td data-label="Audit Item"><a class="results-check-link" data-result-modal-open aria-haspopup="dialog" href="/auditee/finding-details/{{ $check->getKey() }}" aria-label="View Details for {{ $check->checkCode }}">{{ $check->checkCode }}</a><small>{{ $check->referenceStandard }} · {{ $check->createdAt?->format('M j, Y') ?? 'Date not recorded' }}</small></td>
                <td data-label="Audit Result"><x-status-badge class="results-status-badge" :status="$check->result" :label="match($check->result) { 'compliant' => 'C', 'non_compliant' => 'N', 'observation' => 'O', default => 'Unknown' }" :title="match($check->result) { 'compliant' => 'Compliant', 'non_compliant' => 'Non-Conformance', 'observation' => 'Observation', default => 'Unknown' }"/></td>
                <td data-label="Corrective Action Status"><x-status-badge class="results-status-badge results-car-badge" :data-status="$hasCar ? $check->correctiveStatus : 'none'" :status="$hasCar ? $check->correctiveStatus : 'none'" :label="$hasCar ? ucfirst(str_replace('_', ' ', $check->correctiveStatus)) : 'None'"/></td></tr>
            @empty
            <tr><td colspan="3" class="results-empty">{{ $summary['total'] ? 'No legacy results match these filters.' : 'No legacy compliance results yet. Your results will appear after an auditor completes a check.' }}</td></tr>
            @endforelse
            </tbody>
            <tbody data-results-loading hidden aria-hidden="true">
                @for($row = 0; $row < 3; $row++)
                <tr><td data-label="Audit Item"><span class="results-skeleton results-skeleton-code"></span><small><span class="results-skeleton results-skeleton-meta"></span></small></td><td data-label="Audit Result"><span class="results-skeleton results-skeleton-badge"></span></td><td data-label="Corrective Action Status"><span class="results-skeleton results-skeleton-badge"></span></td></tr>
                @endfor
            </tbody></table>
        </div>
        <div class="results-pagination"><x-server-pagination :paginator="$checks" /></div>
    </section>
    <section class="workflow-card" aria-labelledby="shared-title">
        <div class="results-section-heading"><h2 id="shared-title">Shared Accepted Audit Results</h2><span class="dashboard-badge">{{ $published->count() }} shared</span></div>
        @forelse($published as $entry)
        @php($resultStatus = match($entry['result']) { 'C' => 'compliant', 'N' => 'non_compliant', 'O' => 'observation', default => 'unknown' })
        <details class="results-shared"><summary><span>{{ $entry['auditCode'] }}</span><x-status-badge class="results-status-badge" :status="$resultStatus" :label="match($entry['result']) { 'C' => 'C — Compliant', 'N' => 'N — Non-Conformance', 'O' => 'O — Observation', default => 'Unknown result' }"/><span class="results-details-label">View Details</span></summary>
            <dl class="workflow-details"><dt>Shared audit remarks</dt><dd>{{ $entry['summary'] }}</dd><dt>Department</dt><dd>{{ $entry['department'] }}</dd><dt>Shared on</dt><dd>{{ \App\Support\PortalDate::format($entry['at']) }}</dd><dt>Corrective action</dt><dd>A shared checklist decision does not automatically create a CAR.</dd></dl>
            <a href="/auditee/evidence-requests#request-{{ $entry['requestId'] }}">View permitted evidence request and responses</a>
        </details>
        @empty
        <p class="results-empty">{{ request()->hasAny(['search', 'result', 'actionable', 'archived']) ? 'No shared accepted results match these filters.' : 'No accepted workspace results have been shared with you yet.' }}</p>
        @endforelse
    </section>
    @endunless
    @include('auditee.result-modal')
</div>
@endsection
