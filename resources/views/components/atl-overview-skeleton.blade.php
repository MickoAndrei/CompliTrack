@props(['type' => 'attention'])
<div {{ $attributes->class(['atl-panel-skeleton']) }} aria-hidden="true">
    @foreach (range(1, 5) as $row)
        <div class="atl-skeleton-row">
            <div class="atl-skeleton-heading"><span class="atl-skeleton atl-skeleton-name"></span><span class="atl-skeleton atl-skeleton-badge"></span></div>
            @if ($type === 'compliance')
                <span class="atl-skeleton atl-skeleton-track"></span>
            @else
                <span class="atl-skeleton atl-skeleton-detail"></span>
            @endif
            <span class="atl-skeleton atl-skeleton-caption"></span>
        </div>
    @endforeach
</div>
