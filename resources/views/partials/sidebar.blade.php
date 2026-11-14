@php
    $roleId = (int) auth()->user()->roleId;
    $menus = [
        0 => [
            ['Dashboard', '/super-admin/dashboard', 'layout-dashboard'],
            ['Users', '/super-admin/users', 'users'],
            ['Roles', '/super-admin/roles', 'shield-check'],
            ['Departments', '/super-admin/departments', 'building-2'],
            ['Documents', '/super-admin/documents', 'archive'],
            ['Notifications', '/super-admin/notifications', 'bell'],
            ['System Activity Logs', '/super-admin/logs', 'scroll-text'],
            ['Settings', '/super-admin/settings', 'settings-2'],
            ['Help & Standards', '/super-admin/help', 'circle-help'],
        ],
        1 => [
            ['Dashboard', '/admin/dashboard', 'layout-dashboard'],
            ['Audit Assignments', '/audits', 'clipboard-list'],
            ['Audit Reviews', '/audits?view=reviews', 'clipboard-check'],
            ['Corrective Monitoring', '/admin/compliance-records?monitor=1', 'triangle-alert'],
            ['Departments', '/admin/departments', 'building-2'],
            ['Users', '/admin/users', 'users'],
            ['Documents', '/admin/documents', 'archive'],
            ['Reports', '/admin/reports', 'chart-no-axes-combined'],
            ['Notifications', '/admin/notifications', 'bell'],
            ['Help & Standards', '/admin/help', 'circle-help'],
        ],
        2 => [
            ['Dashboard', '/auditor/dashboard', 'layout-dashboard'],
            ['My Assigned Audits', '/audits', 'clipboard-list'],
            ['Documents', '/auditor/documents', 'archive'],
            ['Corrective Actions', '/auditor/corrective-actions', 'kanban'],
            ['Notifications', '/auditor/notifications', 'bell'],
            ['Help & Standards', '/auditor/help', 'circle-help'],
        ],
        3 => [
            ['Dashboard', '/auditee/dashboard', 'layout-dashboard'],
            ['My Audit Results', '/auditee/my-findings', 'clipboard-list'],
            ['Evidence Requests', '/auditee/evidence-requests', 'clipboard-check'],
            ['Provide Evidence', '/auditee/upload', 'upload-cloud'],
            ['Documents', '/auditee/documents', 'archive'],
            ['Notifications', '/auditee/notifications', 'bell'],
            ['Help & Standards', '/auditee/help', 'circle-help'],
            ['Profile', $profilePath, 'circle-user-round'],
        ],
    ];
    if ($roleId === 1 || app(\App\Services\AuditPlanPermissions::class)->allows(auth()->user(), false)) {
        array_splice($menus[$roleId], 1, 0, [['Audit Plans', '/audit-plans', 'clipboard-list']]);
    }
    $roleNames = ['Super Admin', 'Admin', 'Auditor', 'Auditee'];
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
        <nav class="{{ 'sidebar-nav' }}" aria-label="Main navigation">
            @if($roleId === 1)
                    @foreach($menus[$roleId] as [$label, $href, $icon])
                    @php($path = ltrim(parse_url($href, PHP_URL_PATH), '/'))
                    @php($active = request()->is($path, $path.'/*') && (parse_url($href, PHP_URL_QUERY) ?? '') === http_build_query(request()->only(['view', 'monitor'])))
                    <a href="{{ $href }}" @if($active) aria-current="page" @endif class="nav-link-item {{ $active ? 'active' : '' }}"><i data-lucide="{{ $icon }}" class="nav-icon size-5 shrink-0" aria-hidden="true"></i><span>{{ $label }}</span></a>
                    @endforeach
            @else
            @foreach ($menus[$roleId] ?? [] as [$label, $href, $icon])
                @php($active = request()->is(ltrim($href, '/'), ltrim($href, '/').'/*') || ($roleId === 2 && $href === '/audits' && request()->is('auditor/historical-records', 'auditor/checks', 'auditor/checks/*')))
                <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                   class="nav-link-item {{ $active ? 'active' : '' }}">
                    <i data-lucide="{{ $icon }}" class="nav-icon size-5 shrink-0"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
            @endif
        </nav>
    </div>
</aside>
