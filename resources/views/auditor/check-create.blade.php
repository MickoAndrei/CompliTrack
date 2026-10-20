@extends('layouts.app')
@section('title', 'Create Compliance Check')
@section('heading', 'New Compliance Check')
@section('content')
<section x-data="checkCreatePage()" class="max-w-3xl">
    <div class="mb-6"><h2 class="font-heading text-2xl font-bold text-ustp-navy">Create compliance check</h2><p class="mt-1 text-sm text-ustp-muted">Record an assessment for an auditee in your assigned department.</p></div>
    <template x-if="error"><div class="mb-5"><x-alert-banner type="error"><span x-text="error"></span></x-alert-banner></div></template>
    <template x-if="loading"><div class="h-64 animate-pulse rounded-2xl bg-white"></div></template>
    <form x-show="!loading" @submit.prevent="submit()" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <label class="block text-sm font-medium">Department
            <select x-model="departmentId" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
                <option value="">Choose a department</option><template x-for="department in departments" :key="department._id"><option :value="department._id" x-text="department.departmentName"></option></template>
            </select>
        </label>
        <label class="block text-sm font-medium">Auditee
            <select x-model="auditeeId" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
                <option value="">Choose an auditee</option><template x-for="auditee in auditees.filter(user => user.departmentId === departmentId)" :key="auditee._id"><option :value="auditee._id" x-text="`${auditee.fullName} · ${auditee.officeName || ''}`"></option></template>
            </select>
        </label>
        <label class="block text-sm font-medium">Reference standard
            <select x-model="referenceStandard" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
                <option value="ARTA-3days">ARTA 3-day rule</option><option value="ARTA-7days">ARTA 7-day rule</option><option value="ARTA-20days">ARTA 20-day rule</option><option value="ISO9001-9.2">ISO 9001:2015 Clause 9.2</option>
            </select>
        </label>
        <fieldset><legend class="text-sm font-medium">Result</legend><div class="mt-3 flex flex-wrap gap-4"><label class="flex items-center gap-2 text-sm"><input x-model="result" type="radio" value="compliant" class="text-ustp-navy">Compliant</label><label class="flex items-center gap-2 text-sm"><input x-model="result" type="radio" value="non_compliant" class="text-ustp-navy">Non-compliant</label></div></fieldset>
        <label class="block text-sm font-medium">Remarks <span class="font-normal text-ustp-muted">(optional)</span><textarea x-model="remarks" rows="4" maxlength="5000" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></textarea></label>
        <div class="flex justify-end gap-3"><a href="/auditor/checks" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold">Cancel</a><button :disabled="saving" type="submit" class="rounded-xl bg-ustp-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy disabled:opacity-60" x-text="saving ? 'Saving…' : 'Create check'"></button></div>
    </form>
</section>
@endsection