import test from 'node:test';
import assert from 'node:assert/strict';
import { waitForDatabaseSwitch, reconnectDatabaseSwitch } from '../../resources/js/database-switch.js';

test('waits through service and tunnel downtime until verification completes', async () => {
    const responses = [new Error('offline'), { response: { status: 502 } },
        { data: { complete: false, stage: 'starting_services' } },
        { data: { complete: true, outcome: 'switch_verified' } }];
    const progress = [];
    const result = await waitForDatabaseSwitch('/signed-status', {
        get: async url => {
            assert.equal(url, '/signed-status');
            const next = responses.shift();
            if (!next.data) throw next;
            return next;
        }, delay: async () => {}, onProgress: value => progress.push(value),
    });
    assert.equal(result.outcome, 'switch_verified');
    assert.equal(progress[0].stage, 'starting_services');
    assert.equal(responses.length, 0);
});

test('returns rollback and launch failure outcomes without claiming success', async () => {
    for (const outcome of ['rolled_back', 'rollback_failed', 'cancelled', 'automatic_worker_launch_failed']) {
        const result = await waitForDatabaseSwitch('/status', {
            get: async () => ({ data: { complete: true, outcome } }),
        });
        assert.equal(result.outcome, outcome);
    }
});

test('stops when a progress URL is invalid or expired', async () => {
    let calls = 0;
    const error = { response: { status: 403 } };
    await assert.rejects(waitForDatabaseSwitch('/status', {
        get: async () => { calls++; throw error; }, delay: async () => {},
    }), value => value === error);
    assert.equal(calls, 1);
});

test('aborts polling when the settings component is destroyed', async () => {
    const controller = new AbortController();
    const result = await waitForDatabaseSwitch('/status', {
        signal: controller.signal,
        get: async () => ({ data: { complete: false } }),
        delay: async () => controller.abort(),
    });
    assert.equal(result, null);
});

test('bounds retries rather than reporting an unverified switch as successful', async () => {
    await assert.rejects(waitForDatabaseSwitch('/status', {
        get: async () => ({ data: { complete: false } }),
        delay: async () => {}, attempts: 2,
    }), /could not be verified/);
});

test('caps elapsed waiting even when every request uses its full timeout', async () => {
    let clock = 0;
    let calls = 0;
    await assert.rejects(waitForDatabaseSwitch('/status', {
        now: () => clock,
        get: async () => { calls++; clock += 5000; throw new Error('offline'); },
        delay: async () => { clock += 2000; },
        timeoutMs: 14000,
    }), /could not be verified/);
    assert.equal(calls, 2);
});

test('recovers progress when the restart interrupts the initial switch response', async () => {
    let calls = 0;
    const result = await reconnectDatabaseSwitch({
        get: async () => {
            if (calls++ === 0) throw new Error('offline');
            return { data: { databaseSwitch: { pendingRequest: { statusUrl: '/signed-status' } } } };
        }, delay: async () => {},
    });
    assert.equal(result.statusUrl, '/signed-status');
    assert.equal(calls, 2);
});

test('returns a sign-in outcome when services restart and invalidate the old session', async () => {
    const result = await reconnectDatabaseSwitch({
        get: async () => { throw { response: { status: 401 } }; },
    });
    assert.equal(result.signedOut, true);
});
