@extends('layouts.app')
@section('title', 'Archive Review')
@section('heading', 'Archive Review')
@section('content')
<x-resource-page title="Archive Review" description="Encrypted evidence for departments assigned to you." endpoint="archives" :allow-archive-actions="true" :columns="[['key' => 'fileCode', 'label' => 'File Code'], ['key' => 'originalName', 'label' => 'File'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'fileSize', 'label' => 'Size (bytes)'], ['key' => 'createdAt', 'label' => 'Uploaded']]" />
@endsection