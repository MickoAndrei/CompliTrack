@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subtitle', 'Evidence uploads and audit updates that need your attention.')
@section('content')
<x-notification-inbox />
@endsection
