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
            <a href="{{ $profilePath }}" class="header-user-badge" aria-label="Open my profile">
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
            </a>
        @elseif ($roleId === 3)
            <a href="{{ $profilePath }}" class="inline-flex items-center gap-2 rounded-full bg-slate-100 py-1.5 pl-1.5 pr-3 text-sm font-semibold text-ustp-navy transition hover:bg-slate-200" aria-label="Open profile">
                <span class="flex size-9 items-center justify-center overflow-hidden rounded-full bg-ustp-gold text-xs font-bold text-ustp-navy">
                    @if ($userAvatarUrl)
                        <img src="{{ $userAvatarUrl }}" alt="" class="size-full object-cover" data-user-avatar>
                        <span class="hidden" data-user-avatar-fallback>{{ $userInitials }}</span>
                    @else
                        <img src="" alt="" class="hidden" data-user-avatar>
                        <span data-user-avatar-fallback>{{ $userInitials }}</span>
                    @endif
                </span>
                <span class="hidden sm:inline">{{ auth()->user()->fullName }}</span>
            </a>
        @endif
    </div>
</header>
