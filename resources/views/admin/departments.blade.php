@extends('layouts.app')
@section('title', 'Departments')
@section('heading', 'Departments')
@section('content')
<x-resource-page title="Departments" description="CET departments available for compliance tracking." endpoint="departments" :allow-department-create="true" :allow-department-actions="true" :actor-role-id="auth()->user()->roleId" :columns="[['key' => 'departmentCode', 'label' => 'Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'collegeName', 'label' => 'College'], ['key' => 'campusName', 'label' => 'Campus'], ['key' => 'isActive', 'label' => 'Status']]" />
@endsection