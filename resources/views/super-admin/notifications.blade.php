@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subtitle', 'Account, organization, and security updates that need your attention.')
@section('content')
<x-notification-inbox :allow-broadcast="true" />
@endsection
