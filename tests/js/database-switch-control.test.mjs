import test from 'node:test';
import assert from 'node:assert/strict';
import { databaseSwitchControl } from '../../resources/js/database-switch-control.js';

test('connection label and switch action agree on the active database without assuming remote', () => {
    const control = databaseSwitchControl();
    assert.equal(control.databaseConnectionLabel(), 'Checking database...');
    control.loading = false;
    assert.equal(control.databaseConnectionLabel(), 'Database environment unavailable');
    for (const [active, target] of [['local', 'Remote'], ['remote', 'Local']]) {
        control.state = { databaseSwitch: { activeEnvironment: active, health: 'connected' } };
        assert.equal(control.databaseConnectionLabel(), `${active.toUpperCase()} — Connected`);
        assert.equal(control.databaseSwitchLabel(), `Switch to ${target} Database`);
        control.state.databaseSwitch.health = 'unavailable';
        assert.equal(control.databaseConnectionLabel(), `${active.toUpperCase()} — Unavailable`);
    }
});

test('opens the animated progress dialog immediately and prevents dismissing an active switch', async () => {
    const control = databaseSwitchControl();
    let respond;
    const progress = { open: false, showModal() { this.open = true; }, close() { this.open = false; } };
    control.$refs = { databaseSwitchDialog: { close() {} }, databaseSwitchProgress: progress };
    control.state = { databaseSwitch: { targetEnvironment: 'remote', targetConfigured: true } };
    control.databaseConfirmation = 'SWITCH TO REMOTE';
    control.watchDatabaseSwitch = () => {};
    globalThis.window = { axios: { post: () => new Promise(resolve => { respond = resolve; }) } };
    const submitted = control.confirmDatabaseSwitch();
    assert.equal(progress.open, true);
    assert.equal(control.busy, true);
    control.closeDatabaseSwitchProgress();
    assert.equal(progress.open, true);
    respond({ data: { statusUrl: '/status' } });
    await submitted;
    assert.equal(progress.open, true);
});

test('shows confirmed stage progress and only reaches 100 percent after verification', () => {
    const control = databaseSwitchControl();
    for (const [stage, percent] of [['queued', 5], ['preflight', 15], ['changing_target', 45], ['starting_services', 90], ['rollback_failed', 90]]) {
        control.switchProgressStage = stage;
        assert.equal(control.databaseSwitchPercent(), percent);
    }
    control.switchProgressOutcome = 'switch_verified';
    assert.equal(control.databaseSwitchPercent(), 100);
});

test('keeps a failed switch popup open with an actionable error and allows closing it', async () => {
    const control = databaseSwitchControl();
    const progress = { open: false, showModal() { this.open = true; }, close() { this.open = false; } };
    control.$refs = { databaseSwitchProgress: progress };
    control.state = { databaseSwitch: {} };
    globalThis.window = { axios: { get: async () => { throw { response: { status: 403 } }; } } };
    await control.watchDatabaseSwitch('/expired-status');
    assert.equal(progress.open, true);
    assert.equal(control.switching, false);
    assert.match(control.error, /Unable to retrieve/);
    control.closeDatabaseSwitchProgress();
    assert.equal(progress.open, false);
});

test('shows a persisted worker failure without starting an endless switching indicator', async () => {
    const control = databaseSwitchControl();
    globalThis.window = { axios: { get: async () => ({ data: {
        databaseSwitch: { pendingRequest: { stage: 'rollback_failed', statusUrl: '/status' } },
    } }) } };
    control.watchDatabaseSwitch = () => assert.fail('Failed worker must not be watched again');
    await control.init();
    assert.equal(control.switching, false);
    assert.match(control.error, /failed to restart services/);
});

test('dashboard and settings switch to the opposite database using the existing worker endpoint', async () => {
    for (const [active, target] of [['local', 'remote'], ['remote', 'local']]) {
        const control = databaseSwitchControl();
        let closed = false;
        let watched;
        control.state = { databaseSwitch: { activeEnvironment: active, targetEnvironment: target, targetConfigured: true } };
        control.$refs = { databaseSwitchDialog: { close() { closed = true; } } };
        control.watchDatabaseSwitch = url => { watched = url; };
        globalThis.window = { axios: { post: async (url, payload) => {
            assert.equal(url, '/super-admin/system/database-switch');
            assert.deepEqual(payload, { target, confirmation: `SWITCH TO ${target.toUpperCase()}` });
            return { data: { message: 'Restarting', statusUrl: '/signed-progress' } };
        } } };
        assert.equal(control.databaseSwitchLabel(), `Switch to ${target === 'local' ? 'Local' : 'Remote'} Database`);
        control.databaseConfirmation = control.databaseConfirmationPhrase();
        await control.confirmDatabaseSwitch();
        assert.equal(closed, true);
        assert.equal(watched, '/signed-progress');
        assert.equal(control.switching, true);
        assert.equal(control.busy, false);
        assert.equal(control.databaseConfirmation, '');
    }
});

test('requires typed confirmation and prevents opening when target is missing or switch pending', async () => {
    const control = databaseSwitchControl();
    control.state = { databaseSwitch: { targetEnvironment: 'remote', targetConfigured: false } };
    control.$refs = { databaseSwitchDialog: { showModal() { assert.fail('Unavailable switch must stay closed'); } } };
    control.openDatabaseSwitch();
    control.state.databaseSwitch.targetConfigured = true;
    control.state.databaseSwitch.pendingRequest = { stage: 'queued' };
    control.openDatabaseSwitch();
    globalThis.window = { axios: { post() { assert.fail('Incorrect confirmation must not submit'); } } };
    control.databaseConfirmation = 'SWITCH TO LOCAL';
    await control.confirmDatabaseSwitch();
    assert.equal(control.busy, false);
});

test('resumes a queued switch on page load and redirects to login after services restart', async () => {
    const control = databaseSwitchControl();
    let redirected;
    globalThis.window = {
        axios: { get: async url => url === '/api/v1/settings'
            ? { data: { databaseSwitch: { pendingRequest: { statusUrl: '/signed-progress' } } } }
            : { data: { complete: true, outcome: 'switch_verified' } } },
        location: { replace(url) { redirected = url; } },
    };
    await control.init();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(redirected, '/login?reason=database-switched');
    control.destroy();
});
