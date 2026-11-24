const { test } = require('node:test');
const assert = require('node:assert/strict');
test('Manila timestamps and calendar-only dates ignore the browser timezone', async () => {
    const { portalDate } = await import('../../resources/js/portal-date.js');
    process.env.TZ = 'America/Los_Angeles';
    assert.match(portalDate('2026-12-31T23:59:59Z', {precise:true}), /1 Jan 2027|Jan 1, 2027/);
    assert.match(portalDate('2026-12-31T23:59:59Z', {precise:true}), /UTC\+08:00/);
    assert.match(portalDate('2026-12-31'), /31 Dec 2026|Dec 31, 2026/);
    assert.equal(portalDate('invalid'), 'Date unavailable');
});
