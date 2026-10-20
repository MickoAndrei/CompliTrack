@extends('layouts.app')
@section('title', 'Finding Details')
@section('heading', 'Finding Details')
@section('content')
<section x-data="checkDetailsPage({ id: @js(request()->route('id')) })" x-init="load()" class="max-w-4xl">
    <a href="/auditee/my-findings" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-ustp-navy"><i data-lucide="arrow-left" class="size-4"></i>Back to my findings</a>
    <template x-if="loading"><div class="h-52 animate-pulse rounded-2xl bg-white"></div></template>
    <template x-if="error"><x-alert-banner type="error"><span x-text="error"></span></x-alert-banner></template>
    <template x-if="check"><article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70"><h2 class="font-heading text-2xl font-bold text-ustp-navy" x-text="check.checkCode"></h2><dl class="mt-6 grid gap-5 sm:grid-cols-2"><template x-for="field in fields" :key="field.key"><div><dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted" x-text="field.label"></dt><dd class="mt-1 text-sm text-ustp-dark" x-text="check[field.key] || '—'"></dd></div></template></dl><div class="mt-6 border-t border-slate-100 pt-5"><h3 class="font-semibold">Remarks</h3><p class="mt-2 whitespace-pre-wrap text-sm text-ustp-muted" x-text="check.remarks || 'No remarks provided.'"></p></div><a href="/auditee/upload" class="mt-6 inline-flex rounded-xl bg-ustp-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy">Upload evidence</a></article></template>
</section>
@endsection