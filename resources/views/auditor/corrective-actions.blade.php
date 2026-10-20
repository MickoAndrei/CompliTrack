@extends('layouts.app')
@section('title', 'Corrective Actions')
@section('heading', 'Corrective Actions')
@section('content')
<x-resource-page title="Corrective Actions" description="Track open, in-progress, and closed corrective actions." endpoint="compliance-checks" :allow-status-updates="true" detail-base="auditor/checks" :columns="[['key' => 'checkCode', 'label' => 'Check Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'auditeeName', 'label' => 'Auditee'], ['key' => 'remarks', 'label' => 'Finding'], ['key' => 'correctiveStatus', 'label' => 'Status']]" />
@endsection