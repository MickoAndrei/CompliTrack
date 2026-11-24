@props(['notificationsUrl' => '/auditee/notifications', 'compact' => false])
<div x-cloak x-show="open" class="header-notification-panel" :style="{ height: `${({{ $compact ? 'true' : 'false' }} && !loading ? Math.max(1, Math.min(previewLimit(), previewItems().length)) : previewLimit()) * 68 + 134}px` }" role="dialog" aria-label="Notifications" :aria-busy="loading">
    <header>
        <svg class="header-notification-heading-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        <h2>Notifications</h2>
        <button type="button" @click="markAllRead()" :disabled="loading || markingRead || badgeCount() === 0">Read all</button>
        <button type="button" @click="clearNotifications()" :disabled="loading || markingRead || previewItems().length === 0">Clear all</button>
        <button type="button" @click="close()" aria-label="Close notifications">×</button>
    </header>
    <div x-show="loading" role="status" class="header-notification-loading">
        <span class="sr-only">Loading notifications…</span>
        <template x-for="row in previewLimit()" :key="row"><div class="header-notification-skeleton" aria-hidden="true"><span></span><span></span><span></span></div></template>
    </div>
    <div x-show="!loading" class="header-notification-content" :class="{ 'is-empty': !error && previewItems().length === 0 }">
        <p x-page-notice x-show="error" role="alert" class="header-notification-error" x-text="error"></p>
        <div x-show="!error && previewItems().length === 0" class="header-notification-empty">
            <svg class="header-notification-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
            <p>You're all caught up.</p>
        </div>
        <template x-for="item in previewItems().slice((previewPage - 1) * previewLimit(), previewPage * previewLimit())" :key="item.kind + item.record._id">
            <button type="button" class="header-notification-item" @click="markNotificationRead(item.record)" :title="item.record.message" :class="{'is-unread': !item.record.isRead}">
                <span class="header-notification-item-icon" aria-hidden="true">
                    <svg x-show="item.kind === 'notification'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4M2 8a10 10 0 0 1 2-5M22 8a10 10 0 0 0-2-5"/></svg>
                </span>
                <span class="header-notification-item-copy">
                    <strong x-text="item.record.title"></strong>
                    <span x-text="item.record.message"></span>
                    <time :datetime="item.record.createdAt" x-text="formatActivityTime(item.record.createdAt)"></time>
                </span>
            </button>
        </template>
    </div>
    <footer>
        <button type="button" @click="previewPage--" :disabled="loading || previewPage <= 1" aria-label="Previous notifications">Previous</button>
        <span x-text="`${previewPage} / ${previewPages()}`"></span>
        <button type="button" @click="previewPage++" :disabled="loading || previewPage >= previewPages()" aria-label="Next notifications">Next</button>
        <a href="{{ $notificationsUrl }}">View all notifications</a>
    </footer>
</div>
