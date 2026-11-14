// Uses HTML rendered from isolated MongoDB integration fixtures. No live writes.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/assignment-ui');
const publicRoot = path.join(root, 'public');
const planFixtures = path.join(root, 'storage/framework/testing/plan-ui');
let planMode = 'preparer';
const manifest = JSON.parse(fs.readFileSync(path.join(publicRoot, 'build/manifest.json')));
const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    if (req.method !== 'GET') { res.writeHead(405); res.end('Fixture server permits reads only'); return; }
    if (url.pathname === '/api/v1/roles' || url.pathname === '/api/v1/audit-plans') {
        const file = url.pathname.endsWith('/roles') ? 'roles.json' : planMode === 'approver' ? 'approval.json' : 'preparer.json';
        res.setHeader('Content-Type', 'application/json'); res.end(fs.readFileSync(path.join(planFixtures, file))); return;
    }
    if (url.pathname.startsWith('/api/')) {
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify({ data: [], meta: { unread: 0, total: 0, lastPage: 1 }, unreadCount: 0 })); return;
    }
    if (/^\/audits\/[a-f0-9]{24}$/i.test(url.pathname)) {
        res.setHeader('Content-Type', 'text/html'); res.end(fs.readFileSync(path.join(fixtures, 'review.html'))); return;
    }
    if (['/assignments.html', '/reviews.html', '/roles.html', '/plans.html', '/approvals.html'].includes(url.pathname)) {
        const isPlanPage = ['/roles.html', '/plans.html', '/approvals.html'].includes(url.pathname);
        if (isPlanPage) planMode = url.pathname === '/approvals.html' ? 'approver' : 'preparer';
        let html = fs.readFileSync(path.join(isPlanPage ? planFixtures : fixtures, url.pathname.slice(1)), 'utf8');
        html = html.replace(/https?:\/\/[^/"'\s]+/g, '')
            .replace(/\/build\/assets\/app-[^"\s]+\.js/g, '/build/' + manifest['resources/js/app.js'].file)
            .replace(/\/build\/assets\/app-[^"\s]+\.css/g, '/build/' + manifest['resources/css/app.css'].file);
        if (!html.includes('type="module"')) {
            html = html.replace('</head>', `<link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}"><script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></head>`);
        }
        res.setHeader('Content-Type', 'text/html'); res.end(html); return;
    }
    const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(publicRoot + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
    res.setHeader('Content-Type', ({ '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2' })[path.extname(file)] || 'application/octet-stream');
    res.end(fs.readFileSync(file));
});
(async () => {
    await new Promise(resolve => server.listen(9350, '127.0.0.1', resolve));
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--remote-debugging-port=9351', `--user-data-dir=${path.join(fixtures, 'edge')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    try {
        let tabs;
        for (let i = 0; i < 50; i++) { try { tabs = await (await fetch('http://127.0.0.1:9351/json')).json(); break; } catch { await new Promise(r => setTimeout(r, 200)); } }
        assert.ok(tabs, 'Browser starts');
        ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let seq = 0;
        const pending = new Map(), errors = [];
        ws.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.id) { pending.get(data.id)?.(data); pending.delete(data.id); }
            if (data.method === 'Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.text + ': ' + data.params.exceptionDetails.exception?.description);
        });
        const send = (method, params = {}) => new Promise(resolve => { const id = ++seq; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params })); });
        const evaluate = async expression => {
            const data = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
            if (data.result?.exceptionDetails) throw Error(data.result.exceptionDetails.exception?.description || 'Browser evaluation failed');
            return data.result?.result?.value;
        };
        const waitFor = async expression => {
            for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await new Promise(r => setTimeout(r, 100)); }
            throw Error('Timed out: ' + expression);
        };
        await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable');
        await send('Network.setBlockedURLs', { urls: ['https://*'] });
        for (const width of [1440, 768, 390]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: 'http://127.0.0.1:9350/assignments.html' });
            await waitFor('!!window.Alpine && !document.documentElement.hidden && !!document.querySelector("[data-new-assignment]")');
            await new Promise(r => setTimeout(r, 300));
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false, `Register fits ${width}px`);
            assert.equal(await evaluate('document.querySelectorAll("a[data-atl-review]").length'), 0);
            assert.equal(await evaluate('document.querySelector(".header-page-subtitle").textContent.trim()'), 'Assign auditors to departments and offices based on authorized audit plans.');
            await evaluate('document.querySelector("[data-new-assignment]").click()');
            await waitFor('document.querySelector("#audit-assignment-dialog").open');
            assert.ok(await evaluate('(()=>{const r=document.querySelector("#audit-assignment-dialog").getBoundingClientRect();return r.left>=0 && r.right<=innerWidth && r.top>=0 && r.bottom<=innerHeight})()'), 'Assignment modal fits');
            assert.equal(await evaluate('document.querySelector(".assignment-modal-form").checkValidity()'), false);
            await evaluate('(()=>{const p=document.querySelector("select[name=planId]");p.selectedIndex=1;p.dispatchEvent(new Event("change",{bubbles:true}));const u=document.querySelector("select[name=departmentId]");u.value=[...u.options].find(o=>o.value&&!o.disabled).value;u.dispatchEvent(new Event("change",{bubbles:true}))})()');
            assert.equal(await evaluate('document.querySelector("[data-plan-scope]").value'), 'Process');
            assert.equal(await evaluate('[...document.querySelector("select[name=auditorId]").options].filter(o=>o.value&&!o.disabled).length'), 1);
            assert.equal(await evaluate('document.querySelector("select[name=auditorId]").disabled'), false);
            await evaluate('(()=>{const o=[...document.querySelector("select[name=auditorId]").options].find(o=>o.value&&!o.disabled);window.eligibleAuditorOption=o;window.eligibleAuditorUnits=o.dataset.units;o.dataset.units="[]";document.querySelector("select[name=departmentId]").dispatchEvent(new Event("change",{bubbles:true}))})()');
            assert.equal(await evaluate('document.querySelector("select[name=auditorId]").disabled'), true);
            assert.equal(await evaluate('document.querySelector("[data-save-assignment]").disabled'), true);
            assert.ok(await evaluate('document.querySelector("[data-assignment-auditor-help]").textContent.includes("A Super Admin must authorize")'));
            await evaluate('window.eligibleAuditorOption.dataset.units=window.eligibleAuditorUnits;document.querySelector("select[name=departmentId]").dispatchEvent(new Event("change",{bubbles:true}))');
            assert.equal(await evaluate('document.querySelector("select[name=auditorId]").disabled'), false);
            assert.equal(await evaluate('document.querySelector("[name=schedule]").min'), '2020-01-01');
            const shot = await send('Page.captureScreenshot', { format: 'png' });
            fs.writeFileSync(path.join(fixtures, `assignment-modal-${width}.png`), Buffer.from(shot.result.data, 'base64'));
            await evaluate('document.querySelector("[data-close-assignment]").click()');
            await waitFor('document.activeElement.matches("[data-new-assignment]")');
            await evaluate('document.querySelector("[data-edit-assignment]").click()');
            assert.equal(await evaluate('document.querySelector("[name=planId]").disabled && document.querySelector("[name=departmentId]").disabled'), true);
            assert.equal(await evaluate('document.querySelector("[name=changeReason]").required'), true);
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await waitFor('!document.querySelector("#audit-assignment-dialog").open');
            await send('Page.navigate', { url: 'http://127.0.0.1:9350/reviews.html' });
            await waitFor('!!document.querySelector("a[data-atl-review]") && !!window.Alpine && !document.documentElement.hidden');
            await new Promise(r => setTimeout(r, 300));
            await evaluate('document.querySelector("a[data-atl-review]").click()');
            await waitFor('!!document.querySelector("[data-atl-review-form]")');
            assert.ok(await evaluate('(()=>{const r=document.querySelector("#atl-review-dialog").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()'), 'Review modal fits');
            assert.equal(await evaluate('!!document.querySelector("#atl-review-dialog [name=result]")'), false);
            console.log(`${width}px: register, header subtitle, creation/amendment modal, plan scope, eligible auditor, focus, Escape and review modal passed`);
            if (fs.existsSync(planFixtures)) {
                await send('Page.navigate', { url: 'http://127.0.0.1:9350/roles.html' });
                await waitFor('document.querySelector("[x-data=auditPlanPermissions]")?._x_dataStack?.[0]?.loading === false');
                assert.equal(await evaluate('document.querySelector("[x-data=auditPlanPermissions]")._x_dataStack[0].enabled'), false, 'Super Admin wildcard does not grant approval');
                await evaluate('(()=>{const c=document.querySelector("[x-data=auditPlanPermissions]")._x_dataStack[0];c.selected=3;c.choose()})()');
                assert.equal(await evaluate('document.querySelector("[x-data=auditPlanPermissions]")._x_dataStack[0].enabled'), true, 'Explicit role grant is shown');
                assert.equal(await evaluate('document.querySelector(".plan-permission-form").checkValidity()'), false, 'Reason and current password are required');
                assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
                await send('Page.navigate', { url: 'http://127.0.0.1:9350/plans.html' });
                await waitFor('document.querySelector("[x-data=auditPlans]")?._x_dataStack?.[0]?.loading === false');
                await evaluate('document.querySelector("[x-data=auditPlans]")._x_dataStack[0].edit()');
                await waitFor('document.querySelector(".plan-editor").open');
                assert.ok(await evaluate('(()=>{const r=document.querySelector(".plan-editor").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()'), 'Plan editor fits');
                assert.equal(await evaluate('document.querySelector(".plan-editor form").checkValidity()'), false);
                await send('Page.navigate', { url: 'http://127.0.0.1:9350/approvals.html' });
                await waitFor('document.querySelector("[x-data=auditPlans]")?._x_dataStack?.[0]?.loading === false');
                assert.equal(await evaluate('document.querySelector("[x-data=auditPlans]")._x_dataStack[0].data.canApprove'), true);
                assert.ok(await evaluate('[...document.querySelectorAll("button")].some(b=>b.textContent.trim()==="Approve Plan"&&b.getClientRects().length>0)'), 'Authorized approval action is visible');
                assert.equal(await evaluate('document.querySelector(".plan-action-form").checkValidity()'), false, 'Decision remarks required');
                assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
                console.log(`${width}px: explicit role permission controls, plan editor and approval queue passed`);
            }
        }
        assert.deepEqual(errors, [], 'No browser runtime errors');
    } finally {
        if (ws?.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ id: 999999, method: 'Browser.close' }));
        ws?.close(); browser.kill(); server.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
