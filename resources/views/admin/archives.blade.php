@extends('layouts.app')
@section('title', 'Archives')
@section('heading', 'Archive Repository')
@section('content')
<x-resource-page title="Archive Repository" description="Browse encrypted compliance evidence available to your role." endpoint="archives" :allow-archive-actions="true" :columns="[['key' => 'fileCode', 'label' => 'File Code'], ['key' => 'originalName', 'label' => 'File'], ['key' => 'referenceStandard', 'label' => 'Standard'], ['key' => 'fileSize', 'label' => 'Size (bytes)'], ['key' => 'createdAt', 'label' => 'Uploaded']]" />
@endsection