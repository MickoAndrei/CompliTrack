@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subtitle', 'Evidence requests, audit findings, and corrective-action updates for your account.')
@section('body-class', 'ct-v2-page')
@section('content')
<x-notification-inbox />
@endsection
