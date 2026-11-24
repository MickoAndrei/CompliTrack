const { test } = require('node:test');
const assert = require('node:assert/strict');
const { replaceWithRollback, verifySourceUnchanged } = require('../../scripts/copy-remote-to-local.cjs');

test('a source change aborts replacement and rolls back', async () => {
    const source = { collections: [{ name: 'records', documents: ['original'], options: '{}', indexes: '[]' }] };
    const changed = structuredClone(source);
    changed.collections[0].documents.push('changed');
    let local = 'old local';
    await assert.rejects(replaceWithRollback({
        replace: async () => { local = 'export'; },
        verifyAndReport: async () => verifySourceUnchanged(source, changed),
        rollback: async () => { local = 'old local'; },
    }), /remote database changed/);
    assert.equal(local, 'old local');
    assert.doesNotThrow(() => verifySourceUnchanged(source, structuredClone(source)));
});

for (const stage of ['replace', 'snapshot', 'source changed', 'report']) {
    test(`copy restores original data after ${stage} failure`, async () => {
        let local = 'original';
        await assert.rejects(replaceWithRollback({
            replace: async () => { local = 'new'; if (stage === 'replace') throw Error(stage); return local; },
            verifyAndReport: async () => { throw Error(stage); },
            rollback: async () => { local = 'original'; },
        }), new RegExp(stage));
        assert.equal(local, 'original');
    });
}
test('copy commits only after successful verification and report persistence', async () => {
    const events = [];
    const report = await replaceWithRollback({
        replace: async () => { events.push('replace'); return 'new'; },
        verifyAndReport: async copied => { assert.equal(copied, 'new'); events.push('verify', 'report'); return 'saved'; },
        rollback: async () => { events.push('rollback'); },
    });
    assert.deepEqual(events, ['replace', 'verify', 'report']);
    assert.equal(report, 'saved');
});
