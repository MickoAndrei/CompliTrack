@php
    $hasCar = $check->result === 'non_compliant' && in_array($check->correctiveStatus, ['open', 'in_progress', 'closed'], true);
    $needsEvidence = $hasCar && $check->correctiveStatus !== 'closed';
@endphp
<div data-result-detail class="result-detail">
    <header data-result-detail-header class="result-detail-header">
        <p class="result-detail-eyebrow">Audit Result Details</p>
        <h2 data-result-title>{{ $check->referenceStandard ?: $check->checkCode }}</h2>
        <p class="result-detail-reference">Reference: {{ $check->checkCode }}</p>
        <div class="result-detail-badges"><x-status-badge class="results-status-badge" :status="$check->result" :label="$check->result === 'compliant' ? 'C — Compliant' : 'N — Non-Conformance'"/><x-status-badge class="results-status-badge result-detail-car-badge" :data-status="$hasCar ? $check->correctiveStatus : 'none'" :status="$hasCar ? $check->correctiveStatus : 'none'" :label="$hasCar ? 'Corrective Action '.ucfirst(str_replace('_', ' ', $check->correctiveStatus)) : 'No corrective action'"/><span class="result-detail-review-date">Review date: Not recorded</span></div>
    </header>
    <div data-result-detail-body>
        <div class="result-detail-tabs" role="tablist" aria-label="Audit result sections">
            @foreach(['overview' => 'Overview', 'evidence' => 'Evidence Reviewed', 'corrective' => 'Corrective Action'] as $key => $label)
            <button type="button" role="tab" id="result-tab-{{ $key }}" aria-controls="result-panel-{{ $key }}" aria-selected="{{ $key === 'overview' ? 'true' : 'false' }}" tabindex="{{ $key === 'overview' ? '0' : '-1' }}" data-result-tab="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <section role="tabpanel" id="result-panel-overview" aria-labelledby="result-tab-overview" data-result-panel="overview" tabindex="0">
            <h2>Overview</h2>
            <dl class="workflow-details"><dt>Audited department</dt><dd>{{ $departmentName ?? 'Not recorded' }}</dd><dt>Auditor</dt><dd>{{ $auditorName ?? 'Not recorded' }}</dd><dt>Reference Standard</dt><dd>{{ $check->referenceStandard ?: 'Not recorded' }}</dd><dt>Result recorded</dt><dd>{{ $check->createdAt?->format('M j, Y') ?? 'Not recorded' }}</dd></dl>
            <div class="results-detail-block"><h3>Check item / requirement</h3><p>No separate audit question is recorded for this legacy compliance result.</p></div>
            <div class="results-detail-block"><h3>Auditor remarks</h3><p>{{ $check->remarks ?: 'No remarks recorded for this result.' }}</p></div>
            <div class="results-detail-block"><h3>Finding</h3><p>{{ $check->result === 'non_compliant' ? 'Non-conformance recorded against the reference standard. See the auditor remarks for the recorded explanation.' : 'Compliant result. No non-conformance finding is recorded.' }}</p></div>
        </section>
        <section role="tabpanel" id="result-panel-evidence" aria-labelledby="result-tab-evidence" data-result-panel="evidence" tabindex="0">
            <h2>Evidence Reviewed and References</h2>
            @forelse($archives as $archive)<article class="workflow-response"><a href="/api/v1/archives/{{ $archive->getKey() }}/download">{{ $archive->originalName }}</a><p class="workflow-muted">Authorized linked evidence</p></article>@empty<p class="results-empty">No accessible files are linked to this result.</p>@endforelse
            @forelse($check->evidenceResponses ?? [] as $response)
            <article class="workflow-response"><h3>{{ ucfirst(str_replace('-', ' ', $response['method'])) }}</h3><p class="workflow-muted">Submitted {{ $response['at'] }}</p>@if(!empty($response['reference']))<p>Reference: {{ $response['reference'] }}</p>@endif<p>{{ $response['notes'] }}</p>@if($response['method'] === 'physical-original')<p>Original availability: {{ str_replace('-', ' ', $response['availability'] ?? 'not recorded') }}</p><p>Physical-original signature verification: Not recorded. Availability does not confirm verification.</p>@endif</article>
            @empty<p class="workflow-muted">No corrective evidence references submitted yet.</p>@endforelse
            <p class="workflow-muted">Only permitted linked files and references are shown. A submitted file or reference does not confirm auditor verification.</p>
        </section>
        <section role="tabpanel" id="result-panel-corrective" aria-labelledby="result-tab-corrective" data-result-panel="corrective" tabindex="0">
            <div class="results-section-heading"><h2>Corrective Action</h2><x-status-badge class="results-status-badge" :status="$hasCar ? $check->correctiveStatus : 'none'" :label="$hasCar ? ucfirst(str_replace('_', ' ', $check->correctiveStatus)) : 'No CAR'"/></div>
            <p>Deadline: {{ $check->dueDate?->format('M j, Y') ?? 'Not recorded' }}</p>
            @if($needsEvidence)<div class="results-next-action"><h3>Next action: provide corrective evidence</h3><p>Review the finding and provide authorized supporting evidence for auditor review.</p></div>@else<p class="results-empty">{{ $hasCar ? 'This corrective action is closed. No response is required.' : 'No corrective response is required for this result.' }}</p>@endif
            <h3>Status history</h3>
            @forelse($check->statusHistory ?? [] as $event)<article class="workflow-response"><x-status-badge class="results-status-badge" :status="$event['status']"/><p>{{ $event['remarks'] ?? 'No remarks recorded.' }}</p></article>@empty<p class="workflow-muted">No status history recorded.</p>@endforelse
            @if($needsEvidence)
            <section id="corrective-evidence" class="results-detail-block"><h3>Provide Corrective Evidence</h3><p>Provide a reference or arrange an original for inspection, or <a href="/auditee/upload?checkId={{ $check->getKey() }}">upload an authorized file</a>. Submission does not change the result or close the CAR.</p>
                @include('auditee.evidence-response-form', ['entry' => [], 'responseAction' => '/auditee/finding-details/'.$check->getKey().'/response', 'canDraft' => false])
            </section>
            @endif
        </section>
    </div>
</div>
