@extends('layouts.app')
@section('title', 'Corrective Action Monitoring')
@section('heading', 'Corrective Action Monitoring')
@section('subtitle', 'Track corrective actions, target closed-out dates, verification and history')
@push('styles')<link rel="stylesheet" href="{{ asset('css/car-register.css') }}?v={{ filemtime(public_path('css/car-register.css')) }}"><link rel="stylesheet" href="{{ asset('css/car-details.css') }}?v={{ filemtime(public_path('css/car-details.css')) }}"><link rel="stylesheet" href="{{ asset('css/user-management.css') }}?v={{ filemtime(public_path('css/user-management.css')) }}">@endpush
@section('body-class', 'ct-v2-page')
@section('content')
<header class="ct-page-heading"><div><h1>Corrective actions</h1><p>Review corrective actions within your authorized scope.</p></div></header>

<section class="car-register">
    <details class="ct-directory-summary"><summary>Corrective action totals</summary>    <div class="car-metrics">@foreach($summary as $label=>$count)<article><div class="car-metric-top"><span>{{ $label }}</span><svg class="car-metric-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">@switch($label)@case('Open')<path d="M8 3h8v4H8zM8 5H5v16h14V5h-3M8 12h8M8 16h5"/>@break @case('In Progress')<path d="M12 3a9 9 0 1 1-9 9M12 3v5M3 3l5 5M3 8V3h5M12 8v4l3 2"/>@break @case('Awaiting Verification')<path d="M9 3h6v4H9zM9 5H5v16h14V5h-4M8 14l3 3 5-6"/>@break @case('Overdue')<path d="M10.3 4.5 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0ZM12 9v4M12 17h.01"/>@break @default<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>@endswitch</svg></div><strong>{{ $count }}</strong></article>@endforeach</div>
</details>
    <section class="car-panel ct-flat-register"><div class="car-register-controls"><header class="car-register-header sr-only"><h3>Corrective Action Register</h3></header>
        <form method="GET" class="car-filters ct-toolbar"><input type="hidden" name="monitor" value="1"><input type="hidden" name="perPage" value="{{ request('perPage', 10) }}">
            <label>Search<input name="search" value="{{ request('search') }}" placeholder="CAR no., issue or responsible person"></label>
            <label>Workflow status<select name="status"><option value="">All statuses</option>@foreach(['open'=>'Open','in_progress'=>'In Progress','awaiting_verification'=>'Awaiting Verification','returned_for_revision'=>'Returned for Revision','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Department<select name="departmentId"><option value="">All departments</option>@foreach($departments as $unit)<option value="{{ $unit['id'] }}" @selected(request('departmentId')===$unit['id'])>{{ $unit['name'] }}</option>@endforeach</select></label>
            <label>Record type<select name="kind"><option value="">All records</option><option value="formal" @selected(request('kind')==='formal')>Issued CARs</option><option value="legacy" @selected(request('kind')==='legacy')>Legacy checks</option></select></label>
            <label>Sort by<select name="sort">@foreach(['target'=>'Target date','reference'=>'Reference','newest'=>'Newest first'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','target')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="car-check"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))>Overdue only</label><a class="car-reset" href="/admin/compliance-records?monitor=1">Reset Filter</a>
            <a class="car-export" target="_blank" rel="noopener" href="{{ request()->fullUrlWithQuery(['export'=>'log','car'=>null,'history'=>null,'page'=>null]) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 15v6h14v-6"/></svg>Export CAR Log</a>
        </form></div>
        <div class="um-table-wrap ct-table-wrap"><table class="um-table ct-table"><thead><tr><th>CAR reference</th><th>Finding</th><th>Responsible person / unit</th><th>Target Closed-Out Date <small class="car-date-format">MM/DD/YYYY</small></th><th>Workflow Status</th><th>Details</th></tr></thead><tbody>
        @forelse($cars as $car)<tr>
            <td data-label="CAR reference"><strong>{{ $car['formal'] ? $car['carNo'] : $car['checkCode'] }}</strong>@unless($car['formal'])<small>Legacy check · CAR not recorded</small>@endunless</td><td>{{ $car['official']['Issue'] }}</td>
            <td data-label="Department / Responsible"><strong>{{ $car['departmentName'] }}</strong><p>{{ $car['official']['Assigned To'] }}</p></td>
            @php($targetDate = \DateTimeImmutable::createFromFormat('!M j, Y', $car['official']['Target Closed-Out Date']))
            <td data-label="Target Closed-Out Date (MM/DD/YYYY)">{{ $targetDate ? $targetDate->format('m/d/Y') : $car['official']['Target Closed-Out Date'] }}@if($car['overdue'])<span class="car-badge overdue">Overdue</span>@endif</td>
            <td data-label="Workflow Status"><span class="car-badge {{ $car['displayStatus'] }}">{{ $car['statusLabel'] ?: 'Not recorded' }}</span></td>
            
            <td data-label="Details"><a class="car-view" data-car-history href="{{ request()->fullUrlWithQuery(['car'=>$car['_id'],'export'=>null]) }}#car-history">View details</a></td>
        </tr>@empty<tr><td colspan="6">No corrective actions match these filters.</td></tr>@endforelse
    </tbody></table><footer class="car-register-pagination"><nav class="app-pagination ct-pagination" aria-label="Record pages"><span class="app-pagination-total">{{ $cars->total() }} {{ $cars->total() === 1 ? 'record' : 'records' }} · Page {{ $cars->currentPage() }} of {{ $cars->lastPage() }}</span><x-server-page-size :paginator="$cars" />@if($cars->onFirstPage())<span class="app-pagination-button" aria-disabled="true">Previous</span>@else<a class="app-pagination-button" href="{{ $cars->previousPageUrl() }}" rel="prev">Previous</a>@endif @if($cars->hasMorePages())<a class="app-pagination-button" href="{{ $cars->nextPageUrl() }}" rel="next">Next</a>@else<span class="app-pagination-button" aria-disabled="true">Next</span>@endif</nav></footer></div>
    </section>
    @if($firstCar = $cars->first())<section class="ct-panel ct-task"><div><h2>{{ $firstCar['formal'] ? $firstCar['carNo'] : $firstCar['checkCode'] }} · {{ $firstCar['official']['Issue'] }}</h2><p class="ct-muted">{{ $firstCar['verification'] }} · Evidence is recorded against the original finding.</p></div><a class="ct-primary" data-car-history href="{{ request()->fullUrlWithQuery(['car'=>$firstCar['_id'],'export'=>null]) }}#car-history">View CAR details</a></section>@endif
    <dialog id="car-history-dialog" class="car-dialog" aria-labelledby="car-dialog-title" data-auto-open="{{ $detail ? 'true' : 'false' }}">
        <header><div><small>CORRECTIVE ACTION REPORT · FM-USTP-009-2</small><h2 id="car-dialog-title">{{ $detail ? (($detail['formal'] ? $detail['carNo'] : $detail['checkCode']).' · ') : '' }}Corrective Action Details</h2></div><button type="button" data-car-history-close aria-label="Close CAR details">×</button></header>
        <div class="car-dialog-body" data-car-history-body aria-busy="false">@if($detail) @include('admin.partials.car-history') @endif</div>
        <template data-car-details-skeleton><x-car-details-skeleton /></template>
        <span class="car-loading-status" role="status" aria-live="polite" data-car-loading-status></span>
        <footer><p>Monitoring view · Verification and closure require separate explicit permissions.</p><div class="car-detail-actions"><button type="button" data-car-view-history>View History</button><button type="button" data-car-history-close class="car-close-details">Close Details</button></div></footer>
    </dialog>
</section>
@endsection
