const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('legacy unread checkbox filters requests and visible rows', async () => {
    let factory;
    const calls = [];
    const context = { URLSearchParams, portalDate() {}, enhancedUserManagement: () => ({}),
        window: { location: { search: '' } },
        document: { addEventListener: (_, callback) => callback() },
        Alpine: { data: (name, value) => { if (name === 'resourcePage') factory = value; } },
        readNotifications: async params => {
            calls.push(params);
            return { data: { data: params.status === 'unread' ? [{ _id: 'unread' }] : [{ _id: 'read' }, { _id: 'unread' }],
                meta: { currentPage: 1, lastPage: 1 } } };
        },
    };
    vm.runInNewContext(fs.readFileSync('resources/js/pages.js', 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
    const app = factory({ endpoint: 'notifications', columns: [] });
    app.$nextTick = () => {};
    await app.load(1);
    assert.equal(app.rows.length, 2);
    app.unreadOnly = true;
    await app.load(1);
    assert.equal(calls.at(-1).status, 'unread');
    assert.equal(app.rows.length, 1);
    assert.equal(app.rows[0]._id, 'unread');
    app.unreadOnly = false;
    await app.load(1);
    assert.equal(calls.at(-1).status, 'all');
    assert.equal(app.rows.length, 2);
});
