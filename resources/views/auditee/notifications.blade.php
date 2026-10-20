@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('content')
<x-resource-page title="Notifications" description="Updates about your findings and uploaded evidence." endpoint="notifications" :allow-notification-actions="true" :columns="[['key' => 'title', 'label' => 'Title'], ['key' => 'message', 'label' => 'Message'], ['key' => 'type', 'label' => 'Type'], ['key' => 'isRead', 'label' => 'Read'], ['key' => 'createdAt', 'label' => 'Date']]" />
@endsection