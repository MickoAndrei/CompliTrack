@extends('layouts.app')
@section('title', 'Overview')
@section('heading', 'Overview')
@section('body-class', 'ct-v2-overview-page')
@section('content')
@include('components.v2.overview', ['detailsUrl' => url()->current().'?details=1'])
@endsection
