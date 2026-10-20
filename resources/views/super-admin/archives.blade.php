@extends('layouts.app')
@section('title', 'Archive Repository')
@section('heading', 'Archive Repository')
@section('content')
<x-resource-page title="Archive Repository" description="Search and verify encrypted compliance evidence across CET Claveria." endpoint="archives" :allow-archive-actions="true" :columns="[['key' => 'fileCode', 'label' => 'File Code'], ['key' => 'originalName', 'label' => 'Document'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'fileSize', 'label' => 'Size (bytes)'], ['key' => 'createdAt', 'label' => 'Uploaded']]" />
@endsection
