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
            ['Departments', '/admin/departments', 'building-2'],
            ['Users', '/admin/users', 'users'],
            ['Compliance Records', '/admin/compliance-records', 'clipboard-check'],
            ['Archives', '/admin/archives', 'archive'],
            ['Reports', '/admin/reports', 'chart-no-axes-combined'],
            ['Notifications', '/admin/notifications', 'bell'],
        ],
        2 => [
            ['Dashboard', '/auditor/dashboard', 'layout-dashboard'],
            ['Checks', '/auditor/checks', 'clipboard-check'],
            ['Archives', '/auditor/archives', 'archive'],
            ['Corrective Actions', '/auditor/corrective-actions', 'kanban'],
            ['Notifications', '/auditor/notifications', 'bell'],
        ],
        3 => [
            ['Dashboard', '/auditee/dashboard', 'layout-dashboard'],
            ['My Findings', '/auditee/my-findings', 'clipboard-list'],
            ['Upload Evidence', '/auditee/upload', 'upload-cloud'],
            ['Archives', '/auditee/archives', 'archive'],
            ['Notifications', '/auditee/notifications', 'bell'],
            ['Help', '/auditee/help', 'circle-help'],
        ],
    ];
    $roleNames = ['Super Admin', 'Admin', 'Auditor', 'Auditee'];
    $homePaths = [0 => '/super-admin/dashboard', 1 => '/admin/dashboard', 2 => '/auditor/dashboard', 3 => '/auditee/dashboard'];
    $userAvatarUrl = auth()->user()->avatarPath ? url('/api/v1/auth/avatar') : null;
    $userInitials = collect(explode(' ', auth()->user()->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<aside x-cloak :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="sidebar fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-ustp-navy text-white transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0">
    @if ($roleId === 0)<div class="sidebar-primary">@endif
        <a href="{{ $homePaths[$roleId] ?? '/login' }}" class="{{ $roleId === 2 ? 'brand-header' : ($roleId === 0 ? 'sidebar-header' : 'sidebar-brand') }}">
            <span class="{{ $roleId === 2 ? 'brand-crest' : ($roleId === 0 ? 'brand-emblem-svg' : ($roleId === 3 ? 'logo-slot logo-slot--sm logo-slot--dark' : 'brand-logo')) }}">
                @if ($roleId === 0)
                    <svg class="super-admin-brand-crest" viewBox="0 0 40 40" fill="none" role="img" aria-label="USTP logo"><circle cx="20" cy="20" r="18.5" stroke="#fab005" stroke-width="1.5" stroke-dasharray="2.5 2" fill="#04153b"/><circle cx="20" cy="20" r="14.5" fill="#00205b" stroke="#3b5998" stroke-width=".8"/><path d="M20 8 24 15H16L20 8Z" fill="#fff" fill-opacity=".85"/><text x="20" y="26" fill="#fab005" font-family="sans-serif" font-size="7.5" font-weight="800" text-anchor="middle" letter-spacing=".4">USTP</text><path d="M11 30c4 3 14 3 18 0" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/></svg>
                @else
                    <img src="{{ asset('images/ustp-claveria-logo.jpg') }}" alt="USTP Claveria" class="{{ $roleId === 3 ? 'logo-slot-img' : 'brand-logo-img' }}">
                @endif
            </span>
            @if ($roleId === 1)
                <span class="brand-text"><h1>Compli<span>Track</span></h1><p>Claveria Campus</p></span>
            @else
                <span class="{{ $roleId === 0 ? 'super-admin-brand-copy' : '' }}">
                    <span class="{{ $roleId === 0 ? 'brand-title' : ($roleId === 3 ? 'brand-title brand-white' : 'brand-title') }}">Compli<span class="{{ $roleId === 0 ? 'track-highlight' : ($roleId === 2 ? 'gold-text' : 'brand-gold') }}">Track</span></span>
                    <span class="brand-subtitle">{{ $roleId === 0 ? 'CLAVERIA CAMPUS' : 'Claveria Campus' }}</span>
                </span>
            @endif
        </a>
        <nav class="{{ $roleId === 2 ? 'nav-menu' : 'sidebar-nav' }}" aria-label="Main navigation">
            @foreach ($menus[$roleId] ?? [] as [$label, $href, $icon])
                @php($active = request()->is(ltrim($href, '/'), ltrim($href, '/').'/*'))
                <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                   class="{{ $roleId === 0 ? 'nav-link-item' : 'nav-item' }} {{ $active ? 'active' : '' }}">
                    <i data-lucide="{{ $icon }}" class="{{ $roleId === 0 ? 'nav-icon' : ($roleId === 2 ? 'nav-icon' : '') }} size-5 shrink-0"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    @if ($roleId === 0)</div>@endif
    <a href="/profile" class="{{ $roleId === 0 ? 'sidebar-footer' : 'sidebar-footer' }} {{ $roleId === 2 ? 'sidebar-user-info' : '' }}">
        <span class="{{ $roleId === 0 ? 'user-profile-widget' : ($roleId === 2 ? 'user-avatar-small' : ($roleId === 1 ? 'avatar-sm' : 'flex size-10 items-center justify-center rounded-full bg-ustp-gold font-semibold text-ustp-navy')) }}">
            <img src="{{ $userAvatarUrl ?? '' }}" alt="" class="{{ $roleId === 0 ? 'avatar-circle' : ($roleId === 2 ? 'avatar-img' : 'shared-user-avatar') }} {{ $userAvatarUrl ? '' : 'hidden' }}" data-user-avatar>
            <span class="{{ $userAvatarUrl ? 'hidden' : '' }} {{ $roleId === 0 ? 'avatar-initials' : ($roleId === 2 ? 'avatar-initials auditor-avatar-initials' : '') }}" data-user-avatar-fallback>{{ $userInitials }}</span>
        </span>
        <span class="{{ $roleId === 0 ? 'user-meta-info' : ($roleId === 2 ? '' : 'min-w-0') }}">
            <span class="{{ $roleId === 0 ? 'user-display-name' : ($roleId === 1 ? 'footer-user-name' : ($roleId === 2 ? 'user-name' : 'block truncate text-sm font-semibold')) }}">{{ auth()->user()->fullName }}</span>
            <span class="{{ $roleId === 0 ? 'user-department-tag' : ($roleId === 1 ? 'footer-user-dept' : ($roleId === 2 ? 'user-role' : 'block text-xs text-white/60')) }}">{{ $roleNames[$roleId] ?? 'User' }}</span>
        </span>
    </a>
</aside>
