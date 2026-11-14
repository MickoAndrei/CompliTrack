const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function fixture(endpoint = 'notifications') {
    const factories = {}, calls = [];
    let get = async () => ({ data: { data: [], meta: { total: 0, lastPage: 1, currentPage: 1, unreadCount: 0 } } });
    let patch = async () => ({});
    const dialog = { open: false, showModal() { this.open = true; }, close() { this.open = false; } };
    const context = {
        Alpine: { data: (name, factory) => { factories[name] = factory; } }, enhancedUserManagement: () => ({}),
        URLSearchParams, AbortController, Event,
        document: { addEventListener: (name, callback) => { if (name === 'alpine:init') callback(); } },
        window: { location: { search: '' }, clearTimeout() {}, setTimeout() {}, dispatchEvent() {} },
        axios: {
            get: (url, options) => { calls.push({ method: 'GET', url, ...options }); return get(url, options); },
            patch: (url, options) => { calls.push({ method: 'PATCH', url }); return patch(url, options); },
        },
    };
    context.readNotifications = params => context.axios.get('/api/v1/notifications', { params });
    vm.runInNewContext(fs.readFileSync('resources/js/pages.js', 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
    const app = factories.resourcePage({ endpoint, columns: [] });
    app.$nextTick = callback => callback(); app.$refs = { recordDetails: dialog };
    return { app, calls, dialog, get: callback => { get = callback; }, patch: callback => { patch = callback; } };
}

test('notification filters are server-side and stale search results cannot replace newer ones', async () => {
    const f = fixture(); const pending = [];
    f.get(() => new Promise(resolve => pending.push(resolve)));
    f.app.notificationStatus = 'read'; f.app.search = 'older';
    const first = f.app.load(1);
    f.app.search = 'newer'; const second = f.app.load(1);
    assert.equal(f.calls[0].params.status, 'read'); assert.equal(f.calls[1].params.search, 'newer');
    pending[1]({ data: { data: [{ _id: 'newer' }, { _id: 'newer' }], meta: { lastPage: 1, unreadCount: 9 } } }); await second;
    pending[0]({ data: { data: [{ _id: 'older' }], meta: { lastPage: 1, unreadCount: 1 } } }); await first;
    assert.equal(f.app.rows.length, 1); assert.equal(f.app.rows[0]._id, 'newer'); assert.equal(f.app.notificationUnreadCount, 9);
});

test('failed read requests preserve unread state and expose a retryable error', async () => {
    const f = fixture(); const row = { _id: 'one', isRead: false };
    f.patch(async () => { throw Error('Offline'); });
    await f.app.markRead(row);
    assert.equal(row.isRead, false); assert.ok(f.app.error); assert.equal(f.app.markingReadIds.length, 0);
    f.app.notificationUnreadCount = 3;
    await f.app.markAllNotificationsRead();
    assert.equal(f.app.notificationUnreadCount, 3); assert.equal(f.app.markingAll, false);
});

test('directory requests encode the summary flag as an API-valid query boolean', async () => {
    const f = fixture('users');
    f.app.directoryPage = true;
    await f.app.load(1);
    assert.equal(f.calls[0].params.includeSummary, 1);
    const query = new URLSearchParams(f.calls[0].params);
    assert.equal(query.get('includeSummary'), '1');
});

test('notification actions are disabled for log resources and log filters survive CSV export', async () => {
    const f = fixture('logs');
    f.app.notificationUnreadCount = 2;
    await f.app.markRead({ _id: 'log', isRead: false }); await f.app.markAllNotificationsRead();
    assert.equal(f.calls.length, 0);
    f.app.categoryFilter = 'security'; f.app.actorFilter = 'Reviewer'; f.app.outcomeFilter = 'denied';
    const params = f.app.auditLogParams();
    assert.equal(params.category, 'security'); assert.equal(params.actor, 'Reviewer'); assert.equal(params.outcome, 'denied');
});

test('closing or replacing details prevents a stale authorized response from returning', async () => {
    const f = fixture('logs'); const pending = [];
    f.get(() => new Promise(resolve => pending.push(resolve)));
    const first = f.app.openRecordDetails({ _id: 'first' });
    const second = f.app.openRecordDetails({ _id: 'second' });
    assert.equal(f.calls[0].signal.aborted, true);
    pending[1]({ data: { data: { _id: 'second' } } }); await second;
    pending[0]({ data: { data: { _id: 'first' } } }); await first;
    assert.equal(f.app.details._id, 'second');
    const third = f.app.openRecordDetails({ _id: 'third' }); f.app.closeRecordDetails();
    pending[2]({ data: { data: { _id: 'third' } } }); await third;
    assert.equal(f.app.details, null);
});
