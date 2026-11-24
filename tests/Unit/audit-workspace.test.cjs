const test = require('node:test');
const assert = require('node:assert/strict');

test('workspace deep links constrain sections, items, and panels', async () => {
    const { workspaceLocation } = await import('../../resources/js/audit-workspace.js');
    assert.deepEqual(workspaceLocation('#checklist/2/evidence', 4), { tab: 'checklist', selected: 2, panel: 'evidence' });
    assert.deepEqual(workspaceLocation('#review/999/invalid', 4), { tab: 'review', selected: 3, panel: 'evaluation' });
    assert.deepEqual(workspaceLocation('#bad/-8/evidence', 0), { tab: 'checklist', selected: 0, panel: 'evidence' });
    assert.deepEqual(workspaceLocation('', 2), { tab: 'checklist', selected: 0, panel: 'evaluation' });
    assert.equal(workspaceLocation('#evidence-requests', 2).tab, 'requests');
});
