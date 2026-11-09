@extends('layouts.app')
@section('title', 'Departments')
@section('heading', 'Departments')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/super-admin-dashboard.css') }}?v={{ filemtime(public_path('css/super-admin-dashboard.css')) }}">
@endpush
@section('content')
<x-resource-page title="Departments" description="Departments registered in the CET compliance workspace." endpoint="departments" :allow-department-create="true" :allow-department-actions="true" :actor-role-id="auth()->user()->roleId" :columns="[['key' => 'departmentCode', 'label' => 'Code'], ['key' => 'departmentName', 'label' => 'Department'], ['key' => 'collegeName', 'label' => 'College'], ['key' => 'campusName', 'label' => 'Campus'], ['key' => 'isActive', 'label' => 'Status']]" />
<article class="sa-panel sa-organization" x-data="superAdminOrganization">
    <header><div><h3>Organizational Responsibilities</h3><p>Five CET academic departments and a separate college-level Office of the Dean.</p></div><a href="/super-admin/archives?manage=responsibilities">Manage responsibilities</a></header>
    <p role="status" x-show="loading">Loading assigned responsibilities…</p><p class="sa-error" role="alert" x-show="error" x-cloak x-text="error"></p>
    <div class="responsive-table-wrapper"><table><thead><tr><th>Academic Department</th><th>Department Head</th><th>Department Secretary</th></tr></thead><tbody><template x-for="unit in tree.units.filter(unit => unit.unitType === 'academic_department')" :key="unit._id"><tr><td x-text="unit.name"></td><td x-text="names('department_head', unit._id)"></td><td x-text="names('department_secretary', unit._id)"></td></tr></template></tbody></table></div>
    <template x-for="college in tree.colleges" :key="college._id"><section class="sa-college-office"><h3 x-text="`${college.code} · College-level responsibilities`"></h3><p>Office of the Dean is a college office and is excluded from the department count.</p><p><strong>CET Dean: </strong><span x-text="names('college_dean', college._id, true)"></span></p><p><strong>College Secretary: </strong><span x-text="names('college_secretary', college._id, true)"></span></p></section></template>
    <p>Responsibilities use explicit department or college scopes. Changing a responsibility does not change the account's authentication role.</p>
</article>
@endsection
