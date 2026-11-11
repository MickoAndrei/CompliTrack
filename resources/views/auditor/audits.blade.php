@extends('layouts.app')
@section('title', 'Audit Assignments')
@section('heading', request('view') === 'reviews' && (int) auth()->user()->roleId === 1 ? 'Audit Reviews' : 'Audit Assignments')
@section('content')
<div class="space-y-6 {{ (int) auth()->user()->roleId === 1 ? 'atl-assignment-page' : '' }}">
    @include('auditor.audit-feedback')
    @if((int) auth()->user()->roleId === 1)
    <p class="atl-muted">Coordinate assigned audits and review the auditor's submitted evaluations. Accept or return results without changing N/C/O.</p>
    <form method="GET" class="atl-filters">@if(request('view') === 'reviews')<input type="hidden" name="view" value="reviews">@endif<label>Audit status<select name="status"><option value="">All available statuses</option>@foreach((request('view') === 'reviews' ? ['submitted','returned','accepted'] : ['draft','ready','submitted','returned','accepted']) as $state)<option value="{{ $state }}" @selected(request('status') === $state)>{{ ucfirst($state) }}</option>@endforeach</select></label><button class="atl-secondary">Filter</button></form>
    @else<p>Open an assigned audit to prepare check items, review evidence, and submit results.</p>@endif
    @forelse($audits as $audit)
        @php($progress = \App\Services\AuditChecklist::progress($audit->items ?? []))
        <a class="block rounded-xl border bg-white p-5 {{ (int) auth()->user()->roleId === 1 ? 'atl-assignment-card' : '' }}" href="/audits/{{ $audit->getKey() }}" @if((int) auth()->user()->roleId === 1) data-atl-review aria-haspopup="dialog" @endif>
            <strong>{{ $audit->auditCode }} — {{ $audit->title }}</strong>
            <p>{{ $audit->departmentName }} · {{ $audit->schedule }} · {{ ucfirst($audit->status) }}</p>
            <p>{{ $progress['evaluated'] }}/{{ $progress['total'] }} evaluated · {{ $progress['percent'] }}% · {{ (int) auth()->user()->roleId === 1 ? 'Review results' : 'Open Workspace →' }}</p>
        </a>
    @empty
        <section class="auditor-assignment-empty" aria-labelledby="assignment-empty-title">
            <i data-lucide="clipboard-list" aria-hidden="true"></i>
            <h2 id="assignment-empty-title">No Assigned Audits Yet</h2>
            <p>Your assigned audits will appear here once an authorized audit assignment has been made.</p>
        </section>
    @endforelse
    {{ $audits->links() }}
    @if((int) auth()->user()->roleId === 1 && request('view') !== 'reviews')
    <form action="/audits" method="POST" class="atl-assignment-form space-y-3 rounded-xl border bg-white p-6">
        @csrf
        <h2 class="text-lg font-bold">Assign an audit</h2>
        <label class="block">Title <input name="title" required maxlength="200" class="block w-full rounded border p-2" value="{{ old('title') }}"></label>
        <label class="block">Department <select name="departmentId" required class="block w-full rounded border p-2"><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->getKey() }}">{{ $department->departmentName }}</option>@endforeach</select></label>
        <label class="block">Assigned auditor <select name="auditorId" required class="block w-full rounded border p-2"><option value="">Select auditor</option>@foreach($auditors as $auditor)<option value="{{ $auditor->getKey() }}">{{ $auditor->fullName }}</option>@endforeach</select></label>
        <p>The auditor must already be assigned to the selected department.</p>
        <label class="block">Schedule <input type="date" name="schedule" required class="block rounded border p-2" value="{{ old('schedule') }}"></label>
        <label class="block">Scope <textarea name="scope" required maxlength="3000" class="block w-full rounded border p-2">{{ old('scope') }}</textarea></label>
        <label class="block">Criteria <textarea name="criteria" required maxlength="3000" class="block w-full rounded border p-2">{{ old('criteria') }}</textarea></label>
        <button class="rounded bg-ustp-navy px-4 py-2 text-white">Assign audit</button>
    </form>
    @endif
</div>
@endsection
