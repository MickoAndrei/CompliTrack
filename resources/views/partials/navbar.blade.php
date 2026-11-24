@php($roleId = (int) auth()->user()->roleId)
@php($roleNames = ['Super Admin', 'Admin', 'Auditor', 'Auditee'])
@php($userAvatarUrl = auth()->user()->avatarUrl())
@php($userInitials = collect(explode(' ', auth()->user()->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
<header class="top-header-bar app-header">
    <div class="header-left">
        <button type="button" class="menu-toggle" aria-label="Open navigation" @click="sidebarOpen = true">
            <i data-lucide="menu" class="size-5"></i>
        </button>
        <div>
            <h1 class="header-page-title">@yield('heading', 'CompliTrack')</h1>
            <p class="header-page-subtitle">@hasSection('subtitle') @yield('subtitle') @elseif ($roleId === 2){{ request()->is('profile', 'auditor/profile') ? 'Account details & security' : 'My assigned audits, checklists, and findings' }}@else USTP Claveria · Compliance Management @endif</p>
        </div>
    </div>
    <div class="header-right">
        @if (in_array($roleId, [0, 1, 3], true))
            <div class="relative" x-data="auditeeNotifications()" @click.outside="close()" @keydown.escape.window="close()">
                <button type="button" @click="toggle()" class="notification-bell-btn" :aria-label="badgeCount() > 0 ? `Notifications, ${badgeCount()} unread` : 'Notifications'" :aria-expanded="open.toString()" aria-haspopup="dialog">
                    <i data-lucide="bell" class="size-5"></i>
                    <span x-show="badgeCount() > 0" x-cloak class="header-notification-count" aria-hidden="true" x-text="badgeCount() > 99 ? '99+' : badgeCount()"></span>
                </button>
                <x-notification-dropdown :notifications-url="match ($roleId) { 0 => '/super-admin/notifications', 1 => '/admin/notifications', default => '/auditee/notifications' }" />
            </div>
        @else
            <a href="{{ match ($roleId) { 1 => '/admin/notifications', 2 => '/auditor/notifications', 3 => '/auditee/notifications', default => '/profile' } }}" class="notification-bell-btn" x-data="notificationBadge()" :aria-label="badgeCount() > 0 ? `Notifications, ${badgeCount()} unread` : 'Notifications'">
                <i data-lucide="bell" class="size-5"></i>
                <span x-show="badgeCount() > 0" x-cloak class="header-notification-count" aria-hidden="true" x-text="badgeCount() > 99 ? '99+' : badgeCount()"></span>
            </a>
        @endif
        @if (in_array($roleId, [0, 1, 2, 3], true))
            <div class="header-divider"></div>
            <div class="header-profile-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
            <button type="button" class="header-user-badge" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" aria-label="Open account menu">
                @if ($userAvatarUrl)
                    <img src="{{ $userAvatarUrl }}" alt="" class="header-avatar" data-user-avatar>
                    <span class="header-avatar header-avatar-initials hidden" data-user-avatar-fallback>{{ $userInitials }}</span>
                @else
                    <img src="" alt="" class="header-avatar hidden" data-user-avatar>
                    <span class="header-avatar header-avatar-initials" data-user-avatar-fallback>{{ $userInitials }}</span>
                @endif
                <span class="header-user-info">
                    <span class="header-user-name">{{ auth()->user()->fullName }}</span>
                    <span class="header-user-role">{{ $roleNames[$roleId] ?? 'User' }} <x-organizational-responsibility :user="auth()->user()" /></span>
                </span>
            </button>
            <div class="header-profile-dropdown" x-cloak x-show="open">
                <a href="{{ $profilePath }}"><i data-lucide="circle-user-round" aria-hidden="true"></i><span>Profile</span></a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><i data-lucide="log-out" aria-hidden="true"></i><span>Logout</span></button></form>
            </div>
            </div>
        @endif
    </div>
</header>
