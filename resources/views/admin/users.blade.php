@extends('layouts.app')
@section('title', 'Users')
@section('heading', 'User Management')
@section('subtitle', 'View auditors and auditees assigned to CET departments.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/user-management.css') }}?v={{ filemtime(public_path('css/user-management.css')) }}">
@endpush
@section('body-class', 'ct-v2-page')
@section('content')
<header class="ct-page-heading"><div><h1>Users</h1><p>View auditors and auditees assigned to CET departments.</p></div></header>

<x-resource-page title="User Management" description="View auditors and auditees assigned to CET departments." endpoint="users" :show-heading="false" :skeleton-loading="true" :per-page="10" :actor-role-id="auth()->user()->roleId" :columns="[['key' => 'fullName', 'label' => 'Full Name'], ['key' => 'userCode', 'label' => 'User Code'], ['key' => 'email', 'label' => 'Email'], ['key' => 'roleId', 'label' => 'Role'], ['key' => 'isActive', 'label' => 'Status']]" />
@endsection
