const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const handlers = {}, resultHandlers = {}, requests = [];
    const status = { value: '' }, message = { hidden: true, textContent: '' };
    const loading = { hidden: true };
    const results = { rows: ['original'], busy: false,
        setAttribute: (_name, value) => { results.busy = value === 'true'; },
        replaceChildren: (...rows) => { results.rows = rows; },
        addEventListener: (name, callback) => { resultHandlers[name] = callback; } };
    let changes = 0, prevented = 0;
    const filters = { action: 'http://example.test/audits',
        querySelector: () => status,
        addEventListener: (name, callback) => { handlers[name] = callback; },
        requestSubmit: () => handlers.submit({ preventDefault: () => { prevented++; } }) };
    status.addEventListener = (name, callback) => { handlers['status:' + name] = callback; };
    const window = { location: { href: 'http://example.test/audits?page=3', origin: 'http://example.test' }, addEventListener: () => {},
        history: { state: { existing: true }, replaceState: (state, _title, url) => { assert.deepEqual(state, { existing: true }); window.location.href = String(url); changes++; } } };
    const source = fs.readFileSync('resources/js/audit-assignments.js', 'utf8').replace(/^import .*;\r?\n/, '');
    vm.runInNewContext(source, { URL, AbortController, window,
        document: { querySelector: selector => ({ '[data-assignment-filters]': filters, '[data-assignment-results]': results, '[data-assignment-loading]': loading, '[data-assignment-filter-message]': message })[selector] || null },
        onDocumentReady: callback => callback(),
        DOMParser: class { parseFromString(html) { return { querySelector: () => html === 'missing' ? null : { childNodes: [html] } }; } },
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve: html => resolve({ ok: true, redirected: false, text: async () => html }), reject })),
    });
    const click = (group, href) => {
        const event = { button: 0, target: { closest: () => ({ href }) }, preventDefault: () => { prevented++; } };
        (group === 'filters' ? handlers : resultHandlers).click(event);
    };
    return { status, message, results, loading, requests, window, click,
        change(value) { status.value = value; handlers['status:change'](); },
        get changes() { return changes; }, get prevented() { return prevented; } };
}
const settle = () => new Promise(resolve => setImmediate(resolve));

test('status, reset and pagination replace only assignment results without document navigation', async () => {
    const app = setup();
    app.change('ready');
    assert.equal(app.prevented, 1);
    assert.equal(app.requests[0].url.href, 'http://example.test/audits?status=ready');
    assert.equal(app.results.busy, true);
    assert.equal(app.results.hidden, true);
    assert.equal(app.loading.hidden, false);
    assert.equal(app.message.hidden, true);
    assert.equal(app.requests[0].options.credentials, 'same-origin');
    app.requests[0].resolve('ready rows'); await settle();
    assert.deepEqual(app.results.rows, ['ready rows']);
    assert.equal(app.results.busy, false);
    assert.equal(app.results.hidden, false);
    assert.equal(app.loading.hidden, true);
    assert.equal(app.message.hidden, true);
    assert.equal(app.message.textContent, '');
    assert.equal(app.status.value, 'ready');
    app.click('results', 'http://example.test/audits?status=ready&page=2');
    app.requests[1].resolve('page two'); await settle();
    assert.deepEqual(app.results.rows, ['page two']);
    app.click('filters', 'http://example.test/audits');
    app.requests[2].resolve('all rows'); await settle();
    assert.equal(app.status.value, '');
    assert.equal(app.window.location.href, 'http://example.test/audits');
    assert.equal(app.changes, 3);
    assert.equal(app.prevented, 3);
});

test('rapid filter changes discard stale responses and failures retain the last successful table', async () => {
    const app = setup();
    app.change('draft'); app.change('ready');
    assert.equal(app.requests[0].options.signal.aborted, true);
    app.requests[1].resolve('latest rows'); await settle();
    app.change('accepted');
    app.requests[0].resolve('stale rows'); await settle();
    assert.deepEqual(app.results.rows, ['latest rows']);
    assert.equal(app.loading.hidden, false);
    assert.equal(app.results.hidden, true);
    assert.equal(app.changes, 1);
    app.requests[2].reject(Error('Network unavailable')); await settle();
    assert.deepEqual(app.results.rows, ['latest rows']);
    assert.equal(app.status.value, 'ready');
    assert.equal(app.results.busy, false);
    assert.equal(app.loading.hidden, true);
    assert.equal(app.results.hidden, false);
    assert.equal(app.message.hidden, false);
    assert.match(app.message.textContent, /previous results are still shown/);
    app.click('filters', 'http://example.test/audits');
    app.requests[3].resolve('missing'); await settle();
    assert.deepEqual(app.results.rows, ['latest rows']);
    assert.equal(app.changes, 1);
});
