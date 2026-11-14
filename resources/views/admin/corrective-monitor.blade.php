@extends('layouts.app')
@section('title', 'Corrective Action Monitoring')
@section('heading', 'Corrective Action Monitoring')
@section('subtitle', 'Track corrective actions, target closed-out dates, verification and history')
@push('styles')<link rel="stylesheet" href="{{ asset('css/car-register.css') }}?v={{ filemtime(public_path('css/car-register.css')) }}"><link rel="stylesheet" href="{{ asset('css/car-details.css') }}?v={{ filemtime(public_path('css/car-details.css')) }}">@endpush
@section('content')
<section class="car-register">
    <div class="car-metrics">@foreach($summary as $label=>$count)<article><div class="car-metric-top"><span>{{ $label }}</span><svg class="car-metric-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">@switch($label)@case('Open')<path d="M8 3h8v4H8zM8 5H5v16h14V5h-3M8 12h8M8 16h5"/>@break @case('In Progress')<path d="M12 3a9 9 0 1 1-9 9M12 3v5M3 3l5 5M3 8V3h5M12 8v4l3 2"/>@break @case('Awaiting Verification')<path d="M9 3h6v4H9zM9 5H5v16h14V5h-4M8 14l3 3 5-6"/>@break @case('Overdue')<path d="M10.3 4.5 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0ZM12 9v4M12 17h.01"/>@break @default<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>@endswitch</svg></div><strong>{{ $count }}</strong></article>@endforeach</div>
    <section class="car-panel"><header class="car-register-header"><h3>Corrective Action Register</h3><a class="car-export" target="_blank" rel="noopener" href="{{ request()->fullUrlWithQuery(['export'=>'log','car'=>null,'history'=>null,'page'=>null]) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 15v6h14v-6"/></svg>Export CAR Log</a></header><p class="car-muted">{{ $legacyCount }} legacy checks in the search scope. Legacy checks have no recorded CAR number and are excluded from formal CAR metrics and exports.</p>
        <form method="GET" class="car-filters"><input type="hidden" name="monitor" value="1">
            <label>Search<input name="search" value="{{ request('search') }}" placeholder="CAR no., issue or responsible person"></label>
            <label>Workflow status<select name="status"><option value="">All statuses</option>@foreach(['open'=>'Open','in_progress'=>'In Progress','awaiting_verification'=>'Awaiting Verification','returned_for_revision'=>'Returned for Revision','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Department<select name="departmentId"><option value="">All departments</option>@foreach($departments as $unit)<option value="{{ $unit['id'] }}" @selected(request('departmentId')===$unit['id'])>{{ $unit['name'] }}</option>@endforeach</select></label>
            <label>Record type<select name="kind"><option value="">All records</option><option value="formal" @selected(request('kind')==='formal')>Issued CARs</option><option value="legacy" @selected(request('kind')==='legacy')>Legacy checks</option></select></label>
            <label>Sort by<select name="sort">@foreach(['target'=>'Target date','reference'=>'Reference','newest'=>'Newest first'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','target')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Rows<select name="perPage">@foreach([10,20,50] as $size)<option @selected((int)request('perPage',10)===$size)>{{ $size }}</option>@endforeach</select></label>
            <label class="car-check"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))>Overdue only</label><a class="car-reset" href="/admin/compliance-records?monitor=1">Reset Filter</a>
        </form>
    </section>
    <section class="car-panel car-table-panel"><table class="car-table"><thead><tr><th>CAR / Issue</th><th>Department / Responsible</th><th>Target Closed-Out Date</th><th>Workflow Status</th><th>Verification Results</th><th>Details</th></tr></thead><tbody>
        @forelse($cars as $car)<tr>
            <td data-label="CAR / Issue"><strong>{{ $car['formal'] ? $car['carNo'] : $car['checkCode'] }}</strong>@unless($car['formal'])<span class="car-badge legacy">Legacy check · CAR not recorded</span>@endunless<p class="car-issue">{{ $car['official']['Issue'] }}</p></td>
            <td data-label="Department / Responsible"><strong>{{ $car['departmentName'] }}</strong><p>{{ $car['official']['Assigned To'] }}</p></td>
            <td data-label="Target Closed-Out Date">{{ $car['official']['Target Closed-Out Date'] }}@if($car['overdue'])<span class="car-badge overdue">Overdue</span>@endif</td>
            <td data-label="Workflow Status"><span class="car-badge {{ $car['displayStatus'] }}">{{ $car['statusLabel'] ?: 'Not recorded' }}</span></td>
            <td data-label="Verification Results">{{ $car['verification'] }}</td>
            <td><a class="car-view" data-car-history href="{{ request()->fullUrlWithQuery(['car'=>$car['_id'],'export'=>null]) }}#car-history">View details</a></td>
        </tr>@empty<tr><td colspan="6">No corrective actions match these filters.</td></tr>@endforelse
    </tbody></table><footer class="car-pagination"><p>{{ $cars->total() }} records</p><nav aria-label="Corrective action table pagination">
        @if($cars->onFirstPage())<span class="car-page-button" aria-disabled="true">Previous</span>@else<a class="car-page-button" rel="prev" href="{{ $cars->previousPageUrl() }}">Previous</a>@endif
        <span class="car-page-position" aria-live="polite">Page {{ $cars->currentPage() }} of {{ $cars->lastPage() }}</span>
        @if($cars->hasMorePages())<a class="car-page-button" rel="next" href="{{ $cars->nextPageUrl() }}">Next</a>@else<span class="car-page-button" aria-disabled="true">Next</span>@endif
    </nav></footer></section>
    <dialog id="car-history-dialog" class="car-dialog" aria-labelledby="car-dialog-title" data-auto-open="{{ $detail ? 'true' : 'false' }}">
        <header><div><small>CORRECTIVE ACTION REPORT · FM-USTP-009-2</small><h2 id="car-dialog-title">{{ $detail ? (($detail['formal'] ? $detail['carNo'] : $detail['checkCode']).' · ') : '' }}Corrective Action Details</h2></div><button type="button" data-car-history-close aria-label="Close CAR details">×</button></header>
        <div class="car-dialog-body" data-car-history-body aria-busy="false">@if($detail) @include('admin.partials.car-history') @endif</div>
        <template data-car-details-skeleton><x-car-details-skeleton /></template>
        <span class="car-loading-status" role="status" aria-live="polite" data-car-loading-status></span>
        <footer><p>Monitoring view · Verification and closure require separate explicit permissions.</p><div class="car-detail-actions"><button type="button" data-car-view-history>View History</button><button type="button" data-car-history-close class="car-close-details">Close Details</button></div></footer>
    </dialog>
</section>
@endsection
