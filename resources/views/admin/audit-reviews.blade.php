@extends('layouts.app')
@section('title', 'Audit Reviews')
@section('heading', 'Audit Reviews')
@section('subtitle', 'Review submitted evaluations and return them for revision or accept the results.')
@section('content')
<div class="space-y-6 atl-assignment-page">
    @include('auditor.audit-feedback')
    <nav class="atl-actions" aria-label="Audit review tabs">
        @foreach(['submitted' => 'Awaiting Review', 'returned' => 'Returned', 'accepted' => 'Reviewed'] as $state => $label)
        <a class="{{ $status === $state ? 'atl-primary' : 'atl-secondary' }}" href="/audits?view=reviews&amp;status={{ $state }}" @if($status === $state) aria-current="page" @endif>{{ $label }} ({{ $counts[$state] }})</a>
        @endforeach
    </nav>
    @forelse($audits as $audit)
    <a class="block rounded-xl border bg-white p-5 atl-assignment-card" href="/audits/{{ $audit->getKey() }}?review=1" data-atl-review aria-haspopup="dialog">
        <strong>{{ $audit->auditCode }} — {{ $audit->title }}</strong>
        <p>{{ $audit->departmentName }} · {{ $audit->schedule }} · {{ $status === 'accepted' ? 'Reviewed' : ucfirst($status) }}</p>
        <p>{{ $status === 'submitted' ? 'Submitted or resubmitted — awaiting ATL decision' : ($status === 'returned' ? 'Awaiting auditor revision and resubmission' : 'Accepted results and review history') }}</p>
    </a>
    @empty<p class="rounded-xl border bg-white p-6">No audits in this review tab.</p>@endforelse
    {{ $audits->links() }}
</div>
@endsection
