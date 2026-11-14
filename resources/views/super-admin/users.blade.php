@extends('layouts.app')
@section('title', 'User Management')
@section('heading', 'User Management')
@section('subtitle', 'Manage accounts and keep positions separate from access roles.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/super-admin-dashboard.css') }}?v={{ filemtime(public_path('css/super-admin-dashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('css/user-management.css') }}?v={{ filemtime(public_path('css/user-management.css')) }}">
@endpush
@section('content')
<x-resource-page title="User Management" description="Manage existing CompliTrack accounts." endpoint="users" :enhanced-users="true" :allow-user-create="true" :allow-user-actions="true" :actor-role-id="auth()->user()->roleId" />
@endsection
