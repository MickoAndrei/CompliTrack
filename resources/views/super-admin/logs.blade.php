@extends('layouts.app')
@section('title', 'System Activity Logs')
@section('heading', 'System Activity Logs')
@section('subtitle', 'Trace account, audit, document, and security activities.')
@section('body-class', 'ct-v2-page')
@section('content')
<header class="ct-page-heading"><div><h1>Activity log</h1><p>Track authorized system and audit activity.</p></div></header>

<x-activity-logs />
@endsection
