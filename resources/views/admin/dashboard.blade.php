@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('heading', 'Dashboard')
@section('content')
<x-dashboard-page :role-id="1" />
@endsection