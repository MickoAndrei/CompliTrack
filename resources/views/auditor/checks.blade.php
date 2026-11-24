@extends('layouts.app')
@section('title', 'Historical Compliance Records')
@section('heading', 'Historical Compliance Records')
@section('content')
<p class="mb-4">Existing standalone compliance records assigned to you are retained here. For new evaluations, open <a href="/audits" class="font-semibold text-ustp-navy underline">My Assigned Audits → Audit Workspace → Audit Checklist</a>. Manage existing CARs in <a href="/auditor/corrective-actions" class="font-semibold text-ustp-navy underline">Corrective Actions</a>.</p>
<x-resource-page title="Historical Compliance Records" description="Read existing standalone records; new evaluations belong in assigned audit checklists." endpoint="compliance-checks" detail-base="auditor/checks" :columns="[['key' => 'checkCode', 'label' => 'Check Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'auditeeName', 'label' => 'Auditee'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'result', 'label' => 'Result'], ['key' => 'correctiveStatus', 'label' => 'Corrective Status']]" />
@endsection
