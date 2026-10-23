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
        @if ($roleId === 0)
            <div class="relative" x-data="activityNotifications()" @click.outside="close()" @keydown.escape.window="close()">
                <button type="button" @click="toggle()" class="notification-bell-btn" aria-label="Recent activity" :aria-expanded="open.toString()" aria-haspopup="true">
                    <i data-lucide="bell" class="size-5"></i>
                    <span x-show="unreadCount > 0" x-cloak class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white" x-text="unreadCount"></span>
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-0 top-12 z-[100] overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-xl" style="width: min(24rem, calc(100vw - 2rem));" role="dialog" aria-label="Recent activity">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100" style="padding: 16px 24px;">
                        <div class="min-w-0">
                            <h2 class="flex items-center gap-2 text-sm font-bold leading-5 text-ustp-navy"><i data-lucide="activity" class="size-4 shrink-0 text-ustp-gold" aria-hidden="true"></i>Recent activity</h2>
                        </div>
                        <button type="button" @click="clearNotifications()" class="inline-flex shrink-0 items-center gap-1.5 rounded-md text-xs font-semibold text-ustp-navy hover:bg-slate-100 disabled:opacity-50" style="padding: 8px 12px;" :disabled="loading || markingRead || (activities.length === 0 && notifications.length === 0)"><i data-lucide="x-circle" class="size-3.5" aria-hidden="true"></i>Clear notifications</button>
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        <section x-show="notifications.length > 0" class="border-b border-slate-100 px-5 py-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-600"><i data-lucide="bell" class="size-3.5 text-ustp-gold" aria-hidden="true"></i>Notifications</h3>
                                <button x-show="unreadCount > 0" type="button" @click="markAllRead()" :disabled="markingRead" class="rounded px-2 py-1 text-xs font-semibold text-ustp-navy hover:bg-slate-100 disabled:opacity-50" x-text="markingRead ? 'Marking...' : 'Mark all as read'"></button>
                            </div>
                            <template x-for="notification in notifications" :key="notification._id">
                                <article class="border-t border-slate-100 first:border-0">
                                    <button type="button" @click="markNotificationRead(notification)" class="flex w-full items-start gap-3 rounded-lg text-left transition-colors hover:bg-slate-50" :class="notification.isRead ? 'bg-white' : 'bg-blue-50/50'" style="padding: 10px 8px;">
                                        <i data-lucide="bell-ring" class="mt-1 size-4 shrink-0 text-ustp-gold" aria-hidden="true"></i>
                                        <span class="min-w-0 flex-1 space-y-0.5"><span class="block text-xs font-semibold leading-5 text-slate-800" x-text="notification.title"></span><span class="block text-xs leading-5 text-slate-600" x-text="notification.message"></span><time class="block text-[11px] leading-4 text-slate-400" :datetime="notification.createdAt" x-text="formatActivityTime(notification.createdAt)"></time></span>
                                        <span x-show="!notification.isRead" x-transition class="mt-1.5 size-2 shrink-0 rounded-full bg-blue-600" aria-label="Unread"></span>
                                    </button>
                                </article>
                            </template>
                        </section>
                        <p x-show="loading && activities.length === 0" class="px-4 py-5 text-center text-sm text-slate-500">Loading activity...</p>
                        <p x-show="error" class="px-4 py-3 text-sm text-red-700" x-text="error"></p>
                        <div x-show="!loading && !error && activities.length === 0" class="flex flex-col items-center text-center text-sm text-slate-500" style="padding: 24px 28px;">
                            <span class="mb-3 flex size-10 items-center justify-center rounded-full bg-slate-100 text-ustp-navy"><i data-lucide="activity" class="size-5" aria-hidden="true"></i></span>
                            <p>No activity has been recorded yet.</p>
                        </div>
                        <template x-for="activity in activities" :key="activity._id">
                            <article class="border-b border-slate-100 last:border-b-0">
                                <button type="button" @click="openActivity(activity)" class="flex w-full items-start gap-4 text-left transition-colors duration-300 hover:bg-slate-50" :class="isActivityRead(activity) ? 'bg-white' : 'bg-blue-50/40'" style="padding: 20px 24px;">
                                    <span class="relative mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-ustp-navy"><i data-lucide="file-clock" class="size-4" aria-hidden="true"></i><span x-show="!isActivityRead(activity)" x-transition class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-white bg-blue-600" aria-label="Unread"></span></span>
                                    <span class="min-w-0 flex-1 space-y-2"><span class="block text-sm font-semibold leading-6 text-slate-800" x-text="activity.action"></span>
                                    <span class="flex flex-wrap items-center gap-x-1.5 text-xs leading-5 text-slate-500"><span x-text="activity.userName || 'System'"></span><span aria-hidden="true">&middot;</span><time :datetime="activity.createdAt" x-text="formatActivityTime(activity.createdAt)"></time></span></span>
                                </button>
                            </article>
                        </template>
                    </div>
                    <a href="/super-admin/logs" class="block border-t border-slate-100 text-center text-sm font-semibold text-ustp-navy hover:bg-slate-50" style="padding: 16px 24px;">View all activity</a>
                </div>
            </div>
        @elseif ($roleId === 3)
            <div class="relative" x-data="auditeeNotifications()" @click.outside="close()" @keydown.escape.window="close()">
                <button type="button" @click="toggle()" class="notification-bell-btn" aria-label="Notifications" :aria-expanded="open.toString()" aria-haspopup="true">
                    <i data-lucide="bell" class="size-5"></i>
                    <span x-show="unreadCount > 0" x-cloak class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white" x-text="unreadCount"></span>
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-0 top-12 z-[100] overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-xl" style="width: min(24rem, calc(100vw - 2rem));" role="dialog" aria-label="Notifications">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100" style="padding: 16px 24px;">
                        <h2 class="flex items-center gap-2 text-sm font-bold leading-5 text-ustp-navy"><i data-lucide="bell" class="size-4 shrink-0 text-ustp-gold" aria-hidden="true"></i>Notifications</h2>
                        <button type="button" @click="clearNotifications()" class="inline-flex shrink-0 items-center gap-1.5 rounded-md text-xs font-semibold text-ustp-navy hover:bg-slate-100 disabled:opacity-50" style="padding: 8px 12px;" :disabled="loading || markingRead || notifications.length === 0"><i data-lucide="x-circle" class="size-3.5" aria-hidden="true"></i>Clear notifications</button>
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        <div x-show="notifications.length > 0" class="px-5 py-3">
                            <div class="mb-2 flex items-center justify-end">
                                <button x-show="unreadCount > 0" type="button" @click="markAllRead()" :disabled="markingRead" class="rounded px-2 py-1 text-xs font-semibold text-ustp-navy hover:bg-slate-100 disabled:opacity-50" x-text="markingRead ? 'Marking...' : 'Mark all as read'"></button>
                            </div>
                            <template x-for="notification in notifications" :key="notification._id">
                                <article class="border-t border-slate-100 first:border-0">
                                    <button type="button" @click="markNotificationRead(notification)" class="flex w-full items-start gap-3 rounded-lg text-left transition-colors hover:bg-slate-50" :class="notification.isRead ? 'bg-white' : 'bg-blue-50/50'" style="padding: 10px 8px;">
                                        <i data-lucide="bell-ring" class="mt-1 size-4 shrink-0 text-ustp-gold" aria-hidden="true"></i>
                                        <span class="min-w-0 flex-1 space-y-0.5"><span class="block text-xs font-semibold leading-5 text-slate-800" x-text="notification.title"></span><span class="block text-xs leading-5 text-slate-600" x-text="notification.message"></span><time class="block text-[11px] leading-4 text-slate-400" :datetime="notification.createdAt" x-text="formatActivityTime(notification.createdAt)"></time></span>
                                        <span x-show="!notification.isRead" x-transition class="mt-1.5 size-2 shrink-0 rounded-full bg-blue-600" aria-label="Unread"></span>
                                    </button>
                                </article>
                            </template>
                        </div>
                        <p x-show="loading && notifications.length === 0" class="px-4 py-5 text-center text-sm text-slate-500">Loading notifications...</p>
                        <p x-show="error" class="px-4 py-3 text-sm text-red-700" x-text="error"></p>
                        <div x-show="!loading && !error && notifications.length === 0" class="flex flex-col items-center text-center text-sm text-slate-500" style="padding: 24px 28px;">
                            <span class="mb-3 flex size-10 items-center justify-center rounded-full bg-slate-100 text-ustp-navy"><i data-lucide="bell-off" class="size-5" aria-hidden="true"></i></span>
                            <p>No notifications yet.</p>
                        </div>
                    </div>
                    <a href="/auditee/notifications" class="block border-t border-slate-100 text-center text-sm font-semibold text-ustp-navy hover:bg-slate-50" style="padding: 16px 24px;">View all notifications</a>
                </div>
            </div>
        @else
            <a href="{{ match ($roleId) { 1 => '/admin/notifications', 2 => '/auditor/notifications', 3 => '/auditee/notifications', default => '/profile' } }}" class="{{ $roleId === 1 ? 'notif-btn' : 'icon-btn' }}" aria-label="Notifications">
                <i data-lucide="bell" class="size-5"></i>
            </a>
        @endif
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
        @elseif ($roleId === 3)
            <a href="/profile" class="inline-flex items-center gap-2 rounded-full bg-slate-100 py-1.5 pl-1.5 pr-3 text-sm font-semibold text-ustp-navy transition hover:bg-slate-200" aria-label="Open profile">
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
