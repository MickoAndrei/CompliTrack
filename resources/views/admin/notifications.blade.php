@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subtitle', 'Audit assignments, reviews, and corrective-action updates that need your attention.')
@section('body-class', 'ct-v2-page')
@section('content')
<x-notification-inbox :allow-broadcast="true" />
@endsection
