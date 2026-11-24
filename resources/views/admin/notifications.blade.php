@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subtitle', 'Audit assignments, reviews, and corrective-action updates that need your attention.')
@section('content')
<x-notification-inbox :allow-broadcast="true" />
@endsection
