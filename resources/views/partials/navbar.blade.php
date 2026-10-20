@php($roleId = (int) auth()->user()->roleId)
@php($roleNames = ['Super Admin', 'Admin', 'Auditor', 'Auditee'])
@php($userAvatarUrl = auth()->user()->avatarPath ? url('/api/v1/auth/avatar') : null)
@php($userInitials = collect(explode(' ', auth()->user()->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
<header class="{{ $roleId === 0 ? 'top-header-bar' : ($roleId === 1 ? 'topbar' : 'top-header') }}">
    <div class="{{ $roleId === 0 ? 'header-left' : ($roleId === 1 ? 'topbar-left' : 'header-titles') }}">
        <button type="button" class="{{ $roleId === 0 ? 'menu-toggle' : 'rounded-lg p-2 text-ustp-dark hover:bg-ustp-light lg:hidden' }}" aria-label="Open navigation" @click="sidebarOpen = true">
            <i data-lucide="menu" class="size-5"></i>
        </button>
        <div>
            @if ($roleId === 1)
                <h2>@yield('heading', 'CompliTrack')</h2>
            @else
                <h1 class="{{ $roleId === 0 ? 'header-page-title' : 'page-title' }}">@yield('heading', 'CompliTrack')</h1>
            @endif
            <p class="{{ $roleId === 0 ? 'header-page-subtitle' : ($roleId === 1 ? '' : 'page-subtitle') }}">USTP Claveria · Compliance Management</p>
        </div>
    </div>
    <div class="{{ $roleId === 0 ? 'header-right' : ($roleId === 1 ? 'topbar-right' : 'header-actions') }}">
        <a href="{{ match ($roleId) { 0 => '/super-admin/logs', 1 => '/admin/notifications', 2 => '/auditor/notifications', 3 => '/auditee/notifications', default => '/profile' } }}" class="{{ $roleId === 1 ? 'notif-btn' : ($roleId === 0 ? 'notification-bell-btn' : 'icon-btn') }}" aria-label="{{ $roleId === 0 ? 'Activity logs' : 'Notifications' }}">
            <i data-lucide="bell" class="size-5"></i>
        </a>
        @if ($roleId === 0)
            <div class="header-divider"></div>
            <a href="/profile" class="header-user-badge">
                @if ($userAvatarUrl)
                    <img src="{{ $userAvatarUrl }}" alt="" class="header-avatar" data-user-avatar>
                    <span class="header-avatar header-avatar-initials hidden" data-user-avatar-fallback>{{ $userInitials }}</span>
                @else
                    <img src="" alt="" class="header-avatar hidden" data-user-avatar>
                    <span class="header-avatar header-avatar-initials" data-user-avatar-fallback>{{ $userInitials }}</span>
                @endif
                <span class="header-user-info">
                    <span class="header-user-name">{{ auth()->user()->fullName }}</span>
                    <span class="header-user-role">{{ $roleNames[$roleId] ?? 'User' }}</span>
                </span>
            </a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border text-sm font-medium transition hover:bg-ustp-light" style="min-height: 2.375rem; padding: .4rem .75rem; border-color: #cbd5e1; background-color: #fff; color: #334155; font-size: .8125rem; white-space: nowrap;">
                <i data-lucide="log-out" class="size-4"></i>
                <span class="hidden sm:inline">Sign out</span>
            </button>
        </form>
    </div>
</header>
