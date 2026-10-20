@extends('layouts.app')
@section('title', 'Users')
@section('heading', 'User Management')
@section('content')
<x-resource-page title="User Management" description="Manage auditors and auditees assigned to CET departments." endpoint="users" :allow-user-create="true" :allow-user-actions="true" :actor-role-id="auth()->user()->roleId" :columns="[['key' => 'userCode', 'label' => 'User Code'], ['key' => 'fullName', 'label' => 'Full Name'], ['key' => 'email', 'label' => 'Email'], ['key' => 'roleId', 'label' => 'Role'], ['key' => 'isActive', 'label' => 'Status']]" />
@endsection