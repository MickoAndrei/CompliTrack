@php($roleId = (int) auth()->user()->roleId)
@php($roleNames = ['Super Admin', 'Admin', 'Auditor', 'Auditee'])
@php($userAvatarUrl = auth()->user()->avatarUrl())
@php($userInitials = collect(explode(' ', auth()->user()->fullName ?? 'U'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
<header class="{{ in_array($roleId, [0, 2, 3], true) ? 'top-header-bar' : ($roleId === 1 ? 'topbar' : 'top-header') }}">
    <div class="{{ in_array($roleId, [0, 2, 3], true) ? 'header-left' : ($roleId === 1 ? 'topbar-left' : 'header-titles') }}">
        <button type="button" class="{{ in_array($roleId, [0, 2, 3], true) ? 'menu-toggle' : 'rounded-lg p-2 text-ustp-dark hover:bg-ustp-light lg:hidden' }}" aria-label="Open navigation" @click="sidebarOpen = true">
            <i data-lucide="menu" class="size-5"></i>
        </button>
        <div>
            @if ($roleId === 1)
                <h2>@yield('heading', 'CompliTrack')</h2>
            @else
                <h1 class="{{ in_array($roleId, [0, 2, 3], true) ? 'header-page-title' : 'page-title' }}">@yield('heading', 'CompliTrack')</h1>
            @endif
            <p class="{{ in_array($roleId, [0, 2, 3], true) ? 'header-page-subtitle' : ($roleId === 1 ? '' : 'page-subtitle') }}">@if ($roleId === 2){{ request()->is('profile', 'auditor/profile') ? 'Account details & security' : 'My assigned audits, checklists, and findings' }}@else @yield('subtitle', 'USTP Claveria · Compliance Management') @endif</p>
        </div>
    </div>
    <div class="{{ in_array($roleId, [0, 2, 3], true) ? 'header-right' : ($roleId === 1 ? 'topbar-right' : 'header-actions') }}">
        @if ($roleId === 0)
            <div class="relative" x-data="activityNotifications()" @click.outside="close()" @keydown.escape.window="close()">
                <button type="button" @click="toggle()" class="notification-bell-btn" :aria-label="badgeCount() > 0 ? `Recent activity, ${badgeCount()} unread items` : 'Recent activity'" :aria-expanded="open.toString()" aria-haspopup="true">
                    <i data-lucide="bell" class="size-5"></i>
                    <span x-show="badgeCount() > 0" x-cloak class="header-notification-count" aria-hidden="true" x-text="badgeCount() > 99 ? '99+' : badgeCount()"></span>
                </button>
                <x-notification-dropdown :activity="true" />
            </div>
        @elseif (in_array($roleId, [1, 3], true))
            <div class="relative" x-data="auditeeNotifications()" @click.outside="close()" @keydown.escape.window="close()">
                <button type="button" @click="toggle()" class="{{ $roleId === 1 ? 'notif-btn' : 'notification-bell-btn' }}" :aria-label="badgeCount() > 0 ? `Notifications, ${badgeCount()} unread` : 'Notifications'" :aria-expanded="open.toString()" aria-haspopup="dialog">
                    <i data-lucide="bell" class="size-5"></i>
                    <span x-show="badgeCount() > 0" x-cloak class="header-notification-count" aria-hidden="true" x-text="badgeCount() > 99 ? '99+' : badgeCount()"></span>
                </button>
                <x-notification-dropdown :notifications-url="$roleId === 1 ? '/admin/notifications' : '/auditee/notifications'" :compact="$roleId === 1" />
            </div>
        @else
            <a href="{{ match ($roleId) { 1 => '/admin/notifications', 2 => '/auditor/notifications', 3 => '/auditee/notifications', default => '/profile' } }}" class="{{ $roleId === 1 ? 'notif-btn' : 'notification-bell-btn' }}" x-data="notificationBadge()" :aria-label="badgeCount() > 0 ? `Notifications, ${badgeCount()} unread` : 'Notifications'">
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
                    <span class="header-user-role">{{ $roleNames[$roleId] ?? 'User' }}</span>
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
