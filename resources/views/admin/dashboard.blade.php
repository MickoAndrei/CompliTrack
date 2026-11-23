@extends('layouts.app')
@section('title', 'Audit Team Leader Dashboard')
@section('body-class', 'atl-dashboard-page')
@section('heading', 'Audit Team Leader Dashboard')
@section('subtitle', 'Your assigned audits and authorized CET corrective actions.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/user-management.css') }}?v={{ filemtime(public_path('css/user-management.css')) }}">
@endpush
@section('content')
@include('admin.atl-dashboard')
@endsection
