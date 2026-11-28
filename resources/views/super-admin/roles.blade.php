@extends('layouts.app')
@section('title', 'Roles')
@section('heading', 'Roles & Permissions')
@push('styles')<link rel="stylesheet" href="{{ asset('css/audit-plans.css') }}?v={{ filemtime(public_path('css/audit-plans.css')) }}">@endpush
@section('body-class', 'ct-v2-page')
@section('content')
<section class="app-view-panel active app-role-page">
<header class="ct-page-heading"><div><h1>Roles &amp; Permissions</h1><p>Review four roles and explicit workflow grants.</p></div></header>
    <div class="ct-role-grid">
        @foreach ([0 => ['Super Admin', 'System-wide user, department, and settings administration.', 'shield-check', 'Full system access'], 1 => ['Admin', 'CET management, users, archives, and reporting.', 'settings-2', 'CET administration'], 2 => ['Auditor', 'Assigned-department checks and corrective actions.', 'clipboard-check', 'Audit operations'], 3 => ['Auditee', 'Own compliance findings and evidence uploads.', 'file-check-2', 'Assigned records']] as $id => [$role, $description, $icon, $scope])
            <article class="ct-panel ct-role-card"><h2>{{ $id === 1 ? 'Admin (ATL)' : $role }}</h2><p class="ct-muted">System role · fixed</p><p class="sr-only">{{ $description }} · {{ $scope }}</p></article>
        @endforeach
    </div>
    <section class="ct-permission-layout workflow-permissions" x-data="auditPlanPermissions" aria-labelledby="workflow-permissions-heading">
        <div class="ct-panel"><header class="workflow-heading">
            <h2 id="workflow-permissions-heading" class="font-heading text-xl font-bold">Workflow Permissions</h2>
            <p>Choose which roles can approve audit plans, verify CARs, or close CARs.</p>
        </header>
        <p x-page-notice role="alert" x-show="error" x-text="error"></p><p x-page-notice x-show="notice" x-text="notice"></p>
        <p x-show="loading" role="status">Loading permission settings...</p>
        <form class="plan-permission-form workflow-form" @submit.prevent="save()">
            <div class="workflow-fields">
                <div>
                    <label for="workflow-permission">Permission</label>
                    <select id="workflow-permission" x-model="permission" @change="load()" :disabled="loading || saving" aria-describedby="workflow-permission-help"><option value="audit_plans.approve">Audit Plan Approval</option><option value="cars.verify">CAR Verification</option><option value="cars.close">CAR Closure</option></select>
                    <p id="workflow-permission-help" class="workflow-help" x-text="permission === 'audit_plans.approve' ? 'Allow audit plan approval. This must be enabled separately, even for Super Admin.' : permission === 'cars.verify' ? 'Allow assigned auditors to verify corrective actions within their assigned departments.' : 'Allow assigned auditors to close CARs. CAR Verification must also be enabled. Admin monitoring accounts cannot close CARs.'"></p>
                </div>
                <div>
                    <label for="workflow-role">Role</label>
                    <select id="workflow-role" x-model.number="selected" @change="choose()" :disabled="loading || saving" aria-describedby="workflow-role-help"><template x-for="role in roles" :key="role.roleId"><option :value="role.roleId" x-text="role.name"></option></template></select>
                    <p id="workflow-role-help" class="workflow-help">Changes apply to all active users in this role. Their other permissions and department access stay the same.</p>
                </div>
            </div>
            <label class="plan-check workflow-grant"><input type="checkbox" x-model="enabled" :disabled="loading || saving"><span><strong>Enable selected permission</strong><span class="workflow-help">Uncheck to revoke this permission for the selected role.</span></span></label>
            <fieldset class="workflow-confirmation">
                <legend>Confirm change</legend>
                <div class="workflow-fields">
                    <label>Reason for change<textarea x-model="remarks" required maxlength="2000" rows="2" :disabled="loading || saving" placeholder="Explain why this permission is changing"></textarea></label>
                    <label>Your current password<input type="password" x-model="password" autocomplete="current-password" required maxlength="1024" :disabled="loading || saving"><span class="workflow-help">Enter your password to authorize this change.</span></label>
                </div>
                <button type="submit" class="plan-primary" :disabled="loading || saving || !current()" x-text="saving ? 'Saving...' : 'Save Permission'">Save Permission</button>
            </fieldset>
        </form>
        </div><aside class="ct-panel"><h2>Decision rules</h2><p class="ct-muted">Audit plan approval must be explicitly enabled, even for Super Admin.</p><p class="ct-muted">Verification and closure are separate assigned-auditor permissions. Admin monitors only.</p><hr><details class="plan-history" style="margin-top:20px"><summary>Permission change history</summary><template x-for="(entry, index) in (current()?.history || []).filter(entry => entry.permission === permission)" :key="index"><p><strong x-text="entry.enabled ? 'Granted' : 'Revoked'"></strong> · <span x-text="entry.actorName"></span> · <time x-text="entry.at"></time><br><span x-text="entry.remarks"></span></p></template></details></aside>
    </section>
</section>
@endsection
