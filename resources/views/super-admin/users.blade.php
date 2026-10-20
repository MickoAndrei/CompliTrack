@extends('layouts.app')
@section('title', 'User Management')
@section('heading', 'User Management')
@section('content')
<x-resource-page title="User Management" description="Manage user accounts and role assignments." endpoint="users" :allow-user-create="true" :allow-user-actions="true" :actor-role-id="auth()->user()->roleId" :columns="[['key' => 'userCode', 'label' => 'User Code'], ['key' => 'fullName', 'label' => 'Full Name'], ['key' => 'email', 'label' => 'Email'], ['key' => 'roleId', 'label' => 'Role'], ['key' => 'isActive', 'label' => 'Status']]" />
@endsection