const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function fixture(name) {
    const factories = {}, calls = [], listeners = new Map();
    let total = 125, activities = [], fail = false, timer, cleared, primaryReady = false;
    const deferred = new Set();
    const context = {
        Alpine: { data: (name, factory) => { factories[name] = factory; } },
        enhancedUserManagement: () => ({}), URLSearchParams, Event,
        deferAfterPrimaryReady: callback => {
            if (primaryReady) queueMicrotask(callback);
            else deferred.add(callback);
            return () => deferred.delete(callback);
        },
        readNotifications: params => context.axios.get('/api/v1/notifications', { params }),
        axios: {
            get: async (url, options) => {
                calls.push({ url, ...options.params });
                if (fail) throw Error('Offline');
                return { data: { data: url.endsWith('/logs') ? activities : [{ _id: 'one', isRead: false }], meta: { total } } };
            },
            patch: async () => { total = 0; },
        },
        window: {
            location: { search: '' }, localStorage: { getItem: () => null, setItem: () => {} },
            setInterval: callback => { timer = callback; return 42; },
            clearInterval: id => { cleared = id; },
            addEventListener: (type, callback) => listeners.set(type, callback),
            removeEventListener: type => listeners.delete(type),
            dispatchEvent: event => listeners.get(event.type)?.(event),
        },
        document: {
            hidden: false,
            addEventListener: (type, callback) => type === 'alpine:init' ? callback() : listeners.set(type, callback),
            removeEventListener: type => listeners.delete(type),
        },
    };
    vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../resources/js/pages.js'), 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
    const app = factories[name]({ endpoint: 'notifications' });
    return { app, factories, calls, listeners, tick: () => timer(), setTotal: value => { total = value; }, setActivities: value => { activities = value; }, setFail: () => { fail = true; }, cleared: () => cleared,
        ready: async () => { primaryReady = true; for (const callback of deferred) callback(); deferred.clear(); await new Promise(setImmediate); } };
}

test('Super Admin notification preview never queries audit logs', async () => {
    const f = fixture('auditeeNotifications');
    f.setTotal(0);
    f.setActivities([{ _id: 'first' }, { _id: 'second' }]);
    f.app.init(); await f.ready(); await f.app.toggle();
    assert.equal(f.app.badgeCount(), 0);
    assert.ok(f.calls.every(call => call.url === '/api/v1/notifications'));
    f.app.destroy();
});

test('reading a notification on the notifications page refreshes the header', async () => {
    const f = fixture('notificationBadge');
    f.app.init();
    await f.ready();
    const page = f.factories.resourcePage({ endpoint: 'notifications' });
    const row = { _id: 'one', isRead: false };
    await page.markRead(row);
    await new Promise(setImmediate);
    assert.equal(row.isRead, true);
    assert.equal(f.app.badgeCount(), 0);
    f.app.destroy();
});

for (const name of ['notificationBadge', 'auditeeNotifications']) {
    test(`${name}: defers the total until primary content is ready, then polls and cleans up`, async () => {
        const f = fixture(name);
        f.app.init();
        assert.equal(f.calls.length, 0, 'Initial notifications must not block the primary content request');
        await f.ready();
        assert.equal(f.app.unreadCount, 125, 'Uses the full unread total, not the one returned row');
        f.app.notifications = [{ _id: 'new', isRead: false }];
        assert.equal(f.app.previewItems().length, 1, 'Preview reflects records arriving after initialization');
        f.app.previewHeight = 400;
        assert.equal(f.app.previewLimit(), 2, 'Short viewports show fewer items');
        assert.equal(f.calls[0].url, '/api/v1/notifications');
        assert.equal(f.calls[0].unread, true); assert.equal(f.calls[0].perPage, 1);
        f.setTotal(0); f.tick();
        await new Promise(setImmediate);
        assert.equal(f.app.unreadCount, 0);
        f.setTotal(7); await f.app.refreshUnreadCount();
        f.setFail(); await f.app.refreshUnreadCount();
        assert.equal(f.app.unreadCount, 7, 'Network errors retain the last known count');
        f.app.destroy();
        assert.equal(f.cleared(), 42); assert.equal(f.listeners.size, 0);
    });
}

for (const name of ['auditeeNotifications']) {
    test(`${name}: marking all read updates the badge`, async () => {
        const { app } = fixture(name);
        await app.refreshUnreadCount();
        app.notifications = [{ _id: 'one', isRead: false }];
        await app.markAllRead();
        assert.equal(app.unreadCount, 0); assert.equal(app.notifications[0].isRead, true);
    });
}

test('notification read actions never count or mutate audit activity', async () => {
    const { app } = fixture('auditeeNotifications');
    app.unreadCount = 3;
    app.activities = [{ _id: 'historical-log' }];
    app.notifications = [{ _id: 'one', isRead: false }, { _id: 'one', isRead: false }];
    assert.equal(app.badgeCount(), 3);
    assert.equal(app.previewItems().length, 1, 'Stable IDs remove repeated display');
    await app.markAllRead();
    assert.equal(app.badgeCount(), 0);
    assert.equal(app.activities.length, 1, 'Audit history is untouched');
});

for (const name of ['notificationBadge', 'auditeeNotifications']) {
    test(`${name}: notification changes refresh the badge without waiting for polling`, async () => {
        const f = fixture(name);
        f.app.init();
        await f.ready();
        f.setTotal(2);
        await f.listeners.get('complitrack:notifications-changed')();
        assert.equal(f.app.unreadCount, 2);
        f.app.destroy();
        assert.equal(f.listeners.size, 0);
    });
}
