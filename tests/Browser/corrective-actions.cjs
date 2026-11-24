const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn, execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/car-browser');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
execFileSync('php', ['tests/Fixtures/corrective-actions.php'], { cwd: root, windowsHide: true });
const requests = [], exceptions = [];
const id = index => String(index).padStart(24, '0');
const rows = Array.from({ length: 12 }, (_, index) => ({
    _id: id(index + 1), checkCode: `TEST-CAR-${index + 1}`, auditItem: `Maintain the inspection register ${index + 1}`,
    finding: 'An inspection record is missing.', auditorRemarks: 'Original register inspected on site.',
    departmentId: 'engineering', departmentName: 'Engineering', auditeeName: 'Responsible Auditee', auditorName: 'Assigned Auditor',
    referenceStandard: 'ISO 9001 clause 9.2', correctiveStatus: 'in_progress', displayStatus: 'awaiting_verification', statusLabel: 'Awaiting Verification',
    createdAt: '2026-11-01T10:00:00Z', dueDate: '2026-11-20T10:00:00Z', allowedTransitions: ['closed'],
    evidence: [{ id: id(99), name: 'Inspection evidence.pdf', canDownload: true }],
    responses: [{ method: 'physical-original', notes: 'Signed original available for inspection', availability: 'available', at: '2026-11-03T10:00:00Z', actor: 'Responsible Auditee' }],
    timeline: [
        { label: 'Open', kind: 'status', state: 'completed', at: '2026-11-01T10:00:00Z', actor: 'Assigned Auditor', role: 'Auditor' },
        { label: 'Returned for revision', kind: 'status', state: 'completed', returned: true, at: '2026-11-02T10:00:00Z', actor: 'Assigned Auditor', remarks: 'Provide the signed original' },
        { label: 'Corrective evidence resubmitted — Awaiting Verification', kind: 'evidence', state: 'current', at: '2026-11-03T10:00:00Z', actor: 'Responsible Auditee' },
        { label: 'Awaiting auditor verification', kind: 'upcoming', state: 'upcoming', at: null, actor: null },
    ],
}));
let origin;
const json = (response, value) => { response.writeHead(200, { 'Content-Type': 'application/json' }); response.end(JSON.stringify(value)); };
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname.startsWith('/api/')) requests.push({ path: url.pathname, method: request.method, params: Object.fromEntries(url.searchParams) });
    if (url.pathname === '/api/v1/compliance-checks') {
        const filtered = rows.filter(row => (!url.searchParams.get('search') || row.auditItem.includes(url.searchParams.get('search')))
            && (!url.searchParams.get('departmentId') || row.departmentId === url.searchParams.get('departmentId'))
            && (!url.searchParams.get('status') || row.displayStatus === url.searchParams.get('status')));
        const page = Math.min(Number(url.searchParams.get('page') || 1), Math.max(1, Math.ceil(filtered.length / 10)));
        return json(response, { data: filtered.slice((page - 1) * 10, page * 10), summary: { total: rows.length, inProgress: 0, awaitingVerification: rows.filter(row => row.displayStatus === 'awaiting_verification').length, closed: rows.filter(row => row.correctiveStatus === 'closed').length }, departments: [{ id: 'engineering', name: 'Engineering' }], meta: { total: filtered.length, currentPage: page, lastPage: Math.max(1, Math.ceil(filtered.length / 10)) } });
    }
    const match = /^\/api\/v1\/compliance-checks\/([a-f0-9]+)(\/status)?$/.exec(url.pathname);
    if (match) {
        const row = rows.find(row => row._id === match[1]);
        if (request.method === 'PATCH') {
            let body = ''; request.on('data', data => { body += data; }); request.on('end', () => {
                const data = JSON.parse(body); assert.ok(data.remarks); assert.equal(data.correctiveStatus, 'closed');
                row.correctiveStatus = row.displayStatus = 'closed'; row.statusLabel = 'Closed'; row.allowedTransitions = ['open'];
                json(response, { data: row });
            }); return;
        }
        return json(response, { data: row });
    }
    if (url.pathname === `/api/v1/documents/${id(99)}/download`) { response.writeHead(200, { 'Content-Type': 'application/pdf' }); return response.end('%PDF-1.4\nBrowser preview fixture'); }
    if (url.pathname === '/api/v1/notifications') return json(response, { data: [], meta: { total: 0, unreadCount: 0 } });
    if (url.pathname === '/') { response.writeHead(200, { 'Content-Type': 'text/html' }); return response.end(fs.readFileSync(path.join(directory, 'index.html'), 'utf8').replaceAll('http://fixture.local', origin).replaceAll('https://fixture.local', origin)); }
    const file = path.resolve(root, 'public', `.${decodeURIComponent(url.pathname)}`);
    if (!file.startsWith(path.join(root, 'public') + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) return response.writeHead(404).end();
    response.setHeader('Content-Type', ({ '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2' })[path.extname(file)] || 'application/octet-stream');
    response.end(fs.readFileSync(file));
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); origin = `http://127.0.0.1:${server.address().port}`;
    const profile = fs.mkdtempSync(path.join(directory, 'edge-'));
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let socket;
    try {
        let version;
        for (let i = 0; i < 60; i++) { try { const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0]; version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await pause(100); } }
        assert.ok(version, 'Edge started'); socket = new WebSocket(version.webSocketDebuggerUrl);
        await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
        let sequence = 0; const pending = new Map();
        socket.addEventListener('message', ({ data }) => { const message = JSON.parse(data); if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails); pending.get(message.id)?.(message); });
        const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => { const requestId = ++sequence; const timer = setTimeout(() => reject(Error(`Timed out: ${method}`)), 15000); pending.set(requestId, message => { clearTimeout(timer); pending.delete(requestId); message.error ? reject(Error(JSON.stringify(message.error))) : resolve(message.result); }); socket.send(JSON.stringify({ id: requestId, method, params, sessionId })); });
        const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        await send('Page.enable', {}, sessionId); await send('Runtime.enable', {}, sessionId);
        const evaluate = async expression => { const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, sessionId); if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails)); return result.result.value; };
        const waitFor = async expression => { for (let i = 0; i < 120; i++) { if (await evaluate(expression)) return; await pause(50); } throw Error(`Timed out: ${expression}`); };
        const app = `Alpine.$data(document.querySelector('.car-page'))`;
        for (const width of [1440, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 960, deviceScaleFactor: 1, mobile: width < 600 }, sessionId);
            await send('Page.navigate', { url: origin }, sessionId);
            await waitFor(`window.Alpine && document.querySelectorAll('.car-record').length === 10 && !${app}.loading`);
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, `List ${width}px fits`);
            assert.equal(await evaluate("document.querySelector('.car-record .car-status').getAttribute('data-status')"), 'awaiting_verification');
            assert.equal(await evaluate("document.querySelectorAll('.car-record select').length"), 0);
            await evaluate("document.querySelector('.car-record button').focus(); document.querySelector('.car-record button').click()");
            await waitFor(`${app}.detail && !${app}.detailLoading && document.querySelector('.car-dialog').open`);
            assert.equal(await evaluate("document.querySelectorAll('.car-timeline [data-state=returned]').length"), 1);
            assert.equal(await evaluate("document.querySelectorAll('.car-timeline [data-state=current]').length"), 1);
            assert.equal(await evaluate("document.querySelectorAll('.car-timeline [data-state=upcoming]').length"), 1);
            assert.equal(await evaluate("(()=>{const d=document.querySelector('.car-dialog');const r=d.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight&&d.scrollWidth<=d.clientWidth})()"), true, `Modal ${width}px fits`);
            assert.equal(await evaluate("document.querySelector('.car-dialog').textContent.includes('Signed original available for inspection')"), true);
            if ([1440, 390].includes(width)) { const shot = await send('Page.captureScreenshot', { format: 'png' }, sessionId); fs.writeFileSync(path.join(directory, `details-${width}.png`), Buffer.from(shot.data, 'base64')); }
            await evaluate("document.querySelector('.car-close').click()");
            await waitFor("!document.querySelector('.car-dialog').open");
            assert.equal(await evaluate("document.activeElement === document.querySelector('.car-record button')"), true, 'Closing details restores focus to its record');
        }
        await evaluate(`${app}.load(2)`); await waitFor(`${app}.page === 2 && !${app}.loading`);
        assert.equal(await evaluate("document.querySelectorAll('.car-record').length"), 2);
        await evaluate(`${app}.search='register 12'; ${app}.departmentId='engineering'; ${app}.status='awaiting_verification'; ${app}.load(1)`);
        await waitFor(`${app}.rows.length === 1 && !${app}.loading`);
        assert.equal(requests.filter(request => request.path === '/api/v1/compliance-checks').at(-1).params.departmentId, 'engineering');
        await evaluate("document.querySelector('.car-record button').click()"); await waitFor(`${app}.detail && !${app}.detailLoading`);
        await evaluate(`${app}.remarks='Verified original inspection register'; ${app}.submitTransition()`);
        await waitFor(`${app}.detail?.correctiveStatus === 'closed' && !${app}.saving`);
        assert.equal(requests.filter(request => request.method === 'PATCH').length, 1);
        assert.equal(await evaluate(`${app}.rows.length`), 0, 'Closed record leaves Awaiting Verification filter');
        await evaluate("document.querySelector('.car-files [data-evidence-preview]').click()");
        await waitFor("document.querySelector('#evidence-preview-modal').open");
        assert.equal(await evaluate("document.querySelector('.car-dialog').open"), true, 'Preview preserves CAR details');
        assert.deepEqual(exceptions, [], 'No JavaScript exceptions');
        console.log('Passed CAR browser checks: cards, read-only badges, details, timeline, pagination, filters, verification, evidence preview, 1440/768/390/320px.');
        await send('Browser.close');
    } finally { socket?.close(); browser.kill(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
