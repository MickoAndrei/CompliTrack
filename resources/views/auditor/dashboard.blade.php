@extends('layouts.app')
@section('title', 'Auditor Dashboard')
@section('heading', 'Auditor Dashboard')
@section('content')
<x-dashboard-page :role-id="2" />
@endsection