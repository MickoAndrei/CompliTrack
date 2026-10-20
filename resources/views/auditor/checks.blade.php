@extends('layouts.app')
@section('title', 'Compliance Checks')
@section('heading', 'Compliance Checks')
@section('content')
<div class="mb-5 flex justify-end"><a href="/auditor/check-create" class="inline-flex items-center gap-2 rounded-xl bg-ustp-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy"><i data-lucide="plus" class="size-4"></i>New Compliance Check</a></div>
<x-resource-page title="Compliance Checks" description="Checks assigned to your auditor account." endpoint="compliance-checks" detail-base="auditor/checks" :columns="[['key' => 'checkCode', 'label' => 'Check Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'auditeeName', 'label' => 'Auditee'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'result', 'label' => 'Result'], ['key' => 'correctiveStatus', 'label' => 'Corrective Status']]" />
@endsection