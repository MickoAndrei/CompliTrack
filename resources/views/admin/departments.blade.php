@extends('layouts.app')
@section('title', 'Departments')
@section('heading', 'Departments')
@section('subtitle', 'CET departments available for compliance tracking.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/user-management.css') }}?v={{ filemtime(public_path('css/user-management.css')) }}">
@endpush
@section('body-class', 'ct-v2-page')
@section('content')
<header class="ct-page-heading"><div><h1>Departments</h1><p>Departments in your authorized CET workspace.</p></div></header>

<x-resource-page title="Departments" description="CET departments available for compliance tracking." endpoint="departments" :skeleton-loading="true" :per-page="5" :actor-role-id="auth()->user()->roleId" :columns="[['key' => 'departmentCode', 'label' => 'Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'collegeName', 'label' => 'College'], ['key' => 'campusName', 'label' => 'Campus'], ['key' => 'isActive', 'label' => 'Status']]" />
@endsection
