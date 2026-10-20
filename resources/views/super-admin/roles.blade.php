@extends('layouts.app')
@section('title', 'Roles')
@section('heading', 'Roles & Permissions')
@section('content')
<section class="app-view-panel active app-role-page">
    <header class="role-page-heading">
        <div><p class="role-page-eyebrow">ACCESS CONTROL</p><h2 class="font-heading text-2xl font-bold text-ustp-navy">Role overview</h2><p class="role-page-description">Review what each account role can access across CompliTrack.</p></div>
        <span class="role-fixed-note"><i data-lucide="lock-keyhole" aria-hidden="true"></i><span>Roles are managed by the system</span></span>
    </header>
    <div class="role-card-grid">
        @foreach ([0 => ['Super Admin', 'System-wide user, department, and settings administration.', 'shield-check', 'Full system access'], 1 => ['Admin', 'CET management, users, archives, and reporting.', 'settings-2', 'CET administration'], 2 => ['Auditor', 'Assigned-department checks and corrective actions.', 'clipboard-check', 'Audit operations'], 3 => ['Auditee', 'Own compliance findings and evidence uploads.', 'file-check-2', 'Assigned records']] as $id => [$role, $description, $icon, $scope])
            <article class="role-overview-card">
                <div class="role-card-topline"><span class="role-card-icon role-card-icon-{{ $id }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span><x-role-badge :role-id="$id" /></div>
                <h3>{{ $role }}</h3>
                <p>{{ $description }}</p>
                <div class="role-card-scope"><span>ACCESS SCOPE</span><strong>{{ $scope }}</strong></div>
            </article>
        @endforeach
    </div>
</section>
@endsection
