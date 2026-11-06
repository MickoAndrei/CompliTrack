@extends('layouts.app')
@section('title', 'Audit Workspace')
@section('heading', 'Audit Workspace')
@section('content')
@php($editable = (int) auth()->user()->roleId === 2 && in_array($audit->status, ['draft','ready','returned']))
<div class="space-y-6">
    <a href="/audits">← Audit assignments</a>
    @include('auditor.audit-feedback')
    <header class="space-y-2 rounded-xl border bg-white p-6">
        <h2 class="text-xl font-bold">{{ $audit->auditCode }} — {{ $audit->title }}</h2>
        <p>{{ $audit->departmentName }} · {{ $audit->schedule }} · {{ ucfirst($audit->status) }}</p>
        <p>Auditor: {{ \App\Models\User::find($audit->auditorId)?->fullName }} · Reviewer: {{ \App\Models\User::find($audit->reviewerId)?->fullName }}</p>
        <p><strong>Scope:</strong> {{ $audit->scope }}</p><p><strong>Criteria:</strong> {{ $audit->criteria }}</p>
        <progress value="{{ $progress['evaluated'] }}" max="{{ max(1,$progress['total']) }}" aria-label="Checklist progress" class="w-full"></progress>
        <p>{{ $progress['percent'] }}% · {{ $progress['C'] }} compliant · {{ $progress['N'] }} nonconformance · {{ $progress['O'] }} observation · {{ $progress['pending'] }} pending</p>
    </header>
    @foreach($audit->items ?? [] as $index => $item)
    <section class="rounded-xl border bg-white p-6">
        <h3 class="text-lg font-bold">{{ $index + 1 }}. {{ $item['question'] }}</h3>
        <p>{{ $item['reference'] ?? '' }}</p>
        @if($editable && $audit->status === 'draft')
        <form method="POST" class="my-3 space-y-2">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="item" value="{{ $index }}"><input type="hidden" name="action" value="edit"><label class="block">Edit Check Item<textarea name="question" required maxlength="2000" class="block w-full rounded border p-2">{{ $item['question'] }}</textarea></label><label class="block">Criterion reference<input name="reference" maxlength="500" class="block w-full rounded border p-2" value="{{ $item['reference'] ?? '' }}"></label><button class="rounded border p-2">Save question</button></form>
        <form method="POST" class="my-3 flex gap-3">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="item" value="{{ $index }}"><button name="action" value="move" class="rounded border p-2">Move up</button><button name="action" value="remove" onclick="return confirm('Remove this draft question?')" class="rounded border p-2">Remove</button></form>
        @endif
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <div>
                <h4 class="font-bold">Audit Compliance and Remarks</h4>
                @if($editable && $audit->status !== 'draft')
                <form method="POST" class="space-y-3">@csrf
                    <input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" value="evaluate"><input type="hidden" name="item" value="{{ $index }}">
                    <label class="block">Audit Compliance <select name="result" required class="block w-full rounded border p-2"><option value="">Pending</option>@foreach(['N'=>'Non-Conformance','C'=>'Compliant','O'=>'Observation'] as $value=>$label)<option value="{{ $value }}" @selected(($item['result'] ?? '') === $value)>{{ $value }} — {{ $label }}</option>@endforeach</select></label>
                    <label class="block">Audit Remarks <textarea name="remarks" required maxlength="5000" class="block w-full rounded border p-2">{{ $item['remarks'] ?? '' }}</textarea></label>
                    <label class="block">Finding description (required for N; optional for O) <textarea name="finding" maxlength="5000" class="block w-full rounded border p-2">{{ $item['finding'] ?? '' }}</textarea></label>
                    <p>Findings remain linked to this check item and its evidence. They do not automatically create a CAR.</p>
                    <button class="rounded bg-ustp-navy px-4 py-2 text-white">Save evaluation</button>
                </form>
                @else
                <p>{{ $item['result'] ?? 'Pending' }} · {{ $item['remarks'] ?? '' }}</p><p>Finding: {{ $item['finding'] ?? 'None' }}</p>
                @endif
            </div>
            <div class="space-y-3">
                <h4 class="font-bold">Evidence Reviewed</h4>
                @forelse($item['evidence'] ?? [] as $evidence)
                <article class="rounded border p-3"><p>{{ $evidence['method'] }} · {{ $evidence['reference'] ?? '' }}</p><p>{{ $evidence['notes'] }}</p>@if(isset($evidence['signature']))<p>Signature: {{ $evidence['signature'] }}</p>@endif<p>{{ $evidence['reviewedAt'] }}</p></article>
                @empty<p>No evidence reviews recorded.</p>@endforelse
                @if($editable)
                <form method="POST" class="space-y-3" x-data="{ method: 'digital' }">@csrf
                    <input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" value="evidence"><input type="hidden" name="item" value="{{ $index }}">
                    <label class="block">Evidence method <select name="method" x-model="method" class="block w-full rounded border p-2"><option value="digital">Digital</option><option value="physical-original">Physical original</option><option value="restricted">Restricted / controlled</option><option value="interview-process">Interview / observed process</option></select></label>
                    <label class="block">Permitted reference (optional) <input name="reference" maxlength="500" class="block w-full rounded border p-2"></label>
                    <label class="block">What was verified <textarea name="notes" required maxlength="5000" class="block w-full rounded border p-2"></textarea></label>
                    <label class="block" x-show="method === 'physical-original'">Handwritten signature <select name="signature" class="block w-full rounded border p-2"><option value="not-required">Not required</option><option value="verified">Verified</option><option value="missing-incomplete">Missing / incomplete</option><option value="not-verified">Not yet verified</option></select></label>
                    <p>Record only permitted facts. Omit restricted titles and identifiers. Protected QMS documents must not be uploaded without authorization. No copy or upload is required.</p>
                    <button class="rounded border px-4 py-2">Record evidence review</button>
                </form>
                @endif
            </div>
        </div>
    </section>
    @endforeach
    @if($editable && $audit->status === 'draft')
    <form method="POST" class="space-y-3 rounded-xl border bg-white p-6">@csrf
        <input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" value="add">
        <h3 class="font-bold">Checklist preparation</h3>
        <label class="block">Check Item <textarea name="question" required maxlength="2000" class="block w-full rounded border p-2"></textarea></label>
        <label class="block">Permitted criterion reference <input name="reference" maxlength="500" class="block w-full rounded border p-2"></label>
        <button class="rounded border px-4 py-2">Add draft question</button>
    </form>
    <form method="POST" onsubmit="return confirm('Freeze these questions for evaluation?')">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><button name="action" value="ready" class="rounded bg-ustp-navy px-4 py-2 text-white">Mark checklist ready</button></form>
    @endif
    @include('auditor.evidence-requests')
    <section class="space-y-3 rounded-xl border bg-white p-6">
        <h3 class="font-bold">Auditee assessment / interview notes</h3>
        <p>Record process-related responses and factual observations. This is supporting audit documentation.</p>
        @forelse($audit->interviews ?? [] as $interview)
        <article class="rounded border p-3"><strong>{{ $interview['auditeeName'] }} · {{ $interview['process'] }}</strong><p>{{ $interview['topics'] }}</p><p>{{ $interview['responses'] }}</p><p>{{ $interview['notes'] ?? '' }}</p><p>{{ $interview['at'] }}</p></article>
        @empty<p>No interviews recorded. Interviews are optional.</p>@endforelse
        @if($editable)
        <form method="POST" class="space-y-3">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" value="interview">
            <label class="block">Auditee <select name="auditeeId" required class="block w-full rounded border p-2"><option value="">Select departmental auditee</option>@foreach($auditees as $person)<option value="{{ $person->getKey() }}">{{ $person->fullName }}</option>@endforeach</select></label>
            @foreach(['process'=>'Process / area','topics'=>'Questions / topics','responses'=>'Auditee responses','notes'=>'Factual notes (optional)'] as $field=>$label)
            <label class="block">{{ $label }}<textarea name="{{ $field }}" @required($field !== 'notes') maxlength="{{ $field === 'process' ? 500 : ($field === 'topics' ? 3000 : 5000) }}" class="block w-full rounded border p-2"></textarea></label>
            @endforeach
            <button class="rounded border px-4 py-2">Save interview</button>
        </form>
        @endif
    </section>

    <section class="space-y-3 rounded-xl border bg-white p-6">
        <h3 class="font-bold">Review and Submission</h3>
        <p>All items require N/C/O, remarks, and an evidence review. N items require a finding description. Submission locks editing until the reviewer returns the audit.</p>
        @if($editable && $audit->status !== 'draft')
        <form method="POST" onsubmit="return confirm('Submit these results to the designated reviewer?')">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><button name="action" value="submit" class="rounded bg-ustp-navy px-4 py-2 text-white">Submit audit results</button></form>
        @elseif((int) auth()->user()->roleId === 1 && $audit->status === 'submitted')
        <form method="POST" class="space-y-3" onsubmit="return confirm('Record this review decision?')">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><label class="block">Review notes<textarea name="reviewNotes" required maxlength="5000" class="block w-full rounded border p-2"></textarea></label><button name="action" value="accept" class="rounded border px-4 py-2">Accept</button> <button name="action" value="return" class="rounded border px-4 py-2">Return for revision</button></form>
        @endif
        <h4 class="font-bold">Activity and submission history</h4>
        @foreach($audit->history ?? [] as $event)<details class="rounded border p-2"><summary>{{ $event['at'] }} · {{ $event['action'] }}</summary><p>Actor: {{ $event['actor'] }} · {{ $event['notes'] ?? '' }}</p><pre class="overflow-auto whitespace-pre-wrap">{{ json_encode($event['previousItems'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endforeach
    </section>
</div>
@endsection
