@if(!empty($oversight))
<section class="workflow-card" aria-labelledby="qms-oversight-title">
    <div class="dashboard-section-heading"><h2 id="qms-oversight-title">{{ $oversight['responsibility'] }} · Authorized oversight</h2><a href="/auditee/archives">Browse archives</a></div>
    <p>{{ $oversight['units'] }} authorized departments / offices · {{ $oversight['audits'] }} audits · {{ $oversight['openCars'] }} outstanding corrective actions</p>
    <p class="workflow-muted">Read-only organizational summaries. Evidence responses and audit review actions remain with their assigned accounts.</p>
    @forelse($oversight['records'] as $record)
        <article class="dashboard-record"><div class="dashboard-section-heading"><h3>{{ $record['reference'] }}</h3><span class="dashboard-badge">{{ $record['status'] }}</span></div><p>{{ $record['department'] }}</p></article>
    @empty
        <p class="workflow-muted">No audits have been assigned within your authorized scope.</p>
    @endforelse
</section>
@endif
