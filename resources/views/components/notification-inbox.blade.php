@props(['allowBroadcast' => false])
<section class="attention-page notification-inbox" x-data="resourcePage({ endpoint: 'notifications', columns: [], perPage: 10, allowBroadcast: @js($allowBroadcast) })" x-init="load()" aria-label="Notifications">
    <div class="notification-toolbar">
        <label class="attention-search"><i data-lucide="search" aria-hidden="true"></i><span class="sr-only">Search notifications</span><input type="search" maxlength="200" placeholder="Search notifications…" x-model="search" @input.debounce.350ms="load(1)"></label>
        <div class="attention-actions">
        @if($allowBroadcast)<button type="button" class="attention-button broadcast-trigger" @click="startBroadcast()"><i data-lucide="send" aria-hidden="true"></i>Broadcast</button>@endif
        <button type="button" class="attention-button" @click="markAllNotificationsRead()" :disabled="markingAll || loading || !notificationUnreadCount" x-text="markingAll ? 'Marking…' : 'Mark all read'"></button>
        </div>
    </div>
    <div class="attention-filter-row"><div class="attention-tabs" role="group" aria-label="Notification status">
        @foreach(['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $value => $label)
            <button type="button" @click="notificationStatus = '{{ $value }}'; load(1)" :aria-pressed="notificationStatus === '{{ $value }}'" :class="{ 'is-active': notificationStatus === '{{ $value }}' }">{{ $label }}</button>
        @endforeach
    </div><div class="attention-filter-extras"><label><span class="sr-only">Notification category</span><select aria-label="Notification category" x-model="typeFilter" @change="load(1)"><option value="">All categories</option>@foreach(['evidence_requested' => 'Evidence requested', 'evidence_submitted' => 'Evidence submitted', 'evidence_reviewed' => 'Evidence reviewed', 'audit_assigned' => 'Audit assigned', 'audit_submitted' => 'Audit submitted', 'audit_accepted' => 'Audit accepted', 'audit_returned' => 'Audit returned', 'audit_result_shared' => 'Audit result shared', 'status_changed' => 'CAR updates', 'corrective_action_created' => 'Corrective action created', 'compliant_confirmed' => 'Compliance confirmed', 'admin_broadcast' => 'Announcements'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><span class="attention-pill" aria-live="polite" x-text="`${notificationUnreadCount} unread`"></span></div></div>
    <p class="attention-feedback" x-cloak x-show="message" x-text="message" role="status"></p>
    <div class="attention-error" x-cloak x-show="error" role="alert"><p x-text="error"></p><button type="button" class="attention-button" @click="load()">Try again</button></div>
    <div class="attention-loading" x-show="loading" role="status" aria-label="Loading notifications"><span class="sr-only">Loading notifications…</span><template x-for="item in 3" :key="item"><div class="attention-skeleton" aria-hidden="true"><span></span><span></span><span></span></div></template></div>
    <div class="attention-empty" x-cloak x-show="!loading && !error && !rows.length"><i data-lucide="bell" aria-hidden="true"></i><h3 x-text="search || notificationStatus !== 'all' ? 'No matching notifications' : 'You’re all caught up'"></h3><p x-text="search || notificationStatus !== 'all' ? 'Try another search or filter.' : 'New updates will appear here when they arrive.'"></p></div>
    <div class="notification-list" x-cloak x-show="!loading && !error" :aria-busy="loading">
        <template x-for="notification in rows" :key="notification._id"><article class="notification-item" :class="{ 'is-unread': !notification.isRead }">
            <span class="notification-symbol" aria-hidden="true"><i data-lucide="file-check-2"></i></span>
            <div class="notification-copy"><div class="notification-title"><h3 x-text="notification.title"></h3><span class="attention-new" x-text="notification.isRead ? 'Read' : 'New'"></span></div>
                <p class="notification-excerpt" x-text="notification.message"></p>
                <div class="notification-metadata"><time :datetime="notification.createdAt" x-text="formatValue(notification.createdAt)"></time><span class="attention-pill" x-text="notification.category || 'Update'"></span></div>
                <div class="notification-actions"><button type="button" class="attention-button" x-show="!notification.isRead" @click="markRead(notification)" :disabled="markingAll || markingReadIds.includes(notification._id)" x-text="markingReadIds.includes(notification._id) ? 'Marking…' : 'Mark read'"></button><button type="button" class="attention-button is-primary" @click="openRecordDetails(notification)">View details<i data-lucide="arrow-right" aria-hidden="true"></i></button></div>
            </div>
        </article></template>
    </div>
    @include('components.partials.attention-pagination', ['label' => 'Notification pages'])
    <dialog x-ref="recordDetails" class="attention-dialog" @click="if ($event.target === $el) $el.close()" @close="closeRecordDetails()" aria-labelledby="notification-detail-title">
        <header><div><p class="attention-eyebrow">NOTIFICATION</p><h2 id="notification-detail-title">Notification details</h2></div><button type="button" class="attention-button" @click="$refs.recordDetails.close()" aria-label="Close notification details"><i data-lucide="x" aria-hidden="true"></i></button></header>
        <div class="attention-dialog-body"><p x-show="detailLoading" role="status">Loading notification…</p><p class="attention-error" x-show="detailError" x-text="detailError" role="alert"></p><template x-if="details && !detailLoading"><div><h3 x-text="details.title"></h3><p class="attention-detail-message" x-text="details.message"></p><div class="notification-metadata"><span class="attention-pill" x-text="details.category"></span><time :datetime="details.createdAt" x-text="formatValue(details.createdAt)"></time><span x-text="details.isRead ? 'Read' : 'Unread'"></span></div></div></template></div>
        <footer><button type="button" class="attention-button" @click="$refs.recordDetails.close()">Close</button><a class="attention-button is-primary" x-show="details?.destination" :href="details?.destination">Open related record<i data-lucide="arrow-right" aria-hidden="true"></i></a></footer>
    </dialog>
    @if($allowBroadcast) @include('components.partials.notification-broadcast') @endif
</section>
