@extends('layouts.app')
@section('title', 'My Archives')
@section('heading', 'My Archives')
@section('content')
<div class="workflow-page">
    @include('auditor.audit-feedback')
    <p class="workflow-muted">Your authorized evidence files are read-only here. They are not automatically completed audits. <a href="/auditee/my-findings?archived=1">View closed audit results</a>. No retention rule is imposed.</p>
    <form method="GET" class="workflow-filters"><label>Search file name<input name="search" value="{{ request('search') }}"></label><label>Upload year (optional)<input name="year" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="{{ request('year') }}" placeholder="YYYY"></label><button class="auditor-gold-button">Filter</button></form>
    @forelse($archives as $archive)<article class="workflow-card"><h2>{{ $archive->originalName }}</h2><p>{{ $archive->fileCode }} · {{ $archive->referenceStandard }} · {{ number_format($archive->fileSize ?? 0) }} bytes · {{ $archive->createdAt?->format('M d, Y') }}</p><p>Encrypted file · Read-only</p>@if($archive->auditId)<p>Related audit reference: {{ \App\Models\Audit::find($archive->auditId)?->auditCode ?? 'Unavailable' }}</p>@endif<a href="/api/v1/archives/{{ $archive->getKey() }}/download">Download authorized file</a></article>@empty<section class="auditor-assignment-empty"><i data-lucide="archive"></i><h2>No Archived Files Found</h2><p>Authorized files you provide will appear here. Try clearing filters.</p></section>@endforelse
    {{ $archives->links() }}
</div>
@endsection
