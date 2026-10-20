@extends('layouts.app')
@section('title', 'Audit Logs')
@section('heading', 'Audit Logs')
@section('content')
<x-resource-page title="Audit Logs" description="Review recorded administrative and compliance activity." endpoint="logs" :columns="[['key' => 'action', 'label' => 'Action'], ['key' => 'userName', 'label' => 'User'], ['key' => 'entityType', 'label' => 'Record Type'], ['key' => 'createdAt', 'label' => 'Date']]" />
@endsection