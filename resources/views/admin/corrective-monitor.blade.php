@extends('layouts.app')
@section('title', 'Corrective Action Monitoring')
@section('heading', 'Corrective Action Monitoring')
@section('subtitle', 'CET CAR deadlines, verification, and recorded history')
@section('content')
<section class="atl-workspace">
    <header class="atl-card"><h2>Corrective Actions</h2><p class="atl-muted">Read-only monitoring in your existing CET compliance scope. The assigned auditor verifies and closes CARs. Missing deadlines are not inferred.</p>
        <form method="GET" class="atl-filters"><input type="hidden" name="monitor" value="1"><label>Status<select name="status"><option value="">All statuses</option>@foreach(['open'=>'Open','in_progress'=>'In progress','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label><label class="atl-checkbox"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))>Overdue only</label><button class="atl-primary">Apply filters</button><a href="/admin/compliance-records?monitor=1">Clear</a><a href="/admin/compliance-records">All compliance records</a></form>
    </header>
    <div class="atl-card atl-table-scroll"><table class="atl-table"><thead><tr><th>CAR reference</th><th>Department / auditee</th><th>Assigned auditor</th><th>Status</th><th>Due date</th><th>Verification</th><th>History</th></tr></thead><tbody>
        @forelse($cars as $car)<tr><td>{{ $car->checkCode }}</td><td>{{ $departments->get((string) $car->departmentId)?->departmentName ?? 'Department unavailable' }}<p class="atl-muted">{{ $people->get((string) $car->auditeeId)?->fullName ?? 'Account unavailable' }}</p></td><td>{{ $people->get((string) $car->auditorId)?->fullName ?? 'Account unavailable' }}</td><td><span class="atl-status">{{ ucfirst(str_replace('_', ' ', $car->correctiveStatus)) }}</span></td><td>@if($car->dueDate){{ $car->dueDate->format('M j, Y') }}@else No due date recorded @endif @if($workspace->isOverdue($car))<strong class="atl-error">Overdue</strong>@endif</td><td>{{ $workspace->verification($car) }}</td><td><a data-car-history href="{{ request()->fullUrlWithQuery(['car' => (string) $car->getKey()]) }}#car-history">View history</a></td></tr>
        @empty<tr><td colspan="7">No corrective actions match these filters.</td></tr>@endforelse
    </tbody></table></div>
    {{ $cars->links() }}
    <dialog id="car-history-dialog" class="atl-review-dialog" aria-labelledby="car-dialog-title" data-auto-open="{{ $detail ? 'true' : 'false' }}">
        <header class="atl-modal-header"><h2 id="car-dialog-title">CAR History</h2><button type="button" data-car-history-close aria-label="Close CAR history">&times;</button></header>
        <div class="atl-review-body" data-car-history-body aria-busy="false">@if($detail) @include('admin.partials.car-history') @endif</div>
    </dialog>
</section>
@endsection
