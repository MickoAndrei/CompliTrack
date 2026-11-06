@extends('layouts.app')
@section('title', 'Audit Assignments')
@section('heading', 'Audit Assignments')
@section('content')
<div class="space-y-6">
    @include('auditor.audit-feedback')
    <p>Open an assigned audit to prepare check items, review evidence, and submit results.</p>
    @forelse($audits as $audit)
        @php($progress = \App\Services\AuditChecklist::progress($audit->items ?? []))
        <a class="block rounded-xl border bg-white p-5" href="/audits/{{ $audit->getKey() }}">
            <strong>{{ $audit->auditCode }} — {{ $audit->title }}</strong>
            <p>{{ $audit->departmentName }} · {{ $audit->schedule }} · {{ ucfirst($audit->status) }}</p>
            <p>{{ $progress['evaluated'] }}/{{ $progress['total'] }} evaluated · {{ $progress['percent'] }}% · Open Workspace →</p>
        </a>
    @empty
        <section class="auditor-assignment-empty" aria-labelledby="assignment-empty-title">
            <i data-lucide="clipboard-list" aria-hidden="true"></i>
            <h2 id="assignment-empty-title">No Assigned Audits Yet</h2>
            <p>Your assigned audits will appear here once an authorized audit assignment has been made.</p>
        </section>
    @endforelse
    {{ $audits->links() }}
    @if((int) auth()->user()->roleId === 1)
    <form action="/audits" method="POST" class="space-y-3 rounded-xl border bg-white p-6">
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
