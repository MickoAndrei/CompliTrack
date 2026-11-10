const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function fixture(get) {
    const factories = {};
    const downloads = [];
    const blobs = [];
    const context = {
        Alpine: { data: (name, factory) => { factories[name] = factory; } },
        enhancedUserManagement: () => ({}),
        axios: { get }, Blob, URLSearchParams,
        URL: { createObjectURL: blob => { blobs.push(blob); return 'blob:logs'; }, revokeObjectURL() {} },
        window: { location: { search: '' }, setTimeout() {}, clearTimeout() {} },
        document: {
            addEventListener: (_, callback) => callback(), body: { appendChild() {} },
            createElement: () => ({ click() { downloads.push(this.download); }, remove() {} }),
        },
    };
    const source = fs.readFileSync(path.resolve(__dirname, '../../resources/js/pages.js'), 'utf8').replace(/^import .*;\r?\n/gm, '');
    vm.runInNewContext(source, context);
    const app = factories.resourcePage({ endpoint: 'logs', columns: [] });
    return { app, downloads, blobs };
}

test('exports every matching page, snapshots search/sort and preserves the displayed page', async () => {
    const calls = [];
    const { app, downloads, blobs } = fixture(async (url, { params }) => {
        calls.push({ url, ...params });
        if (params.page === 1) { app.search = 'changed'; app.sortBy = 'action:desc'; }
        return { data: { data: [{ userName: 'User ' + params.page, action: 'Viewed', createdAt: '2026-11-10' }], meta: { lastPage: 3 } } };
    });
    app.page = 4; app.search = 'original'; app.sortBy = 'userName:asc';
    app.startDate = '2026-11-01'; app.endDate = '2026-11-10';
    await app.exportAuditLogs();
    assert.equal(calls.length, 3);
    calls.forEach((call, i) => {
        assert.equal(call.url, '/api/v1/logs'); assert.equal(call.page, i + 1);
        assert.equal(call.perPage, 100); assert.equal(call.search, 'original');
        assert.equal(call.sortBy, 'userName'); assert.equal(call.sortDirection, 'asc');
        assert.equal(call.startDate, '2026-11-01'); assert.equal(call.endDate, '2026-11-10');
    });
    assert.equal(app.page, 4); assert.equal(app.exporting, false);
    assert.equal(downloads.length, 1); assert.match(downloads[0], /^audit-logs-\d{4}-\d{2}-\d{2}\.csv$/);
    const bytes = new Uint8Array(await blobs[0].arrayBuffer());
    assert.deepEqual([...bytes.slice(0, 3)], [239, 187, 191]);
    assert.equal(await blobs[0].text(), '"User","Action","Date & Time"\r\n"User 1","Viewed","2026-11-10"\r\n"User 2","Viewed","2026-11-10"\r\n"User 3","Viewed","2026-11-10"');
    assert.equal(app.successPopupMessage, 'Exported 3 audit log record(s).');
});

test('applies and clears history ranges at page one, rejecting reversed dates', async () => {
    const { app } = fixture();
    let loadedPage;
    app.load = async page => { loadedPage = page; };
    app.draftStartDate = '2026-11-10'; app.draftEndDate = '2026-11-01';
    await app.applyDateRange();
    assert.ok(app.dateRangeError); assert.equal(loadedPage, undefined); assert.equal(app.startDate, '');
    app.draftStartDate = '2026-11-01';
    await app.applyDateRange();
    assert.equal(loadedPage, 1); assert.equal(app.dateRangeError, null);
    assert.equal(app.auditLogParams().endDate, '2026-11-01');
    await app.clearDateRange();
    assert.equal(app.startDate, ''); assert.equal(app.endDate, '');
    assert.equal(app.auditLogParams().startDate, undefined);
});

test('quotes CSV punctuation and multiline values, and neutralizes spreadsheet formulas', () => {
    const { app } = fixture();
    assert.equal(app.csvCell('Name, "quoted"\nnext line'), '"Name, ""quoted""\nnext line"');
    for (const prefix of ['=', '+', '-', '@', '\t', '\r', '\n']) {
        assert.equal(app.csvCell(prefix + 'value'), '"\'' + prefix + 'value"');
    }
    assert.equal(app.csvCell(null), '""');
});

test('failed later pages never download a partial CSV and allow retry', async () => {
    let fail = true;
    const { app, downloads } = fixture(async (_, { params }) => {
        if (params.page === 2 && fail) throw Error('Failed request');
        return { data: { data: [], meta: { lastPage: 2 } } };
    });
    await app.exportAuditLogs();
    assert.equal(downloads.length, 0); assert.ok(app.exportError); assert.equal(app.exporting, false);
    fail = false;
    await app.exportAuditLogs();
    assert.equal(app.exportError, null); assert.equal(downloads.length, 1);
});

test('prevents duplicate exports and exports only on the logs page', async () => {
    let release;
    let count = 0;
    const { app, downloads } = fixture(() => { count++; return new Promise(resolve => { release = resolve; }); });
    const exporting = app.exportAuditLogs();
    await app.exportAuditLogs();
    assert.equal(count, 1);
    release({ data: { data: [], meta: { lastPage: 1 } } });
    await exporting;
    assert.equal(downloads.length, 1);
    app.endpoint = 'users';
    await app.exportAuditLogs();
    assert.equal(count, 1);
});
