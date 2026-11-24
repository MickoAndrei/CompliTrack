@extends('layouts.app')
@section('title', 'Compliance Check Details')
@section('heading', 'Check Details')
@section('content')
<section x-data="checkDetailsPage({ id: @js(request()->route('id')) })" x-init="load()" class="max-w-4xl">
    <a href="/auditor/historical-records" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-ustp-navy"><i data-lucide="arrow-left" class="size-4"></i>Back to historical records</a>
    <template x-if="loading"><div class="h-52 animate-pulse rounded-2xl bg-white"></div></template>
    <template x-if="error"><x-alert-banner type="error"><span x-text="error"></span></x-alert-banner></template>
    <template x-if="check"><article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70"><h2 class="font-heading text-2xl font-bold text-ustp-navy" x-text="check.checkCode"></h2><dl class="mt-6 grid gap-5 sm:grid-cols-2"><template x-for="field in fields" :key="field.key"><div><dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted" x-text="field.label"></dt><dd class="mt-1 text-sm text-ustp-dark" x-text="check[field.key] || '—'"></dd></div></template></dl><div class="mt-6 border-t border-slate-100 pt-5"><h3 class="font-semibold">Remarks</h3><p class="mt-2 whitespace-pre-wrap text-sm text-ustp-muted" x-text="check.remarks || 'No remarks provided.'"></p></div></article></template>
<template x-if="check"><section class="workflow-card" style="margin-top:20px"><h3>Corrective evidence responses</h3><template x-for="response in check.evidenceResponses || []" :key="response.submissionKey"><article class="workflow-response"><p x-text="response.method"></p><p x-text="response.reference || ''"></p><p x-text="response.notes"></p><p x-show="response.availability" x-text="`Reported availability: ${response.availability || ''}; auditor verification is separate.`"></p></article></template><p>Use the existing authorized CAR verification workflow to record the outcome. A response does not close a CAR.</p></section></template>
    <section class="mt-5 rounded-2xl bg-white p-6 ring-1 ring-slate-200/70" aria-label="Historical evidence and status history">
        <h3 class="font-semibold">Linked evidence</h3>
        @forelse($attachments as $attachment)
        <p class="mt-2">@if($attachment['canDownload'])<a class="underline" href="/api/v1/documents/{{ $attachment['id'] }}/download">{{ $attachment['name'] }}</a>@else{{ $attachment['name'] }} <small>Download restricted</small>@endif</p>
        @empty<p class="mt-2 text-sm text-ustp-muted">No linked evidence available to your account.</p>@endforelse
        <h3 class="mt-5 font-semibold">Status history</h3>
        @forelse($check->statusHistory ?? [] as $event)
        <article class="mt-3 border-t border-slate-100 pt-3"><strong>{{ ucwords(str_replace('_', ' ', $event['status'] ?? 'Not recorded')) }}</strong><small class="block">{{ \App\Services\AtlWorkspace::date($event['timestamp'] ?? null) }}</small>@if(!empty($event['remarks']))<p class="mt-1 whitespace-pre-wrap text-sm">{{ $event['remarks'] }}</p>@endif</article>
        @empty<p class="mt-2 text-sm text-ustp-muted">No corrective status changes recorded.</p>@endforelse
        @if($check->result === 'non_compliant')<p class="mt-5"><a href="/auditor/corrective-actions" class="font-semibold underline">Open Corrective Actions</a> to verify corrective evidence and manage this CAR.</p>@endif
    </section>
</section>
@endsection
