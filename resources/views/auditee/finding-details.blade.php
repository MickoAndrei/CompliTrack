@extends('layouts.app')
@section('title', 'Audit Result Details')
@section('heading', 'Audit Result Details')
@section('subtitle', 'Review your result and provide permitted corrective evidence')
@section('content')
<div class="workflow-page auditee-results">
    <a class="results-back" href="/auditee/my-findings">&larr; My Audit Results</a>
    @include('auditor.audit-feedback')
    <section class="workflow-card">@include('auditee.result-detail-content')</section>
</div>
@endsection
