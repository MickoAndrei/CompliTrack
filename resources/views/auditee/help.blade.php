@extends('layouts.app')
@section('title', 'Help & Standards')
@section('heading', 'Help & Standards')
@section('content')
<section>
    <div class="mb-6"><h2 class="font-heading text-2xl font-bold text-ustp-navy">Reference standards</h2><p class="mt-1 text-sm text-ustp-muted">Quick reference for the compliance standards used in CompliTrack.</p></div>
    <div class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70"><span class="rounded-lg bg-ustp-navy/5 p-2 text-ustp-navy"><i data-lucide="landmark" class="inline size-5"></i></span><h3 class="mt-4 font-heading text-lg font-semibold">ARTA Citizen’s Charter</h3><ul class="mt-3 space-y-2 text-sm text-ustp-muted"><li>ARTA 3-day rule</li><li>ARTA 7-day rule</li><li>ARTA 20-day rule</li></ul></article>
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70"><span class="rounded-lg bg-ustp-navy/5 p-2 text-ustp-navy"><i data-lucide="badge-check" class="inline size-5"></i></span><h3 class="mt-4 font-heading text-lg font-semibold">ISO 9001:2015</h3><p class="mt-3 text-sm leading-6 text-ustp-muted">Clause 9.2 describes internal audit requirements for evaluating the quality management system.</p></article>
    </div>
</section>
@endsection