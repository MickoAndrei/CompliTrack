@extends('layouts.app')
@section('title', 'Roles')
@section('heading', 'Roles & Permissions')
@push('styles')<link rel="stylesheet" href="{{ asset('css/audit-plans.css') }}?v={{ filemtime(public_path('css/audit-plans.css')) }}">@endpush
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
    <section class="plan-panel" x-data="auditPlanPermissions" style="margin-top:24px">
        <h2 class="font-heading text-xl font-bold">Audit Plan Approval Permission</h2>
        <p class="plan-note">Approval requires an explicit audit_plans.approve grant. Super Admin's wildcard and the Admin / ATL or Auditor roles do not grant it automatically.</p>
        <p class="plan-note">A change applies to every active account in the selected role. Roles and all other permissions remain unchanged.</p>
        <p x-page-notice role="alert" x-show="error" x-text="error"></p><p x-page-notice x-show="notice" x-text="notice"></p>
        <p x-show="loading" role="status">Loading permission settings...</p>
        <form class="plan-permission-form" @submit.prevent="save()">
            <label>Role<select x-model.number="selected" @change="choose()" :disabled="loading || saving"><template x-for="role in roles" :key="role.roleId"><option :value="role.roleId" x-text="role.name"></option></template></select></label>
            <label class="plan-check"><input type="checkbox" x-model="enabled" :disabled="loading || saving"> Permit this role to approve and return audit plans</label>
            <label>Reason / remarks<textarea x-model="remarks" required maxlength="2000" rows="2" :disabled="saving"></textarea></label>
            <label>Your current password<input type="password" x-model="password" autocomplete="current-password" required maxlength="1024" :disabled="saving"></label>
            <button type="submit" class="plan-primary" :disabled="loading || saving">Save Permission</button>
        </form>
        <details class="plan-history" style="margin-top:20px"><summary>Permission change history</summary><template x-for="(entry, index) in current()?.history || []" :key="index"><p><strong x-text="entry.enabled ? 'Granted' : 'Revoked'"></strong> · <span x-text="entry.actorName"></span> · <time x-text="entry.at"></time><br><span x-text="entry.remarks"></span></p></template></details>
    </section>
</section>
@endsection
