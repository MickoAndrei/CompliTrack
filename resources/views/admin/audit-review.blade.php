@php($date = fn ($value) => \App\Services\AtlWorkspace::date($value))
<div class="atl-review-record" data-audit-id="{{ $audit->getKey() }}">
    <header class="atl-review-overview">
        <span class="atl-eyebrow">{{ ucfirst($audit->status) }} · Version {{ $audit->version }}</span>
        <h3>{{ $audit->auditCode }} — {{ $audit->title }}</h3>
        <p>{{ $audit->departmentName }} · Scheduled {{ $date($audit->schedule) }}</p>
        <p>Assigned auditor: {{ \App\Models\User::find($audit->auditorId)?->fullName ?? 'Account unavailable' }}</p>
        <dl><dt>Scope</dt><dd>{{ $audit->scope }}</dd><dt>Criteria</dt><dd>{{ $audit->criteria }}</dd></dl>
        <p>{{ $progress['evaluated'] }}/{{ $progress['total'] }} evaluated · C {{ $progress['C'] }} · N {{ $progress['N'] }} · O {{ $progress['O'] }}</p>
        <p class="atl-muted">Auditor evaluations are read-only. Return for Revision when clarification or corrections are needed.</p>
    </header>
    <section aria-label="Checklist questions and results">
        <h3>Checklist Results</h3>
        @forelse($audit->items ?? [] as $index => $item)
        <article class="atl-review-item">
            <div class="atl-section-heading"><h4>{{ $index + 1 }}. {{ $item['question'] ?? 'Question not recorded' }}</h4><span class="atl-status">{{ $item['result'] ?? 'Pending' }}</span></div>
            <p class="atl-muted">{{ $item['reference'] ?? '' }}</p>
            <dl><dt>Auditor remarks</dt><dd>{{ $item['remarks'] ?? 'Not recorded' }}</dd><dt>Finding</dt><dd>{{ $item['finding'] ?? 'None recorded' }}</dd></dl>
            <h5>Evidence reviews</h5>
            @forelse($item['evidence'] ?? [] as $evidence)
            <div class="atl-evidence-note"><strong>{{ $evidence['method'] ?? 'Evidence review' }}</strong><p>{{ $evidence['reference'] ?? '' }}</p><p>{{ $evidence['notes'] ?? '' }}</p>@if(isset($evidence['signature']))<p>Signature: {{ $evidence['signature'] }}</p>@endif<time>{{ $date($evidence['reviewedAt'] ?? null) }}</time></div>
            @empty<p class="atl-muted">No evidence reviews recorded.</p>@endforelse
        </article>
        @empty<p class="atl-muted">No checklist questions recorded.</p>@endforelse
    </section>
    <section><h3>Authorized Evidence References</h3>
        @forelse($attachments as $attachment)
        <p>@if($attachment['canDownload'])<a href="/api/v1/archives/{{ $attachment['id'] }}/download">{{ $attachment['name'] }}</a>@else{{ $attachment['name'] }} <span class="atl-muted">Download not authorized</span>@endif</p>
        @empty<p class="atl-muted">No linked files are available within your archive permissions. Recorded evidence reviews remain above.</p>@endforelse
    </section>
    <section><h3>Submitted Evidence Responses</h3>
        @forelse($audit->evidenceRequests ?? [] as $entry)
        <article class="atl-review-item"><h4>{{ $entry['title'] ?? $entry['description'] ?? 'Evidence request' }}</h4><p class="atl-muted">{{ ucfirst(str_replace('_', ' ', $entry['status'] ?? 'Not recorded')) }}</p>
            @forelse($entry['responses'] ?? [] as $response)<div class="atl-evidence-note"><strong>{{ $response['method'] ?? 'Response' }}</strong><p>{{ $response['reference'] ?? '' }}</p><p>{{ $response['notes'] ?? '' }}</p><time>{{ $date($response['at'] ?? null) }}</time>@if(!empty($response['review']))<p>Evidence review: {{ $response['review']['feedback'] ?? '' }}</p><p>Signature: {{ $response['review']['signature'] ?? 'Not recorded' }}</p>@endif</div>@empty<p class="atl-muted">No submitted responses recorded.</p>@endforelse
        </article>
        @empty<p class="atl-muted">No evidence requests recorded.</p>@endforelse
    </section>
    <section><h3>Audit Interviews and Process Notes</h3>
        @forelse($audit->interviews ?? [] as $interview)<article class="atl-review-item"><h4>{{ $interview['auditeeName'] ?? 'Interview' }} · {{ $interview['process'] ?? '' }}</h4><p>{{ $interview['topics'] ?? '' }}</p><p>{{ $interview['responses'] ?? '' }}</p><p>{{ $interview['notes'] ?? '' }}</p><time>{{ $date($interview['at'] ?? null) }}</time></article>@empty<p class="atl-muted">No interview notes recorded.</p>@endforelse
    </section>
    <section><h3>Submission and Review History</h3>
        @forelse($audit->history ?? [] as $event)
        <details class="atl-history"><summary>{{ ucfirst(str_replace('_', ' ', $event['action'] ?? 'Update')) }} · {{ $date($event['at'] ?? null) }}</summary><p>Actor: {{ $event['actor'] ?? 'Not recorded' }}</p><p>{{ $event['notes'] ?? '' }}</p>
            @if(!empty($event['previousItems']))<h4>Previous evaluation snapshot</h4>@foreach($event['previousItems'] as $previous)<p><strong>{{ $previous['question'] ?? 'Question' }}</strong> · {{ $previous['result'] ?? 'Pending' }}</p><p>{{ $previous['remarks'] ?? '' }}</p><p>{{ $previous['finding'] ?? '' }}</p>@endforeach @endif
        </details>
        @empty<p class="atl-muted">No submission or review history recorded.</p>@endforelse
    </section>
    @if($audit->status === 'submitted')
    <form action="/audits/{{ $audit->getKey() }}" method="POST" data-atl-review-form class="atl-review-decision">@csrf
        <input type="hidden" name="version" value="{{ $audit->version }}">
        <label for="atl-review-notes">Review notes<textarea id="atl-review-notes" name="reviewNotes" required maxlength="5000" rows="3"></textarea></label>
        <div class="atl-actions"><button type="submit" name="action" value="return" class="atl-secondary">Return for Revision</button><button type="submit" name="action" value="accept" class="atl-primary">Accept Audit Results</button></div>
    </form>
    @else<p class="atl-muted">Review decisions are available only for submitted audits assigned to you.</p>@endif
</div>
