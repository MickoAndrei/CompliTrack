@extends('layouts.app')
@section('title', 'My Archives')
@section('heading', 'My Archives')
@section('content')
<x-resource-page title="My Archives" description="Evidence files uploaded by you and available to your account." endpoint="archives" :allow-archive-actions="true" :columns="[['key' => 'fileCode', 'label' => 'File Code'], ['key' => 'originalName', 'label' => 'File'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'fileSize', 'label' => 'Size (bytes)'], ['key' => 'createdAt', 'label' => 'Uploaded']]" />
@endsection