@extends('layouts.app')
@section('title', 'My Findings')
@section('heading', 'My Findings')
@section('content')
<x-resource-page title="My Findings" description="Compliance checks and corrective actions assigned to you." endpoint="compliance-checks" detail-base="auditee/finding-details" :columns="[['key' => 'checkCode', 'label' => 'Check Code'], ['key' => 'auditorName', 'label' => 'Auditor'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'result', 'label' => 'Result'], ['key' => 'correctiveStatus', 'label' => 'Status'], ['key' => 'createdAt', 'label' => 'Date']]" />
@endsection