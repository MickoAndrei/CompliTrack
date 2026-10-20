@extends('layouts.app')
@section('title', 'Auditee Dashboard')
@section('heading', 'My Dashboard')
@section('content')
<x-dashboard-page :role-id="3" />
@endsection