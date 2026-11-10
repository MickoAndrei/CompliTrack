@props(['type' => 'activity', 'columns' => 3])
<div {{ $attributes->class(['sa-loading-placeholder', 'sa-skeleton-'.$type]) }} aria-hidden="true">
    @if ($type === 'table')
        <div class="responsive-table-wrapper"><table class="sa-skeleton-table">
            <thead><tr>@foreach (range(1, $columns) as $column)<th><span class="sa-skeleton sa-skeleton-name"></span></th>@endforeach</tr></thead>
            <tbody>@foreach (range(1, 5) as $row)<tr>@foreach (range(1, $columns) as $column)<td><span class="sa-skeleton sa-skeleton-detail"></span></td>@endforeach</tr>@endforeach</tbody>
        </table></div>
    @elseif ($type === 'comparison')
        @foreach (range(1, 5) as $row)
        <div class="sa-department-row sa-skeleton-row"><div><span class="sa-skeleton sa-skeleton-name"></span><span class="sa-skeleton sa-skeleton-caption"></span></div><span class="sa-skeleton sa-skeleton-bar"></span><div><span class="sa-skeleton sa-skeleton-result"></span><span class="sa-skeleton sa-skeleton-caption"></span></div></div>
        @endforeach
    @elseif ($type === 'chart')
        <span class="sa-skeleton sa-skeleton-plot"></span>
        <div class="sa-skeleton-months">@foreach (range(1, 6) as $month)<span class="sa-skeleton"></span>@endforeach</div>
    @elseif ($type === 'health')
        @foreach (range(1, 4) as $service)
        <div class="system-health-row"><span class="sa-skeleton sa-skeleton-name"></span><span class="sa-skeleton sa-skeleton-result"></span></div>
        @endforeach
    @elseif ($type === 'summary')
        @foreach (range(1, 3) as $value)<span class="sa-skeleton sa-skeleton-name"></span>@endforeach
    @else
        @foreach (range(1, 3) as $entry)
        <div class="sa-skeleton-activity-row"><div><span class="sa-skeleton sa-skeleton-name"></span><span class="sa-skeleton sa-skeleton-result"></span></div><span class="sa-skeleton sa-skeleton-detail"></span><span class="sa-skeleton sa-skeleton-caption"></span></div>
        @endforeach
    @endif
</div>
