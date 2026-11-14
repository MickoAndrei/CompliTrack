const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function fixture() {
    let factory; const calls = [];
    const context = { Intl, Date, document: { addEventListener: (_, callback) => callback() },
        window: { Alpine: { data: (_, callback) => { factory = callback; } }, axios: {
            get: async (url, options) => { calls.push({ url, ...options }); return { data: { data: [], summary: {}, departments: [], meta: { total: 0, lastPage: 1, currentPage: 1 } } }; },
            patch: async (url, data) => { calls.push({ url, data }); },
        } } };
    vm.runInNewContext(fs.readFileSync('resources/js/corrective-actions.js', 'utf8'), context);
    const app = factory(); app.$nextTick = callback => callback();
    app.$refs = { details: { open: false, showModal() { this.open = true; } } };
    return { app, calls, axios: context.window.axios };
}

test('CAR filtering sends the server all filters and discards stale results', async () => {
    const f = fixture(), pending = [];
    f.axios.get = (url, config) => { f.calls.push(config); return new Promise(resolve => pending.push(resolve)); };
    f.app.search = 'inspection'; f.app.departmentId = 'department'; f.app.status = 'awaiting_verification';
    const first = f.app.load(2); f.app.search = 'register'; const second = f.app.load(1);
    assert.equal(f.calls[0].params.page, 2); assert.equal(f.calls[0].params.status, 'awaiting_verification');
    assert.equal(f.calls[0].params.departmentId, 'department'); assert.equal(f.calls[1].params.search, 'register');
    const result = name => ({ data: { data: [{ _id: name }], summary: {}, departments: [], meta: { currentPage: 1, lastPage: 1 } } });
    pending[1](result('current')); await second; pending[0](result('old')); await first;
    assert.equal(f.app.rows[0]._id, 'current');
});

test('closing CAR details prevents a delayed response from restoring the record', async () => {
    const f = fixture(); let resolve;
    f.axios.get = () => new Promise(callback => { resolve = callback; });
    const request = f.app.openDetails({ _id: 'car' }); f.app.closeDetails();
    resolve({ data: { data: { _id: 'car' } } }); await request;
    assert.equal(f.app.detail, null); assert.equal(f.app.detailLoading, false);
});

test('verification requires notes, blocks duplicate submission and uses only allowed transitions', async () => {
    const f = fixture();
    f.app.detail = { _id: 'car', correctiveStatus: 'in_progress', allowedTransitions: ['closed'] };
    await f.app.submitTransition(); assert.equal(f.calls.length, 0); assert.match(f.app.detailError, /notes/);
    let resolve; f.axios.patch = (url, data) => { f.calls.push({ url, data }); return new Promise(callback => { resolve = callback; }); };
    f.app.remarks = 'Inspected the physical register';
    f.app.openDetails = async () => {}; f.app.load = async () => {};
    const request = f.app.submitTransition(); await f.app.submitTransition();
    assert.equal(f.calls.length, 1); assert.equal(f.calls[0].data.correctiveStatus, 'closed');
    resolve({}); await request; assert.equal(f.app.saving, false);
    f.app.detail.allowedTransitions = []; await f.app.submitTransition(); assert.equal(f.calls.length, 1);
});

test('failed verification retains the notes and shows the backend authorization error', async () => {
    const f = fixture(); f.app.detail = { _id: 'car', allowedTransitions: ['closed'] }; f.app.remarks = 'Verification note';
    f.axios.patch = async () => { throw { response: { data: { message: 'This department is outside your scope.' } } }; };
    await f.app.submitTransition(); assert.equal(f.app.remarks, 'Verification note'); assert.match(f.app.detailError, /outside your scope/);
    assert.equal(f.app.saving, false); assert.equal(f.app.date(null), 'Date not recorded');
});
