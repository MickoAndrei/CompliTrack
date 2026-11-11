const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const crypto = require('node:crypto');
const sleep = ms => new Promise(r => setTimeout(r, ms));

test('secondary work waits for all primary requests and runs after their settlement', async () => {
    const page = await import('../../resources/js/page-work.js?gate');
    const first = page.beginPrimaryRequest(), second = page.beginPrimaryRequest();
    let called = 0;
    page.deferAfterPrimaryReady(() => called++);
    page.pageInitialized();
    await sleep(60); assert.equal(called, 0);
    first(); first(); // Release is idempotent.
    await sleep(45); assert.equal(called, 0);
    second(); await sleep(60); assert.equal(called, 1);
});

test('secondary work has a bounded wait and disposed work never runs', async () => {
    const page = await import('../../resources/js/page-work.js?deadline');
    const finish = page.beginPrimaryRequest();
    let called = 0;
    page.deferAfterPrimaryReady(() => called++, { maxDelay: 15 });
    const cancel = page.deferAfterPrimaryReady(() => called += 100, { maxDelay: 15 });
    cancel();
    await sleep(45); assert.equal(called, 1);
    finish();
});

test('notification reads coalesce only in flight and never cross an account boundary', async () => {
    const { readNotifications, clearNotificationRequests } = await import('../../resources/js/notification-data.js');
    const requests = [];
    global.document = { body: { dataset: { userId: 'account-one' } } };
    global.window = { axios: { get: (url, options) => new Promise((resolve, reject) => {
        requests.push({ url, options, resolve });
        options.signal.addEventListener('abort', () => reject(Error('cancelled')), { once: true });
    }) } };
    try {
        const first = readNotifications({ unread: true, perPage: 1 });
        assert.equal(first, readNotifications({ perPage: 1, unread: true }));
        assert.equal(requests.length, 1);
        requests[0].resolve({ data: { meta: { total: 4 } } }); await first;
        const next = readNotifications({ unread: true, perPage: 1 });
        assert.notEqual(first, next, 'Completed authorization-sensitive responses must not be cached');
        document.body.dataset.userId = 'account-two';
        const other = readNotifications({ unread: true, perPage: 1 });
        assert.notEqual(next, other);
        const rejected = [assert.rejects(next, /cancelled/), assert.rejects(other, /cancelled/)];
        clearNotificationRequests(); await Promise.all(rejected);
    } finally { clearNotificationRequests(); delete global.window; delete global.document; }
});

test('local font assets match their recorded hashes and carry each original OFL license', () => {
    const manifest = JSON.parse(fs.readFileSync('public/fonts/sources.json', 'utf8'));
    for (const family of ['inter', 'plusjakartasans', 'poppins']) {
        assert.match(fs.readFileSync(`public/fonts/${family}/OFL.txt`, 'utf8'), /SIL OPEN FONT LICENSE Version 1.1/);
    }
    for (const font of manifest.files) {
        const family = font.family.toLowerCase().replaceAll(' ', '');
        const content = fs.readFileSync(`public/fonts/${family}/${font.filename}`);
        assert.equal(content.subarray(0, 4).toString(), 'wOF2');
        assert.equal(crypto.createHash('sha256').update(content).digest('hex'), font.sha256);
    }
});
