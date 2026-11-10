const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function fixture() {
    const factories = {}, redirects = [], cleared = [];
    let status = 'pending', fail = false, callback, interval;
    const context = {
        Alpine: {data: (name, factory) => factories[name] = factory}, enhancedUserManagement: () => ({}),
        axios: {get: async () => ({}), post: async url => {
            if (fail) throw Error('Offline');
            return {data: url.endsWith('/forgot-password') ? {watch: 'opaque-watch-fixture'} : {status}};
        }},
        window: {location: {assign: value => redirects.push(value)},
            setInterval: (fn, delay) => {callback = fn; interval = delay; return 42;}, clearInterval: id => cleared.push(id)},
        document: {addEventListener: (name, fn) => {if (name === 'alpine:init') fn();}}, URLSearchParams,
    };
    vm.runInNewContext(fs.readFileSync('resources/js/pages.js', 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
    return {app: factories.forgotPasswordPage(), redirects, cleared, interval: () => interval, tick: () => callback(),
        complete: () => status = 'completed', expire: () => status = 'expired', fail: () => fail = true};
}

test('inbox tab polls every five seconds and returns to sign-in after reset completion', async () => {
    const f = fixture(); f.app.email = 'recipient@example.test'; await f.app.submit();
    assert.equal(f.app.submitted, true); assert.equal(f.interval(), 5000);
    await f.tick(); assert.deepEqual(f.redirects, []);
    f.complete(); await f.tick(); assert.deepEqual(f.redirects, ['/login?reset=success']);
    assert.ok(f.cleared.includes(42));
});
test('expired requests stop polling and component disposal cleans up its timer', async () => {
    const f = fixture(); await f.app.submit(); f.expire(); await f.tick();
    assert.equal(f.app.resetStatus, 'expired'); assert.deepEqual(f.redirects, []);
    f.app.destroy(); assert.ok(f.cleared.includes(42));
});
test('temporary polling failure does not report reset success or navigate away', async () => {
    const f = fixture(); await f.app.submit(); f.fail(); await f.tick();
    assert.equal(f.app.resetStatus, 'pending'); assert.equal(f.app.checkingReset, false);
    assert.deepEqual(f.redirects, []);
});
