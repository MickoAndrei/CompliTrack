const { test } = require('node:test');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const http = require('node:http');
const { once } = require('node:events');
const path = require('node:path');
const fs = require('node:fs');

test('development router serves complete static styles with and without diagnostic headers', async t => {
    const reservation = http.createServer(); reservation.listen(0, '127.0.0.1'); await once(reservation, 'listening');
    const port = reservation.address().port; await new Promise(r => reservation.close(r));
    const root = path.resolve(__dirname, '../..');
    const child = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(root, 'scripts/performance/router.php')], {
        cwd: path.join(root, 'public'), env: { ...process.env, COMPLITRACK_DIAGNOSTIC_TOKEN: 'isolated-static-test' }, windowsHide: true, stdio: 'ignore',
    });
    let spawnError; child.on('error', error => { spawnError = error; });
    t.after(() => child.kill());
    const expected = fs.readFileSync(path.join(root, 'public/css/auth-login.css'));
    for (const headers of [{}, { 'X-Performance-Token': 'isolated-static-test' }]) {
        let response;
        for (let attempt = 0; attempt < 50; attempt++) {
            if (spawnError) throw spawnError;
            try { response = await fetch(`http://127.0.0.1:${port}/css/auth-login.css`, { headers }); break; }
            catch { await new Promise(r => setTimeout(r, 50)); }
        }
        assert.ok(response, 'PHP development server started');
        assert.equal(response.status, 200);
        assert.match(response.headers.get('content-type'), /text\/css/);
        assert.deepEqual(Buffer.from(await response.arrayBuffer()), expected, 'A 200 with an empty body breaks the entire layout');
    }
});
