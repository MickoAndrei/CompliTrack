@php($date = fn ($value) => \App\Services\AtlWorkspace::date($value))
@php($submission = \App\Services\AuditReviewQueue::submission($audit))
<div class="atl-review-record" data-audit-id="{{ $audit->getKey() }}">
    <h2 class="sr-only" data-review-dialog-title>Audit Results Review — {{ $audit->auditCode }}</h2>
    <p class="sr-only" data-review-dialog-meta>{{ \App\Support\AuditStatus::label($audit->status) }} · Version {{ $audit->version }}</p>
    <div class="atl-review-layout">
        <main class="atl-review-main">
            <header class="atl-review-overview">
                <div class="atl-review-overview-heading">
                    <div><p class="atl-eyebrow">{{ $audit->auditCode }}</p><h3>{{ $audit->departmentName }}</h3><p class="atl-review-audit-title">{{ $audit->title }}</p></div>
                    <span class="review-badge is-{{ $audit->status }}">{{ \App\Support\AuditStatus::label($audit->status) }}</span>
                </div>
                <div class="atl-review-meta">
                    <p><span>Assigned auditor</span><strong>{{ $people[(string) $audit->auditorId]?->fullName ?? 'Account unavailable' }}</strong></p>
                    <p><span>Submitted</span><strong>{{ $date($submission['at']) }}</strong></p>
                    <p><span>Scheduled</span><strong>{{ $date($audit->schedule) }}</strong></p>
                </div>
                <details class="atl-review-scope"><summary>Audit scope and criteria</summary><dl><dt>Scope</dt><dd>{{ $audit->scope }}</dd><dt>Criteria</dt><dd>{{ $audit->criteria }}</dd></dl></details>
            </header>
            <nav class="atl-review-tabs" role="tablist" aria-label="Review sections">
                <button type="button" id="review-tab-checklist" role="tab" aria-controls="review-checklist" aria-selected="true" tabindex="0">Checklist</button>
                <button type="button" id="review-tab-evidence" role="tab" aria-controls="review-evidence" aria-selected="false" tabindex="-1">Evidence</button>
                <button type="button" id="review-tab-findings" role="tab" aria-controls="review-findings" aria-selected="false" tabindex="-1">Findings</button>
                <button type="button" id="review-tab-history" role="tab" aria-controls="review-history" aria-selected="false" tabindex="-1">History</button>
            </nav>
            @if($submission['resubmitted'])<p class="review-revision">Resubmission. {{ $revisionBaseline !== null ? 'Revisions are compared with the latest returned evaluation snapshot.' : 'Historical snapshots are insufficient to identify revisions.' }}</p>@endif
            <section id="review-checklist" class="atl-review-section" role="tabpanel" aria-labelledby="review-tab-checklist" tabindex="0">
                <div class="atl-review-section-heading"><h3 id="review-checklist-title">Checklist Evaluation</h3><span>{{ $progress['total'] }} {{ $progress['total'] === 1 ? 'item' : 'items' }}</span></div>
                @forelse($audit->items ?? [] as $index => $item)
                @php($baseline = !empty($item['id']) ? $revisionBaseline?->get($item['id']) : null)
                @php($revised = $baseline && collect(['question', 'reference', 'result', 'remarks', 'finding', 'evidence'])->contains(fn ($field) => ($item[$field] ?? null) != ($baseline[$field] ?? null)))
                @php($result = match($item['result'] ?? null) { 'C' => ['Compliant', 'is-compliant'], 'N' => ['Nonconformity', 'is-nonconformity'], 'O' => ['Observation', 'is-observation'], default => ['Pending', 'is-pending'] })
                <article class="atl-review-item {{ $revised ? 'review-changed' : '' }}">
                    @if($revised)<span class="review-revision">Revised since return</span><details><summary>Previously returned evaluation</summary><p>{{ $baseline['result'] ?? 'Pending' }} · {{ $baseline['remarks'] ?? '' }}</p><p>{{ $baseline['finding'] ?? '' }}</p></details>@elseif($revisionBaseline !== null && !$baseline)<span class="review-revision">No matching historical question</span>@endif
                    <div class="atl-review-item-heading">
                        <div><h4>{{ $index + 1 }}. {{ $item['question'] ?? 'Question not recorded' }}</h4><p class="atl-muted">{{ $item['reference'] ?? 'No reference recorded' }}</p></div>
                        <span class="atl-review-result {{ $result[1] }}">{{ $result[0] }}</span>
                    </div>
                    <div class="atl-review-item-details">
                        <p><strong>Auditor remarks</strong>{{ $item['remarks'] ?? 'Not recorded' }}</p>
                        <p><strong>Finding</strong>{{ $item['finding'] ?? 'No finding recorded' }}</p>
                    </div>
                    <h5>Evidence reviews</h5>
                    @forelse($item['evidence'] ?? [] as $evidence)
                    <div class="atl-evidence-note"><strong>{{ $evidence['method'] ?? 'Evidence review' }}</strong><p>{{ $evidence['reference'] ?? '' }}</p><p>{{ $evidence['notes'] ?? '' }}</p>@if(isset($evidence['signature']))<p>Signature: {{ $evidence['signature'] }}</p>@endif<time>{{ $date($evidence['reviewedAt'] ?? null) }}</time></div>
                    @empty<p class="atl-muted">No evidence reviews recorded.</p>@endforelse
                </article>
                @empty<p class="atl-muted">No checklist questions recorded.</p>@endforelse
            </section>
            <section id="review-evidence" class="atl-review-section" role="tabpanel" aria-labelledby="review-tab-evidence" tabindex="0" hidden>
                <h3 id="review-evidence-title">Evidence Reviewed</h3>
                <h4>Authorized Evidence References</h4>
                @forelse($attachments as $attachment)
                <p class="atl-review-attachment">@if($attachment['canDownload'])<a class="atl-review-document-link" href="/api/v1/documents/{{ $attachment['id'] }}/download"><i data-lucide="file-text" aria-hidden="true"></i><span>{{ $attachment['name'] }}</span><i data-lucide="download" aria-hidden="true"></i></a>@else{{ $attachment['name'] }} <span class="atl-muted">Download not authorized</span>@endif</p>
                @empty<p class="atl-muted">No linked files are available within your archive permissions. Recorded evidence reviews remain above.</p>@endforelse
                <h4>Submitted Evidence Responses</h4>
                @forelse($audit->evidenceRequests ?? [] as $entry)
                <article class="atl-review-item"><h4>{{ $entry['title'] ?? $entry['description'] ?? 'Evidence request' }}</h4><p class="atl-muted">{{ ucfirst(str_replace('_', ' ', $entry['status'] ?? 'Not recorded')) }}</p>
                    @forelse($entry['responses'] ?? [] as $response)<div class="atl-evidence-note"><strong>{{ $response['method'] ?? 'Response' }}</strong><p>{{ $response['reference'] ?? '' }}</p><p>{{ $response['notes'] ?? '' }}</p><time>{{ $date($response['at'] ?? null) }}</time>@if(!empty($response['review']))<p>Evidence review: {{ $response['review']['feedback'] ?? '' }}</p><p>Signature: {{ $response['review']['signature'] ?? 'Not recorded' }}</p>@endif</div>@empty<p class="atl-muted">No submitted responses recorded.</p>@endforelse
                </article>
                @empty<p class="atl-muted">No evidence requests recorded.</p>@endforelse
                <h4>Audit Interviews and Process Notes</h4>
                @forelse($audit->interviews ?? [] as $interview)<article class="atl-review-item"><h4>{{ $interview['auditeeName'] ?? 'Interview' }} · {{ $interview['process'] ?? '' }}</h4><p>{{ $interview['topics'] ?? '' }}</p><p>{{ $interview['responses'] ?? '' }}</p><p>{{ $interview['notes'] ?? '' }}</p><time>{{ $date($interview['at'] ?? null) }}</time></article>@empty<p class="atl-muted">No interview notes recorded.</p>@endforelse
            </section>
            <section id="review-findings" class="atl-review-section" role="tabpanel" aria-labelledby="review-tab-findings" tabindex="0" hidden>
                <h3 id="review-findings-title">Findings and Corrective Actions</h3>
                @forelse(collect($audit->items ?? [])->filter(fn ($item) => trim($item['finding'] ?? '') !== '') as $item)<article class="atl-review-item"><h4>{{ $item['question'] ?? 'Question not recorded' }}</h4><p>{{ $item['result'] ?? '' }} · {{ $item['finding'] }}</p></article>@empty<p class="atl-muted">No checklist findings recorded.</p>@endforelse
                <p class="atl-muted">{{ $linkedCars }} checklist-linked {{ $linkedCars === 1 ? 'CAR is' : 'CARs are' }} available in Corrective Monitoring. Review decisions do not change CAR status.</p>
            </section>
            <section id="review-history" class="atl-review-section" role="tabpanel" aria-labelledby="review-tab-history" tabindex="0" hidden>
                <h3 id="review-history-title">Submission and Review History</h3>
                @forelse($audit->history ?? [] as $event)
                <details class="atl-history"><summary>{{ ucfirst(str_replace('_', ' ', $event['action'] ?? 'Update')) }} · {{ $date($event['at'] ?? null) }}</summary><p>Actor: {{ $people[(string) ($event['actor'] ?? '')]?->fullName ?? 'Account unavailable' }}</p><p>{{ $event['notes'] ?? '' }}</p>
                    @if(!empty($event['previousAssignment']))<h4>Previous assignment</h4><p>{{ $event['previousAssignment']['title'] ?? '' }} · {{ $event['previousAssignment']['schedule'] ?? '' }}</p><p>Auditor: {{ (string) ($event['previousAssignment']['auditorId'] ?? '') }}</p><p>{{ $event['previousAssignment']['scope'] ?? '' }}</p><p>{{ $event['previousAssignment']['criteria'] ?? '' }}</p>@endif
                    @if(!empty($event['previousItems']))<h4>Previous evaluation snapshot</h4>@foreach($event['previousItems'] as $previous)<p><strong>{{ $previous['question'] ?? 'Question' }}</strong> · {{ $previous['result'] ?? 'Pending' }}</p><p>{{ $previous['remarks'] ?? '' }}</p><p>{{ $previous['finding'] ?? '' }}</p>@endforeach @endif
                </details>
                @empty<p class="atl-muted">No submission or review history recorded.</p>@endforelse
            </section>
        </main>
        <aside class="atl-review-sidebar" aria-label="Review summary and decision">
            <section class="atl-review-summary" aria-labelledby="review-summary-title">
                <h3 id="review-summary-title">Review Summary</h3>
                <div class="atl-review-progress-label"><span>Checklist progress</span><strong>{{ $progress['percent'] }}%</strong></div>
                <div class="atl-review-progress" role="progressbar" aria-label="Checklist progress" aria-valuemin="0" aria-valuemax="{{ max(1, $progress['total']) }}" aria-valuenow="{{ $progress['evaluated'] }}">
                    <span style="width:{{ $progress['percent'] }}%"></span>
                </div>
                <p class="atl-review-progress-copy">{{ $progress['evaluated'] }} of {{ $progress['total'] }} evaluated</p>
                <div class="atl-review-counts">
                    <p><span><i class="is-compliant"></i>Compliant</span><strong>{{ $progress['C'] }}</strong></p>
                    <p><span><i class="is-nonconformity"></i>Nonconformity</span><strong>{{ $progress['N'] }}</strong></p>
                    <p><span><i class="is-observation"></i>Observation</span><strong>{{ $progress['O'] }}</strong></p>
                </div>
                <div class="atl-review-car-count"><span>Linked CARs</span><strong>{{ $linkedCars }}</strong></div>
                <p class="atl-review-legend">C: Compliant · N: Nonconformity · O: Observation</p>
            </section>
            <section class="atl-review-decision" aria-labelledby="atl-review-decision-title">
                <h3 id="atl-review-decision-title">ATL Review Decision</h3>
                @if($audit->status === 'submitted')
                <form action="/audits/{{ $audit->getKey() }}" method="POST" data-atl-review-form>@csrf
                    <input type="hidden" name="version" value="{{ $audit->version }}">
                    <label for="atl-review-notes">Review notes <small>Required when returning an audit for revision.</small><textarea id="atl-review-notes" name="reviewNotes" maxlength="5000" rows="4" placeholder="Enter review notes..."></textarea></label>
                    <div class="atl-actions"><button type="submit" name="action" value="return" class="atl-secondary">Return for Revision</button><button type="submit" name="action" value="accept" class="atl-primary">Accept Results</button></div>
                </form>
                @else<p class="atl-muted">Review decisions are available only for submitted audits assigned to you.</p>@endif
            </section>
        </aside>
    </div>
</div>
