@extends('layouts.app')
@section('title', 'Upload Evidence')
@section('heading', 'Upload Evidence')
@section('content')
<section x-data="evidenceUploadPage()" class="max-w-3xl">
    <div class="mb-6"><h2 class="font-heading text-2xl font-bold text-ustp-navy">Upload corrective evidence</h2><p class="mt-1 text-sm text-ustp-muted">Select an open non-compliant finding and attach supporting evidence.</p></div>
    <div x-cloak x-show="error || message" x-transition class="fixed right-6 top-6 z-[100] flex min-h-[52px] w-[calc(100%-3rem)] max-w-sm items-center rounded-lg border-l-4 bg-ustp-navy py-4 pr-5 text-[13px] font-medium leading-5 text-white shadow-[0_10px_25px_rgba(5,27,68,0.18)]" :class="error ? 'border-red-500' : 'border-ustp-gold'" style="padding-left: 24px" :role="error ? 'alert' : 'status'" aria-live="polite">
        <span class="min-w-0 flex-1" x-text="error || message"></span>
    </div>
    <template x-if="loading"><div class="h-60 animate-pulse rounded-2xl bg-white"></div></template>
    <form x-show="!loading" @submit.prevent="submit()" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <label class="block text-sm font-medium">Finding
            <select x-model="selectedCheckId" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
                <option value="">Select a non-compliant check</option><template x-for="check in checks" :key="check._id"><option :value="check._id" x-text="`${check.checkCode} · ${check.referenceStandard}`"></option></template>
            </select>
        </label>
        <label class="block text-sm font-medium">Evidence file
            <input x-ref="fileInput" type="file" accept=".pdf,.docx,.png,.jpg,.jpeg" @change="file = $event.target.files[0] || null" required class="mt-2 block w-full rounded-xl border border-slate-200 p-3 text-sm">
            <span class="mt-2 block text-xs text-ustp-muted">PDF, DOCX, PNG, or JPG · maximum 20 MB</span>
        </label>
        <div class="flex items-start gap-3 rounded-xl bg-ustp-light p-4 text-sm text-ustp-muted"><i data-lucide="lock-keyhole" class="mt-0.5 size-5 shrink-0 text-ustp-navy"></i><span>File will be encrypted with AES-256-GCM when securely stored.</span></div>
        <button :disabled="saving" type="submit" class="rounded-xl bg-ustp-navy px-5 py-3 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy disabled:opacity-60" x-text="saving ? 'Uploading…' : 'Upload evidence'"></button>
    </form>
</section>
@endsection
