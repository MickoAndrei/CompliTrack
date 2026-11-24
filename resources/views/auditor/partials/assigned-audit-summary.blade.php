<div class="maa-summary" data-my-audit-summary aria-label="Assignment summary">
    @foreach([['total', 'Total Assigned', 'clipboard-list', 'All audits assigned to you'], ['inProgress', 'In Progress', 'clock', 'Ready, active, and returned audits'], ['awaitingReview', 'Awaiting Review', 'send', 'Submitted audits pending review'], ['completed', 'Completed', 'circle-check', 'Accepted by the Audit Team Leader']] as [$key, $label, $icon, $help])
    <article class="maa-metric" title="{{ $help }}"><div><span>{{ $label }}</span><i data-lucide="{{ $icon }}" aria-hidden="true"></i></div><strong data-metric="{{ $key }}">{{ $summary[$key] }}</strong></article>
    @endforeach
</div>
