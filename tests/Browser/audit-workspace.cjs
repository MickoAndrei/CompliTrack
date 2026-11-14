// Rendered isolated MongoDB fixtures; this server never writes application data.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/workspace-ui');
const publicRoot = path.join(root, 'public');
const manifest = JSON.parse(fs.readFileSync(path.join(publicRoot, 'build/manifest.json')));
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    if (req.method !== 'GET') { res.writeHead(405); res.end(); return; }
    if (url.pathname.startsWith('/api/')) { res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify({ data: [], meta: { unread: 0, total: 0 }, unreadCount: 0 })); return; }
    if (['/draft.html', '/ready.html', '/submitted.html'].includes(url.pathname)) {
        let html = fs.readFileSync(path.join(fixtures, url.pathname.slice(1)), 'utf8');
        html = html.replace(/https?:\/\/[^/"'\s]+/g, '')
            .replace(/\/build\/assets\/app-[^"\s]+\.js/g, '/build/' + manifest['resources/js/app.js'].file)
            .replace(/\/build\/assets\/app-[^"\s]+\.css/g, '/build/' + manifest['resources/css/app.css'].file);
        if (!html.includes('type="module"')) html = html.replace('</head>', `<link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}"><script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></head>`);
        res.setHeader('Content-Type', 'text/html'); res.end(html); return;
    }
    const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(publicRoot + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
    res.setHeader('Content-Type', ({ '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2' })[path.extname(file)] || 'application/octet-stream'); res.end(fs.readFileSync(file));
});
(async () => {
    await new Promise(resolve => server.listen(9360, '127.0.0.1', resolve));
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--remote-debugging-port=9361', `--user-data-dir=${path.join(fixtures, 'edge')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    try {
        let tabs;
        for (let i = 0; i < 50; i++) { try { tabs = await (await fetch('http://127.0.0.1:9361/json')).json(); break; } catch { await new Promise(r => setTimeout(r, 200)); } }
        assert.ok(tabs, 'Browser starts');
        ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let seq = 0; const pending = new Map(), errors = [];
        ws.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.id) { pending.get(data.id)?.(data); pending.delete(data.id); }
            if (data.method === 'Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.exception?.description || data.params.exceptionDetails.text);
        });
        const send = (method, params = {}) => new Promise(resolve => { const id = ++seq; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params })); });
        const evaluate = async expression => {
            const data = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
            if (data.result?.exceptionDetails) throw Error(data.result.exceptionDetails.exception?.description || 'Browser evaluation failed');
            return data.result?.result?.value;
        };
        const waitFor = async expression => { for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await new Promise(r => setTimeout(r, 100)); } throw Error('Timed out: ' + expression + '\nBrowser errors: ' + JSON.stringify(errors)); };
        const component = 'document.querySelector(".audit-workspace")._x_dataStack[0]';
        const setField = (selector, value) => evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}))})()`);
        await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable');
        await send('Network.setBlockedURLs', { urls: ['https://*'] });
        for (const width of [1440, 768, 390]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 960, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: 'http://127.0.0.1:9360/draft.html#checklist/0/evaluation' });
            await waitFor(`!!document.querySelector('.audit-workspace')?._x_dataStack`);
            await new Promise(r => setTimeout(r, 200));
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false, `Workspace fits ${width}px`);
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".aw-checklist")).gridTemplateColumns.split(" ").length'), width < 700 ? 1 : 2);
            await evaluate(`document.querySelector('#aw-panel-checklist .aw-section-heading button').click()`);
            await waitFor('document.querySelector("#workspace-question-dialog").open');
            assert.equal(await evaluate('document.activeElement.name'), 'question', 'Modal focuses question');
            assert.equal(await evaluate('document.querySelector("#workspace-question-dialog form").checkValidity()'), false);
            await evaluate('[...document.querySelectorAll("#workspace-question-dialog button")].at(-1).focus()');
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            assert.equal(await evaluate('document.querySelector("#workspace-question-dialog").contains(document.activeElement)'), true, 'Modal traps keyboard focus');
            assert.ok(await evaluate('(()=>{const r=document.querySelector("#workspace-question-dialog").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()'), 'Modal fits');
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await waitFor('!document.querySelector("#workspace-question-dialog").open');
            await evaluate(`${component}.prepare(0, "Does the team's process follow standards?", 'ISO 9001:2015 9.2')`);
            assert.equal(await evaluate('document.querySelector("#workspace-question-dialog textarea").value'), "Does the team's process follow standards?");
            await evaluate('document.querySelector("#workspace-question-dialog button").click()');
            await evaluate('document.querySelector("#aw-tab-overview").focus()');
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowRight', code: 'ArrowRight', windowsVirtualKeyCode: 39 });
            await waitFor('document.activeElement.id === "aw-tab-checklist"');
            assert.equal(await evaluate('document.querySelector("#aw-tab-checklist").getAttribute("aria-selected")'), 'true');
            await setField('input[type=search]', 'no matching question');
            await waitFor('[...document.querySelectorAll(".aw-item")].filter(e=>e.getClientRects().length).length === 0');
            assert.equal(await evaluate('[...document.querySelectorAll(".aw-item")].filter(e=>e.getClientRects().length).length'), 0);
            await setField('input[type=search]', '');
            await evaluate('document.querySelector("#aw-tab-interviews").click()');
            await waitFor('document.querySelector("#aw-panel-interviews").getClientRects().length');
            await evaluate('document.querySelector("#aw-panel-interviews .aw-section-heading button").click()');
            await waitFor('document.querySelector("#workspace-interview-dialog").open');
            assert.equal(await evaluate('document.activeElement.name'), 'auditeeId');
            assert.equal(await evaluate('document.querySelector("#workspace-interview-dialog form").checkValidity()'), false);
            await evaluate('document.querySelector("#workspace-interview-dialog button").click()');
            await evaluate('document.querySelector("#aw-tab-requests").click()');
            await waitFor('document.querySelector("#aw-panel-requests").getClientRects().length');
            await evaluate('document.querySelector("#aw-panel-requests .aw-section-heading button").click()');
            await waitFor('document.querySelector("#workspace-request-dialog").open');
            assert.equal(await evaluate('document.activeElement.name'), 'itemId');
            assert.equal(await evaluate('document.querySelector("#workspace-request-dialog form").checkValidity()'), false);
            await evaluate('document.querySelector("#workspace-request-dialog button").click()');
            await evaluate('document.querySelector("#aw-tab-checklist").click()');
            await new Promise(r => setTimeout(r, 100));
            const shot = await send('Page.captureScreenshot', { format: 'png' }); fs.writeFileSync(path.join(fixtures, `draft-${width}.png`), Buffer.from(shot.result.data, 'base64'));

            await send('Page.navigate', { url: 'http://127.0.0.1:9360/ready.html#checklist/0/evaluation' });
            await waitFor(`!!document.querySelector('.audit-workspace')?._x_dataStack`);
            await setField('#aw-item-0 textarea[name=remarks]', 'Unsaved objective observations');
            await evaluate('document.querySelector("#aw-tab-interviews").click()');
            await evaluate('document.querySelector("#aw-tab-checklist").click()');
            assert.equal(await evaluate('document.querySelector("#aw-item-0 textarea[name=remarks]").value'), 'Unsaved objective observations');
            assert.equal(await evaluate(`${component}.dirty.size`), 1);
            assert.equal(await evaluate('window.dispatchEvent(new Event("beforeunload", { cancelable: true }))'), false, 'Unsaved changes guard page departure');
            await evaluate(`${component}.go('checklist',1,'evaluation')`);
            await waitFor('document.querySelector("#aw-item-1").getClientRects().length');
            assert.equal(await evaluate('document.querySelector("#aw-item-1 fieldset").disabled'), true, 'Evidence required');
            await evaluate('history.back()');
            await waitFor(`${component}.selected === 0`);
            await evaluate(`${component}.go('review')`);
            await waitFor('document.querySelector("#aw-panel-review").getClientRects().length');
            await waitFor('!!document.querySelector(".aw-history-scroll").style.maxHeight');
            assert.equal(await evaluate('document.querySelectorAll(".aw-event[data-state=current][aria-current=step]").length'), 1, 'One current workflow stage');
            assert.equal(await evaluate('[...document.querySelectorAll(".aw-event[data-state=completed]")].every(e=>e.querySelector(".aw-event-node svg"))'), true, 'Recorded actions have checkmarks');
            assert.ok(await evaluate('document.querySelectorAll(".aw-event[data-state=future]").length > 0'), 'Upcoming workflow stages are shown');
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".aw-history-scroll")).scrollbarWidth'), 'none');
            assert.ok(await evaluate('document.querySelector(".aw-history").getBoundingClientRect().height <= document.querySelector("[data-submission-readiness]").getBoundingClientRect().height + 1'), 'History is capped by readiness height');
            assert.ok(await evaluate('(()=>{const v=document.querySelector(".aw-history-scroll"),t=document.querySelector(".aw-timeline"),n=t.querySelectorAll(".aw-event")[4];return !n||v.clientHeight<=n.getBoundingClientRect().bottom-t.getBoundingClientRect().top+7})()'), 'At most five nodes are visible');
            assert.equal(await evaluate('(()=>{const v=document.querySelector(".aw-history-scroll");v.scrollTop=v.scrollHeight;return v.scrollTop>0})()'), true, 'Remaining nodes are scrollable');
            await evaluate('document.querySelector(".aw-history-scroll").scrollTop=0');
            await evaluate('document.querySelector("#aw-panel-review button[type=button]").click()');
            await waitFor(`${component}.selected === 1 && ${component}.panel === 'evidence'`);
            await evaluate(`${component}.openModal('evidence')`);
            await setField('#workspace-evidence-dialog select[name=method]', 'physical-original');
            await waitFor('document.querySelector("#workspace-evidence-dialog select[name=signature]").getClientRects().length');
            assert.ok(await evaluate('document.querySelector("#workspace-evidence-dialog select[name=signature]").getClientRects().length'));
            // Avoid native confirmation UI while testing retained modal data.
            await evaluate('window.confirm=()=>true');
            await setField('#workspace-evidence-dialog textarea', 'Evidence draft for item 2');
            await evaluate('document.querySelector("#workspace-evidence-dialog button").click()');
            await evaluate('window.confirm=()=>false');
            assert.equal(await evaluate('document.querySelector("#aw-item-0 form").dispatchEvent(new SubmitEvent("submit",{bubbles:true,cancelable:true}))'), false, 'Saving warns about other unsaved forms');
            assert.equal(await evaluate(`${component}.saving`), false);
            await evaluate('window.confirm=()=>true');
            await evaluate(`${component}.openModal('evidence')`);
            assert.equal(await evaluate('document.querySelector("#workspace-evidence-dialog textarea").value'), 'Evidence draft for item 2');
            await evaluate('document.querySelector("#workspace-evidence-dialog button").click()');
            await evaluate(`${component}.go('checklist',0,'evidence'); ${component}.openModal('evidence')`);
            assert.equal(await evaluate('document.querySelector("#workspace-evidence-dialog textarea").value'), '', 'Different evidence item resets after confirmation');
            await evaluate('document.querySelector("#workspace-evidence-dialog button").click()');
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
            await evaluate(`${component}.go('checklist',0,'evaluation'); ${component}.dirty = new Set()`);
            await new Promise(r => setTimeout(r, 150));
            const readyShot = await send('Page.captureScreenshot', { format: 'png' }); fs.writeFileSync(path.join(fixtures, `ready-${width}.png`), Buffer.from(readyShot.result.data, 'base64'));
            await send('Page.navigate', { url: 'http://127.0.0.1:9360/submitted.html#review' });
            await waitFor(`!!document.querySelector('.audit-workspace')?._x_dataStack`);
            assert.equal(await evaluate('document.querySelectorAll(".audit-workspace form").length'), 0, 'Submitted results locked');
            console.log(`${width}px: layout, modals, focus/Escape, keyboard tabs, search, retained remarks, history, evidence gating, readiness links and locks passed`);
        }
        assert.deepEqual(errors, [], 'No browser runtime errors');
    } finally {
        if (ws?.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ id: 999999, method: 'Browser.close' }));
        ws?.close(); browser.kill(); server.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
