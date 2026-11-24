const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

(async () => {
    let factory;
    const pending = [];
    const context = {
        document: {body: {dataset: {portalRole: '2', userId: 'actor'}}, addEventListener: (_, callback) => callback()},
        window: {Alpine: {data: (_, callback) => { factory = callback; }}, axios: {get: () => new Promise(resolve => pending.push(resolve))}},
        location: {search: ''}, URLSearchParams,
    };
    const source = fs.readFileSync(path.join(__dirname, '../../resources/js/qms-archives.js'), 'utf8').replace(/^import .*;\r?\n/gm, '');
    vm.runInNewContext(source, context);
    const app = factory();
    app.tree.units = ['first', 'second'].map(_id => ({_id, name: _id, canCreate: true}));
    app.currentView = 'department';
    app.$refs = {uploadForm: {reset() {}}, upload: {showModal() {}}};
    app.unitId = 'first';
    const first = app.create();
    app.unitId = 'second';
    const second = app.create();
    const response = (id, departmentId) => ({data: {data: [{_id: id, departmentId, auditorId: 'actor'}]}});
    pending[1](response('current', 'second'));
    await second;
    assert.equal(app.uploadChecks[0]._id, 'current');
    pending[0](response('stale', 'second'));
    await first;
    assert.equal(app.uploadChecks[0]._id, 'current', 'Stale responses cannot replace current folder findings');
    assert.equal(app.uploadDestination.unitId, 'second');
    assert.equal(app.uploadChecksLoading, false);
    app.tree.units[1].folderPermissions = {view: true, upload: false, correctiveUpload: true};
    const corrective = app.create();
    assert.equal(app.uploadDestination.correctiveOnly, true);
    pending[2](response('corrective', 'second'));
    await corrective;
    assert.equal(app.canUploadHere, true, 'Finding-authorized users retain a corrective upload entry point');
    const paginated = app.create();
    pending[3]({...response('first-page', 'first'), data: {...response('first-page', 'first').data, meta: {lastPage: 2}}});
    await new Promise(setImmediate);
    pending[4](response('older-finding', 'second'));
    await paginated;
    assert.equal(app.uploadChecks[0]._id, 'older-finding', 'Findings beyond the first page remain selectable');
    app.uploadFile = {name: 'retained.pdf'};
    context.window.axios.get = async () => { throw new Error('Unavailable'); };
    await app.loadUploadChecks();
    assert.ok(app.uploadChecksError);
    context.window.axios.get = async () => response('retried', 'second');
    await app.loadUploadChecks();
    assert.equal(app.uploadChecksError, '');
    assert.equal(app.uploadFile.name, 'retained.pdf', 'Retry preserves the selected file');
    console.log('PASS folder upload discards out-of-order finding responses');
})().catch(error => { console.error(error); process.exitCode = 1; });
