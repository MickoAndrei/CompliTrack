const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function fixture(name) {
    const factories = {}, calls = [], listeners = new Map();
    let total = 125, fail = false, timer, cleared;
    const context = {
        Alpine: { data: (name, factory) => { factories[name] = factory; } },
        enhancedUserManagement: () => ({}), URLSearchParams,
        axios: {
            get: async (url, options) => {
                calls.push({ url, ...options.params });
                if (fail) throw Error('Offline');
                return { data: { data: [{ _id: 'one', isRead: false }], meta: { total } } };
            },
            patch: async () => { total = 0; },
        },
        window: {
            location: { search: '' }, localStorage: { getItem: () => null },
            setInterval: callback => { timer = callback; return 42; },
            clearInterval: id => { cleared = id; },
            addEventListener: (type, callback) => listeners.set(type, callback),
            removeEventListener: type => listeners.delete(type),
        },
        document: {
            hidden: false,
            addEventListener: (type, callback) => type === 'alpine:init' ? callback() : listeners.set(type, callback),
            removeEventListener: type => listeners.delete(type),
        },
    };
    vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../resources/js/pages.js'), 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
    const app = factories[name]();
    return { app, calls, listeners, tick: () => timer(), setTotal: value => { total = value; }, setFail: () => { fail = true; }, cleared: () => cleared };
}

for (const name of ['notificationBadge', 'auditeeNotifications', 'activityNotifications']) {
    test(`${name}: fetches total before opening, refreshes in background and cleans up`, async () => {
        const f = fixture(name);
        f.app.init();
        await new Promise(setImmediate);
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

for (const name of ['auditeeNotifications', 'activityNotifications']) {
    test(`${name}: marking all read updates the badge`, async () => {
        const { app } = fixture(name);
        await app.refreshUnreadCount();
        app.notifications = [{ _id: 'one', isRead: false }];
        await app.markAllRead();
        assert.equal(app.unreadCount, 0); assert.equal(app.notifications[0].isRead, true);
    });
}
