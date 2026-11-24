@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subtitle', 'Evidence requests, audit findings, and corrective-action updates for your account.')
@section('content')
<x-notification-inbox />
@endsection
