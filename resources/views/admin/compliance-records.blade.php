@extends('layouts.app')
@section('title', 'Compliance Records')
@section('heading', 'Compliance Records')
@section('content')
<x-resource-page title="Compliance Records" description="Review checks and corrective action status across CET departments." endpoint="compliance-checks" :columns="[['key' => 'checkCode', 'label' => 'Check Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'auditeeName', 'label' => 'Auditee'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'result', 'label' => 'Result'], ['key' => 'correctiveStatus', 'label' => 'Corrective Status'], ['key' => 'createdAt', 'label' => 'Date']]" />
@endsection
