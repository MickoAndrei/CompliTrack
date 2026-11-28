@php
    $roleId = (int) auth()->user()->roleId;
    $menus = [
        0 => [
            ['Overview', '/super-admin/dashboard', 'layout-dashboard'],
            ['Organizations', '/super-admin/organizations', 'building-2'],
            ['Users & access', '/super-admin/users', 'users'],
            ['Roles & Permissions', '/super-admin/roles', 'shield-check'],
            ['Departments', '/super-admin/departments', 'building-2'],
            ['QMS Documents', '/super-admin/documents', 'archive'],
            ['Notifications', '/super-admin/notifications', 'bell'],
            ['Activity log', '/super-admin/logs', 'scroll-text'],
            ['System settings', '/super-admin/settings', 'settings-2'],
            ['Help & Standards', '/super-admin/help', 'circle-help'],
        ],
        1 => [
            ['Overview', '/admin/dashboard', 'layout-dashboard'],
            ['Audit Assignments', '/audits', 'clipboard-list'],
            ['Report review', '/audits?view=reviews', 'clipboard-check'],
            ['Corrective actions', '/admin/compliance-records?monitor=1', 'triangle-alert'],
            ['Departments', '/admin/departments', 'building-2'],
            ['Users', '/admin/users', 'users'],
            ['QMS Documents', '/admin/documents', 'archive'],
            ['Reports', '/admin/reports', 'chart-no-axes-combined'],
            ['Notifications', '/admin/notifications', 'bell'],
            ['Help & Standards', '/admin/help', 'circle-help'],
        ],
        2 => [
            ['Overview', '/auditor/dashboard', 'layout-dashboard'],
            ['Assigned audits', '/audits', 'clipboard-list'],
            ['Historical records', '/auditor/historical-records', 'clock'],
            ['QMS Documents', '/auditor/documents', 'archive'],
            ['Corrective Actions', '/auditor/corrective-actions', 'kanban'],
            ['Notifications', '/auditor/notifications', 'bell'],
            ['Help & Standards', '/auditor/help', 'circle-help'],
        ],
        3 => [
            ['Overview', '/auditee/dashboard', 'layout-dashboard'],
            ['My audit findings', '/auditee/my-findings', 'clipboard-list'],
            ['Evidence Requests', '/auditee/evidence-requests', 'clipboard-check'],
            ['QMS Documents', '/auditee/documents', 'archive'],
            ['Notifications', '/auditee/notifications', 'bell'],
            ['Help & Standards', '/auditee/help', 'circle-help'],
        ],
    ];
    if ($roleId === 1 || app(\App\Services\AuditPlanPermissions::class)->allows(auth()->user(), false)) {
        array_splice($menus[$roleId], 1, 0, [['Audit Plans', '/audit-plans', 'clipboard-list']]);
    }
    $roleNames = ['Super Admin', 'Admin (ATL)', 'Auditor', 'Auditee'];
    $homePaths = [0 => '/super-admin/dashboard', 1 => '/admin/dashboard', 2 => '/auditor/dashboard', 3 => '/auditee/dashboard'];
    $userAvatarUrl = auth()->user()->avatarUrl();
    $userInitials = collect(explode(' ', auth()->user()->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<aside x-cloak :class="{ 'open': sidebarOpen }"
       class="sidebar app-sidebar fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-ustp-navy text-white transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0">
    <div class="sidebar-primary">
        <a href="{{ $homePaths[$roleId] ?? '/login' }}" class="sidebar-header">
            <span class="brand-emblem-svg">
                <img src="{{ asset('images/ustp-claveria-logo.jpg') }}" alt="USTP Claveria" class="sidebar-brand-logo" width="40" height="40">
            </span>
            <span class="super-admin-brand-copy">
                <span class="brand-title">Compli<span class="track-highlight">Track</span></span>
                <span class="brand-subtitle">CLAVERIA CAMPUS</span>
            </span>
        </a>
        <div class="v2-sidebar-tools">
            <button type="button" class="v2-collapse-toggle" @click="toggleSidebar()" :aria-expanded="(!sidebarCollapsed).toString()" :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'" :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <i data-lucide="columns" aria-hidden="true"></i><span>Collapse sidebar</span>
            </button>
            <div class="v2-scope" aria-label="Current organization scope">
                <span class="v2-scope-label">Workspace</span>
                <div class="v2-scope-value"><i data-lucide="building-2" aria-hidden="true"></i><span>{{ $roleId === 0 ? 'USTP · University' : 'Claveria · CET' }}</span></div>
            </div>
        </div>
        <span class="ct-nav-label">Navigation</span><nav class="sidebar-nav" aria-label="Main navigation">
            @if($roleId === 1)
                    @foreach($menus[$roleId] as [$label, $href, $icon])
                    @php($path = ltrim(parse_url($href, PHP_URL_PATH), '/'))
                    @php($active = request()->is($path, $path.'/*') && (parse_url($href, PHP_URL_QUERY) ?? '') === http_build_query(request()->only(['view', 'monitor'])))
                    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" @if($active) aria-current="page" @endif class="nav-link-item {{ $active ? 'active' : '' }}"><i data-lucide="{{ $icon }}" class="nav-icon size-5 shrink-0" aria-hidden="true"></i><span>{{ $label }}</span></a>
                    @endforeach
            @else
            @foreach ($menus[$roleId] ?? [] as [$label, $href, $icon])
                @php($active = request()->is(ltrim($href, '/'), ltrim($href, '/').'/*') || ($roleId === 2 && $href === '/audits' && request()->is('auditor/checks', 'auditor/checks/*')))
                <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" @if ($active) aria-current="page" @endif
                   class="nav-link-item {{ $active ? 'active' : '' }}">
                    <i data-lucide="{{ $icon }}" class="nav-icon size-5 shrink-0"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
            @endif
        </nav>
        <a href="{{ $profilePath }}" class="v2-sidebar-account" title="Account profile" aria-label="Account profile">
            @if($userAvatarUrl)<img src="{{ $userAvatarUrl }}" alt="" width="32" height="32">@else<span class="v2-avatar">{{ $userInitials }}</span>@endif
            <span class="v2-account-copy"><strong>{{ auth()->user()->fullName }}</strong><small>{{ $roleNames[$roleId] ?? 'User' }}</small></span>
            <i data-lucide="chevron-right" aria-hidden="true"></i>
        </a>
    </div>
</aside>
