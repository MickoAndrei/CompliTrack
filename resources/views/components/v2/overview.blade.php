{{-- Figma web overview frames 4:264, 4:694, 6:1187, 6:1043. Values come from role-scoped queries. --}}
<section class="ct-overview">
    <header class="ct-page-heading ct-overview-heading">
        <div><h1>{{ $title }}</h1><p>{{ $subtitle }}</p></div>
        @if($plans->isNotEmpty())
            <form method="GET" action="{{ url()->current() }}" class="ct-period-form">
                <label for="ct-overview-plan" class="sr-only">Audit cycle</label>
                <i data-lucide="calendar-days" aria-hidden="true"></i>
                <select id="ct-overview-plan" name="planId" onchange="this.form.submit()"><option value="">All audit cycles</option>@foreach($plans as $id => $name)<option value="{{ $id }}" @selected($selected === $id)>{{ $name }}</option>@endforeach</select><noscript><button type="submit">Apply</button></noscript>
            </form>
        @else
            <span class="ct-period-label"><i data-lucide="calendar-days" aria-hidden="true"></i>{{ $period }}</span>
        @endif
    </header>
    <div class="ct-metric-grid">
        @foreach($metrics as [$label, $value, $detail])
            <article class="ct-metric"><p>{{ $label }}</p><strong>{{ number_format($value) }}</strong><span>{{ $detail }}</span></article>
        @endforeach
    </div>
    <div class="ct-chart-grid">
        <section class="ct-panel ct-activity" aria-labelledby="ct-activity-title">
            <header><h2 id="ct-activity-title">{{ $chartTitle }}</h2><small>{{ $period }}</small></header>
            @php($maximum = max(array_merge([1], array_column($bars, 1))))
            <div class="ct-bar-list">
                @forelse($bars as [$label, $value])
                <div class="ct-bar-row"><span title="{{ $label }}">{{ $label }}</span><div class="ct-bar-track" aria-hidden="true"><span style="width:{{ min(100, 100*$value/$maximum) }}%"></span></div><strong>{{ number_format($value) }}</strong></div>
                @empty<p class="ct-muted">No recorded checks in this scope.</p>@endforelse
            </div>
        </section>
        <section class="ct-panel ct-distribution" aria-labelledby="ct-distribution-title">
            <header><h2 id="ct-distribution-title">Status distribution</h2></header><p class="ct-muted">{{ $period }}</p>
            @php($distributionTotal = array_sum(array_column($distribution, 1)))
            <div class="ct-segments" aria-hidden="true">
                @forelse(collect($distribution)->filter(fn ($row) => $row[1] > 0) as [$label, $value, $tone])<span class="ct-tone-{{ $tone }}" style="flex:{{ $value }}"></span>@empty<span class="ct-tone-neutral" style="flex:1"></span>@endforelse
            </div>
            <div class="ct-distribution-legend">
                @foreach($distribution as [$label, $value, $tone])<div><i class="ct-tone-{{ $tone }}" aria-hidden="true"></i><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div>@endforeach
            </div>
            @if(!$distributionTotal)<p class="ct-muted">No records in this scope.</p>@endif
        </section>
    </div>
    <section class="ct-panel ct-attention" aria-labelledby="ct-attention-title">
        <header><h2 id="ct-attention-title">Needs attention</h2><small>{{ $period }}</small></header>
        <div class="ct-attention-grid">
            @foreach($attention as [$kind, $label, $href])<a href="{{ $href }}"><span class="ct-tag {{ $kind === 'Action' ? 'ct-tag-warning' : '' }}">{{ $kind }}</span><span>{{ $label }}</span><i data-lucide="chevron-right" aria-hidden="true"></i></a>@endforeach
        </div>
    </section>
    <footer class="ct-overview-footer"><span>Metrics are based on authorized records. Closed CARs do not change original audit findings.</span><a href="{{ $detailsUrl }}">Detailed overview</a></footer>
</section>
