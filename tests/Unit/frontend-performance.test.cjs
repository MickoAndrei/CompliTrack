const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

test('explicit Lucide registry covers literal and array-driven icons in application source', async () => {
    const { registrySource } = await import('../../scripts/generate-icon-registry.mjs');
    assert.equal(fs.readFileSync('resources/js/icons.js', 'utf8'), registrySource(), 'Regenerate the icon registry after adding an icon.');
});

test('Users pagination reuses page-local options and explicit refresh fetches current options', async () => {
    const { enhancedUserManagement } = await import('../../resources/js/user-management.js');
    const calls = [];
    const latest = { departments: [{ _id: 'new' }], colleges: [], responsibilities: [] };
    global.axios = { get: async (url, config) => {
        calls.push({ url, params: config?.params });
        if (url.endsWith('/options')) return { data: { options: latest } };
        return { data: { data: [], meta: { currentPage: config.params.page, lastPage: 3 }, summary: { total: 3 },
            ...(config.params.includeOptions ? { options: { departments: [], colleges: [], responsibilities: [] } } : {}) } };
    } };
    const component = { ...enhancedUserManagement({ enhancedUsers: true }), page: 1, perPage: 10, search: '', $nextTick: () => {} };
    try {
        await component.load();
        assert.equal(calls[0].params.includeOptions, 1);
        const firstOptions = component.options;
        await component.load(2);
        assert.equal(calls[1].params.includeOptions, 0);
        assert.equal(component.options, firstOptions);
        await component.load(2, { refreshOptions: true });
        assert.equal(calls[2].params.includeOptions, 1);
        component.optionsLoadedAt = Date.now() - 61000;
        await component.load(3);
        assert.equal(calls[3].params.includeOptions, 1);
        await component.refreshUserOptions();
        assert.equal(calls[4].url, '/api/v1/users/options');
        assert.equal(component.options, latest);
    } finally { delete global.axios; }
});

test('delayed page modules initialize DOM handlers even after DOMContentLoaded', async () => {
    const { onDocumentReady } = await import('../../resources/js/dom-ready.js');
    let callback, calls = 0;
    global.document = { readyState: 'loading', addEventListener: (name, fn, options) => {
        assert.equal(name, 'DOMContentLoaded'); assert.equal(options.once, true); callback = fn;
    } };
    try {
        onDocumentReady(() => calls++); assert.equal(calls, 0);
        callback(); assert.equal(calls, 1);
        document.readyState = 'complete';
        onDocumentReady(() => calls++); assert.equal(calls, 2);
    } finally { delete global.document; }
});

test('obsolete User searches are aborted and cannot replace newer results', async () => {
    const { enhancedUserManagement } = await import('../../resources/js/user-management.js');
    const requests = [];
    global.axios = { get: (_url, options) => new Promise(resolve => requests.push({ options, resolve })) };
    const component = { ...enhancedUserManagement({ enhancedUsers: true }), page: 1, perPage: 10, search: '', $nextTick: () => {} };
    try {
        const old = component.load();
        component.search = 'updated';
        const current = component.load();
        assert.equal(requests[0].options.signal.aborted, true);
        const result = name => ({ data: { data: [{ fullName: name }], meta: { currentPage: 1, lastPage: 1 }, summary: {} } });
        requests[1].resolve(result('current')); await current;
        requests[0].resolve(result('obsolete')); await old;
        assert.equal(component.rows[0].fullName, 'current');
        assert.equal(component.error, null);
        const pending = component.load(); component.abortUserRequests();
        assert.equal(requests[2].options.signal.aborted, true);
        requests[2].resolve(result('disposed')); await pending;
        assert.equal(component.rows[0].fullName, 'current');
    } finally { delete global.axios; }
});
