@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subtitle', 'Evidence uploads and audit updates that need your attention.')
@section('body-class', 'ct-v2-page')
@section('content')
<x-notification-inbox />
@endsection
