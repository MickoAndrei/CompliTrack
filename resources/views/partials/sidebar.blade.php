@php
    $roleId = (int) auth()->user()->roleId;
    $menus = [
        0 => [
            ['Dashboard', '/super-admin/dashboard', 'layout-dashboard'],
            ['Users', '/super-admin/users', 'users'],
            ['Roles', '/super-admin/roles', 'shield-check'],
            ['Departments', '/super-admin/departments', 'building-2'],
            ['Archive Repository', '/super-admin/archives', 'archive'],
            ['Logs', '/super-admin/logs', 'scroll-text'],
            ['Settings', '/super-admin/settings', 'settings-2'],
        ],
        1 => [
            ['Dashboard', '/admin/dashboard', 'layout-dashboard'],
            ['Audit Assignments', '/audits', 'clipboard-list'],
            ['Departments', '/admin/departments', 'building-2'],
            ['Users', '/admin/users', 'users'],
            ['Compliance Records', '/admin/compliance-records', 'clipboard-check'],
            ['Archives', '/admin/archives', 'archive'],
            ['Reports', '/admin/reports', 'chart-no-axes-combined'],
            ['Notifications', '/admin/notifications', 'bell'],
        ],
        2 => [
            ['Dashboard', '/auditor/dashboard', 'layout-dashboard'],
            ['My Assigned Audits', '/audits', 'clipboard-list'],
            ['Compliance Checks', '/auditor/checks', 'clipboard-check'],
            ['Archive Review', '/auditor/archives', 'archive'],
            ['Corrective Actions', '/auditor/corrective-actions', 'kanban'],
            ['Notifications', '/auditor/notifications', 'bell'],
        ],
        3 => [
            ['Dashboard', '/auditee/dashboard', 'layout-dashboard'],
            ['My Audit Results', '/auditee/my-findings', 'clipboard-list'],
            ['Evidence Requests', '/auditee/evidence-requests', 'clipboard-check'],
            ['Provide Evidence', '/auditee/upload', 'upload-cloud'],
            ['Archives', '/auditee/archives', 'archive'],
            ['Notifications', '/auditee/notifications', 'bell'],
            ['Help & Standards', '/auditee/help', 'circle-help'],
            ['Profile', '/profile', 'circle-user-round'],
        ],
    ];
    $roleNames = ['Super Admin', 'Admin', 'Auditor', 'Auditee'];
    $homePaths = [0 => '/super-admin/dashboard', 1 => '/admin/dashboard', 2 => '/auditor/dashboard', 3 => '/auditee/dashboard'];
    $userAvatarUrl = auth()->user()->avatarPath ? url('/api/v1/auth/avatar') : null;
    $userInitials = collect(explode(' ', auth()->user()->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $navigationGroups = $roleId === 0 ? [
        ['label' => null, 'items' => array_slice($menus[0], 0, 1)],
        ['label' => 'Organization', 'items' => array_slice($menus[0], 1, 3)],
        ['label' => 'Records & System', 'items' => array_slice($menus[0], 4)],
    ] : [['label' => null, 'items' => $menus[$roleId] ?? []]];
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
            @foreach ($navigationGroups as $group)
            @if ($group['label'])
                <details class="sidebar-section" @if (collect($group['items'])->contains(fn ($item) => request()->is(ltrim($item[1], '/'), ltrim($item[1], '/').'/*')) || request()->is('super-admin/dashboard')) open @endif>
                    <summary>{{ $group['label'] }}<i data-lucide="chevron-down" aria-hidden="true"></i></summary>
            @endif
            @foreach ($group['items'] as [$label, $href, $icon])
                @php($active = request()->is(ltrim($href, '/'), ltrim($href, '/').'/*'))
                <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                   class="nav-link-item {{ $active ? 'active' : '' }}">
                    <i data-lucide="{{ $icon }}" class="nav-icon size-5 shrink-0"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
            @if ($group['label'])</details>@endif
            @endforeach
        </nav>
    </div>
</aside>
