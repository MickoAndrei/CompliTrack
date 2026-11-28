@extends('layouts.app')
@section('title', 'Overview')
@section('heading', 'Overview')
@section('body-class', 'ct-v2-overview-page')
@section('content')
@php
    $open = $checks->where('result', 'non_compliant')->whereIn('correctiveStatus', ['open','in_progress','returned_for_revision'])->count();
    $review = $checks->where('result', 'non_compliant')->where('correctiveStatus', 'awaiting_verification')->count();
    $closed = $checks->where('result', 'non_compliant')->where('correctiveStatus', 'closed')->count();
    $evidence = $requests->whereIn('status', ['requested','needs_information'])->count();
    $title = 'My workspace'; $subtitle = 'A summary of findings and corrective actions assigned to you.';
    $period = 'All authorized records'; $plans = collect(); $selected = '';
    $metrics = [['My findings', $checks->where('result','non_compliant')->count(), 'Assigned check findings'], ['Open actions', $open, 'Needs response'], ['For verification', $review, 'Awaiting decision'], ['Closed', $closed, 'Recorded closure']];
    $bars = [['Open', $open], ['For verification', $review], ['Closed', $closed]];
    $distribution = [['Closed', $closed, 'navy'], ['For verification', $review, 'gold'], ['Open', $open, 'red']];
    $chartTitle = 'Audit activity';
    $attention = [['Action', $open.' corrective actions need response', '/auditee/my-findings?actionable=1'], ['Review', $evidence.' evidence requests', '/auditee/evidence-requests']];
@endphp
@include('components.v2.overview', ['detailsUrl' => '/auditee/dashboard?details=1'])
@endsection
