@extends('layouts.app')
@section('title', 'Departments')
@section('body-class', 'department-page')
@section('heading', 'Departments')
@section('subtitle', 'Departments registered in the CET compliance workspace.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/super-admin-dashboard.css') }}?v={{ filemtime(public_path('css/super-admin-dashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('css/user-management.css') }}?v={{ filemtime(public_path('css/user-management.css')) }}">
@endpush
@section('content')
<x-resource-page :per-page="5" :skeleton-loading="true" title="Departments" description="Departments registered in the CET compliance workspace." endpoint="departments" :allow-department-create="true" :allow-department-actions="true" :actor-role-id="auth()->user()->roleId" :columns="[['key' => 'departmentCode', 'label' => 'Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'collegeName', 'label' => 'College'], ['key' => 'campusName', 'label' => 'Campus'], ['key' => 'isActive', 'label' => 'Status']]" />
@endsection
