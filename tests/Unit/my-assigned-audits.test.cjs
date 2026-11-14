const { test } = require('node:test');
const assert = require('node:assert/strict');

async function setup() {
    const { assignedAuditFilters } = await import('../../resources/js/my-assigned-audits.js');
    const handlers = {}, fields = Object.fromEntries(['search', 'departmentId', 'status'].map(name => [name, { value: '', addEventListener: (event, callback) => { handlers[`${name}:${event}`] = callback; } }]));
    const form = { elements: { namedItem: name => fields[name] }, getAttribute: () => '/audits', addEventListener: (name, callback) => { handlers[name] = callback; } };
    const content = { hidden: false };
    const tabs = ['', 'draft', 'submitted'].map(status => ({ href: 'http://example.test/audits?status=' + status, current: status === '', setAttribute() { this.current = true; }, removeAttribute() { this.current = false; } }));
    const results = { childNodes: ['original'], querySelectorAll: () => tabs, querySelector: () => content, setAttribute: (_name, value) => { results.busy = value; }, replaceChildren: (...nodes) => { results.childNodes = nodes; } };
    const summary = { childNodes: ['counts'], replaceChildren: (...nodes) => { summary.childNodes = nodes; } };
    const loading = { hidden: true }, error = { hidden: true }, message = {};
    const root = { querySelector: selector => ({ '[data-my-audit-filters]': form, '[data-my-audit-results]': results,
        '[data-my-audit-summary]': summary, '[data-my-audit-loading]': loading, '[data-my-audit-error]': error,
        '[data-my-audit-error-text]': message, '[data-my-audit-retry]': { addEventListener: (_event, callback) => { handlers.retry = callback; } } })[selector],
        addEventListener: (name, callback) => { handlers[`root:${name}`] = callback; } };
    const requests = [], history = [];
    const window = { location: { href: 'http://example.test/audits?page=3&_tab=abc', origin: 'http://example.test' },
        history: { state: { retained: true }, pushState: (state, _title, url) => { history.push({ state, url }); window.location.href = String(url); } },
        addEventListener: (name, callback) => { handlers[`window:${name}`] = callback; }, removeEventListener: () => {} };
    const component = assignedAuditFilters({ root, window, fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, reject,
        resolve: text => resolve({ ok: true, redirected: false, text: async () => text }) })),
        DOMParser: class { parseFromString(html) { return { querySelector: selector => html === 'invalid' ? null : { childNodes: [selector + ':' + html] } }; } } });
    return { component, requests, fields, results, content, tabs, summary, loading, error, message, handlers, history, window };
}

test('initial rendered audits make no request; filtering resets pagination and preserves tab context and history', async () => {
    const app = await setup();
    assert.equal(app.requests.length, 0);
    app.fields.search.value = 'recorded title'; app.fields.status.value = 'submitted';
    const request = app.handlers.submit({ preventDefault() {} });
    assert.equal(app.requests[0].url.searchParams.has('page'), false);
    assert.equal(app.requests[0].url.searchParams.get('_tab'), 'abc');
    assert.equal(app.requests[0].url.searchParams.get('search'), 'recorded title');
    assert.equal(app.requests[0].url.searchParams.get('status'), 'submitted');
    assert.equal(app.loading.hidden, false);
    assert.equal(app.content.hidden, true);
    assert.notEqual(app.results.hidden, true);
    app.requests[0].resolve('filtered'); await request;
    assert.equal(app.results.busy, 'false');
    assert.equal(app.history.length, 1); assert.deepEqual(app.history[0].state, { retained: true });
    assert.deepEqual(app.summary.childNodes, ['counts']);
    assert.equal(app.content.hidden, false);
    assert.equal(app.loading.hidden, true);
    app.component.dispose();
});

test('stale responses cannot replace newer audits, and failures retain results with an explicit retry', async () => {
    const app = await setup();
    const old = app.component.load('/audits?status=draft'), latest = app.component.load('/audits?status=ready');
    assert.equal(app.requests[0].options.signal.aborted, true);
    app.requests[1].resolve('ready'); await latest;
    app.requests[0].resolve('stale'); await old;
    assert.ok(app.results.childNodes[0].endsWith('ready'));
    assert.equal(app.content.hidden, false);
    assert.equal(app.loading.hidden, true);
    const failed = app.component.load('/audits?status=accepted');
    app.requests[2].reject(Error('Unavailable')); await failed;
    assert.equal(app.error.hidden, false); assert.match(app.message.textContent, /previous results/);
    assert.ok(app.results.childNodes[0].endsWith('ready'));
    assert.equal(app.fields.status.value, 'ready');
    const retry = app.handlers.retry(); app.requests[3].resolve('accepted'); await retry;
    assert.ok(app.results.childNodes[0].endsWith('accepted'));
    app.component.dispose();
});

test('browser history reloads the matching filtered results without pushing another history entry', async () => {
    const app = await setup();
    app.window.location.href = 'http://example.test/audits?status=returned';
    const navigation = app.handlers['window:popstate']();
    app.requests[0].resolve('returned'); await navigation;
    assert.equal(app.history.length, 0); assert.equal(app.fields.status.value, 'returned');
    assert.ok(app.results.childNodes[0].endsWith('returned'));
    app.component.dispose();
});

test('clear filters resets every field and pagination while preserving the tab and summary', async () => {
    const app = await setup();
    Object.values(app.fields).forEach(field => { field.value = 'active'; });
    let prevented = false;
    const request = app.handlers['root:click']({
        preventDefault() { prevented = true; },
        target: { closest: selector => selector === '[data-my-audit-reset]' ? {} : null },
    });
    assert.equal(prevented, true);
    assert.ok(Object.values(app.fields).every(field => field.value === ''));
    assert.equal(app.requests[0].url.search, '?_tab=abc');
    app.requests[0].resolve('cleared'); await request;
    assert.deepEqual(app.summary.childNodes, ['counts']);
    assert.equal(app.loading.hidden, true);
    app.component.dispose();
});

test('typing cancels an outstanding response before the debounce can overwrite the new search', async () => {
    const app = await setup();
    const previous = app.handlers['status:change']();
    app.fields.search.value = 'new search';
    app.handlers['search:input']();
    assert.equal(app.requests[0].options.signal.aborted, true);
    app.requests[0].resolve('old search'); await previous;
    assert.equal(app.fields.search.value, 'new search');
    assert.deepEqual(app.results.childNodes, ['original']);
    app.component.dispose();
});

test('tab selection updates immediately, leads skeleton loading, and rolls back on failure', async () => {
    const app = await setup();
    const link = { href: app.tabs[2].href, closest: () => ({}) };
    const request = app.handlers['root:click']({ button: 0, preventDefault() {},
        target: { closest: selector => selector === 'a[href]' ? link : null } });
    assert.equal(app.tabs[2].current, true);
    assert.equal(app.tabs[0].current, false);
    assert.equal(app.fields.status.value, 'submitted');
    assert.equal(app.loading.hidden, true);
    assert.equal(app.content.hidden, false);
    await new Promise(resolve => setTimeout(resolve, 180));
    assert.equal(app.loading.hidden, false);
    assert.equal(app.content.hidden, true);
    app.requests[0].reject(Error('Unavailable')); await request;
    assert.equal(app.tabs[0].current, true);
    assert.equal(app.tabs[2].current, false);
    assert.equal(app.loading.hidden, true);
    app.component.dispose();
});
