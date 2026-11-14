<div data-my-audit-results aria-busy="false">
    <div class="maa-result-heading"><nav class="maa-pills" aria-label="Quick status filters">
        @foreach(['' => 'All', 'draft' => 'Draft', 'in_progress' => 'In Progress', 'submitted' => 'Submitted', 'returned' => 'Returned', 'accepted' => 'Completed'] as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" @if($filters['status'] === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav><span class="maa-record-count" role="status" aria-live="polite">{{ $audits->total() }} {{ $audits->total() === 1 ? 'record' : 'records' }}</span></div>
    <p class="maa-completion-note">Progress shows checklist items evaluated, not compliance percentage.</p>
    <div data-my-audit-content>
    <div class="maa-cards">
        @forelse($audits as $audit)
        <article class="maa-card" data-audit-status="{{ $audit['status'] }}">
            <div class="maa-card-heading"><div><h3>{{ $audit['title'] }}</h3><p class="maa-reference">{{ $audit['code'] }}</p></div><span class="maa-status" data-status="{{ $audit['status'] }}">{{ $audit['label'] }}</span></div>
            <div class="maa-meta"><span><i data-lucide="building-2" aria-hidden="true"></i>{{ $audit['department'] }}</span><span><i data-lucide="calendar-days" aria-hidden="true"></i>{{ $audit['scheduled'] }}</span></div>
            <div class="maa-progress-heading"><span>Checklist evaluated</span><strong>{{ $audit['progress']['evaluated'] }} of {{ $audit['progress']['total'] }} · {{ $audit['progress']['percent'] }}%</strong></div>
            <progress value="{{ $audit['progress']['evaluated'] }}" max="{{ max(1, $audit['progress']['total']) }}" aria-label="{{ $audit['title'] }}: checklist evaluation completion"></progress>
            @if($audit['status'] === 'submitted')
            <div class="maa-review-note"><i data-lucide="clock" aria-hidden="true"></i><div><strong>{{ $audit['awaitingAtl'] ? 'Awaiting Audit Team Leader Review' : 'Submitted · Review assignment unavailable' }}</strong><p>Editing is locked until the audit is returned for revision.</p></div></div>
            @elseif($audit['status'] === 'returned')
            <div class="maa-feedback"><strong>ATL review feedback</strong>@if($audit['feedback'])<p>{{ \Illuminate\Support\Str::limit($audit['feedback']['notes'], 220) }}</p>@if(mb_strlen($audit['feedback']['notes']) > 220)<details><summary>Read full feedback</summary><p>{{ $audit['feedback']['notes'] }}</p></details>@endif<small>{{ $audit['feedback']['at'] }}</small>@else<p>Open the workspace to review the return decision.</p>@endif</div>
            @elseif($audit['status'] === 'accepted')<p class="maa-final-note"><i data-lucide="circle-check" aria-hidden="true"></i>Accepted by the Audit Team Leader. Final results are read-only.</p>
            @endif
            <footer><a href="{{ $audit['url'] }}" class="maa-button maa-action">{{ $audit['action'] }}<i data-lucide="arrow-right" aria-hidden="true"></i></a></footer>
        </article>
        @empty
        <div class="maa-empty"><i data-lucide="clipboard-list" aria-hidden="true"></i><h3>{{ $summary['total'] ? 'No matching audits' : 'No Assigned Audits Yet' }}</h3><p>{{ $summary['total'] ? 'Try a different title, department, or status.' : 'Your assigned audits will appear here once an authorized audit assignment has been made.' }}</p>@if($summary['total'])<a href="/audits" class="maa-button" data-my-audit-reset>Clear filters</a>@endif</div>
        @endforelse
    </div>
    {{ $audits->links() }}
    </div>
</div>
