@extends('layouts.app')
@section('title', 'Organizations')
@section('heading', 'Organizations')
@section('body-class', 'ct-v2-page')
@section('content')
<header class="ct-page-heading"><div><h1>Organizations</h1><p>View the university hierarchy and its registered departments.</p></div><a class="ct-primary" href="{{ url('/super-admin/departments') }}">Manage departments</a></header>
<form method="GET" class="ct-toolbar"><label class="sr-only" for="organization-search">Search departments</label><input class="ct-search" id="organization-search" name="search" value="{{ $search }}" placeholder="Search departments…">@if($selected)<input type="hidden" name="collegeId" value="{{ $selected->getKey() }}">@endif<button class="ct-secondary">Search</button></form>
<div class="ct-organization-layout">
<section class="ct-panel"><h2>University structure</h2><div class="ct-org-tree"><strong>USTP</strong>@forelse($campuses as $campus)<div class="ct-campus"><span>{{ $campus->campusName }}</span>@foreach($colleges->filter(fn($college) => (string)$college->campusId === (string)$campus->getKey()) as $college)<a @if($selected && (string)$selected->getKey() === (string)$college->getKey()) aria-current="page" @endif href="{{ url('/super-admin/organizations').'?'.http_build_query(['collegeId'=>(string)$college->getKey()]) }}">{{ $college->collegeName }}</a>@endforeach</div>@empty<p class="ct-muted">No campuses recorded.</p>@endforelse</div></section>
<section class="ct-panel">@if($selected)<h2>{{ $selected->collegeName }}</h2><p class="ct-muted">{{ $selected->campusName }} · College</p><hr><h3>Departments and offices</h3><ul class="ct-org-units">@forelse($units as $unit)<li><span><i data-lucide="building-2" aria-hidden="true"></i> {{ $unit->departmentName }}</span><span class="ct-muted">{{ $unit->departmentCode }}</span></li>@empty<li>No matching department records.</li>@endforelse</ul><a class="ct-primary" href="{{ url('/super-admin/departments') }}">Manage departments</a>@else<h2>No college records</h2><p class="ct-muted">The registered hierarchy will appear here.</p>@endif</section>
</div>
@endsection
