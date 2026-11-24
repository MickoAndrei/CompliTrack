// Read-only browser server using HTML generated from isolated MongoDB test records.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/my-audits-ui');
const publicRoot = path.join(root, 'public');
const manifest = JSON.parse(fs.readFileSync(path.join(publicRoot, 'build/manifest.json')));
let failNext = false, listingRequests = 0;
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    if (req.method !== 'GET') { res.writeHead(405); res.end(); return; }
    if (url.pathname.startsWith('/api/')) { res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify({ data: [], meta: { unread: 0, total: 0 }, unreadCount: 0 })); return; }
    if (url.pathname === '/audits' || url.pathname === '/empty.html') {
        listingRequests++;
        if (failNext) { failNext = false; res.writeHead(503); res.end('Unavailable'); return; }
        const isFragment = req.headers['x-requested-with'] === 'XMLHttpRequest';
        const file = url.pathname === '/empty.html' ? 'empty' : isFragment && url.searchParams.get('status') === 'submitted' ? 'submitted' : isFragment && url.searchParams.get('search') === 'nomatch' ? 'none' : 'full';
        let html = fs.readFileSync(path.join(fixtures, file + '.html'), 'utf8');
        html = html.replace(/https?:\/\/[^/"'\s]+/g, '')
            .replace(/\/build\/assets\/app-[^"\s]+\.js/g, '/build/' + manifest['resources/js/app.js'].file)
            .replace(/\/build\/assets\/app-[^"\s]+\.css/g, '/build/' + manifest['resources/css/app.css'].file);
        if (html.includes('</head>') && !html.includes('type="module"')) html = html.replace('</head>', `<link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}"><script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></head>`);
        res.setHeader('Content-Type', 'text/html');
        if (isFragment) setTimeout(() => res.end(html), 250);
        else res.end(html);
        return;
    }
    const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(publicRoot + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
    res.setHeader('Content-Type', ({ '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2' })[path.extname(file)] || 'application/octet-stream'); res.end(fs.readFileSync(file));
});
(async () => {
    await new Promise(resolve => server.listen(9370, '127.0.0.1', resolve));
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--remote-debugging-port=9371', `--user-data-dir=${path.join(fixtures, 'edge')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    try {
        let tabs;
        for (let i = 0; i < 50; i++) { try { tabs = await (await fetch('http://127.0.0.1:9371/json', { signal: AbortSignal.timeout(1000) })).json(); break; } catch { await new Promise(r => setTimeout(r, 200)); } }
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
        const waitFor = async expression => { for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await new Promise(r => setTimeout(r, 100)); } throw Error('Timed out: ' + expression + '\n' + JSON.stringify(errors)); };
        const setField = (name, value, event) => evaluate(`(()=>{const e=document.querySelector('[data-my-audit-filters] [name=${name}]');e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('${event}',{bubbles:true}))})()`);
        await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable');
        await send('Network.setBlockedURLs', { urls: ['https://*'] });
        for (const width of [1440, 900, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 1100, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: 'http://127.0.0.1:9370/audits' });
            await waitFor('!!window.Alpine && !document.documentElement.hidden && !!document.querySelector(".maa-card svg")');
            await new Promise(r => setTimeout(r, 150));
            const initial = listingRequests;
            await new Promise(r => setTimeout(r, 350));
            assert.equal(listingRequests, initial, 'No redundant initial listing request or polling');
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false, `Page fits ${width}px`);
            assert.equal(await evaluate('getComputedStyle(document.documentElement).scrollbarWidth'), 'none', 'Audit page scrollbar is hidden');
            assert.ok(await evaluate('document.querySelector(".maa-summary").getBoundingClientRect().top - document.querySelector(".app-header").getBoundingClientRect().bottom <= 13'), 'Summary cards sit directly below the portal header');
            if (width === 1440) assert.equal(await evaluate('document.documentElement.scrollHeight > innerHeight'), false, 'Desktop audit cards fit without page scrolling');
            assert.equal(await evaluate('document.querySelectorAll(".maa-card").length'), 6);
            assert.ok(await evaluate('parseFloat(getComputedStyle(document.querySelector(".maa-search input")).paddingLeft) >= 38'), 'Search text clears its icon');
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".maa-filters label:nth-child(2)")).marginTop'), '0px', 'Shared form spacing is contained');
            assert.equal(await evaluate('document.querySelector("[data-metric=total]").textContent'), '6');
            assert.equal(await evaluate('document.querySelector("[data-audit-status=submitted]").textContent.includes("Awaiting Audit Team Leader Review")'), true);
            assert.equal(await evaluate('document.querySelector("[data-audit-status=returned]").textContent.includes("Clarify the verification sample")'), true);
            assert.equal(await evaluate('document.querySelector("[data-audit-status=accepted] progress").value'), 1, 'Completion includes nonconformant evaluated items');
            assert.equal(await evaluate('[...document.querySelectorAll(".maa-action")].every(a=>/^\\/audits\\/[a-f0-9]{24}#(review|checklist)$/.test(new URL(a.href).pathname+new URL(a.href).hash))'), true, 'Existing workspace routes reused');
            await evaluate('document.querySelector("[data-my-audit-filters] [name=search]").focus()');
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            assert.equal(await evaluate('document.activeElement.name'), 'departmentId', 'Keyboard filter order');
            const shot = await send('Page.captureScreenshot', { format: 'png' }); fs.writeFileSync(path.join(fixtures, `assigned-${width}.png`), Buffer.from(shot.result.data, 'base64'));
            await evaluate('window.retainedSummary=document.querySelector("[data-my-audit-summary]");window.retainedMetric=document.querySelector("[data-metric=total]");window.retainedFilters=document.querySelector("[data-my-audit-filters]")');
            assert.equal(await evaluate('document.querySelector(".maa-filters [data-my-audit-reset]").textContent'), 'Clear Filters');
            assert.equal(await evaluate('document.querySelectorAll(".maa-filters button, .maa-filters a").length'), 1);
            await setField('status', 'submitted', 'change');
            await waitFor('!document.querySelector("[data-my-audit-loading]").hidden');
            assert.equal(await evaluate('document.querySelector("[data-my-audit-content]").hidden'), true);
            assert.equal(await evaluate('document.querySelector(".maa-pills").getBoundingClientRect().height > 0 && getComputedStyle(document.querySelector(".maa-pills")).pointerEvents !== "none"'), true, 'Status tabs stay visible and interactive during loading');
            assert.equal(await evaluate('document.querySelectorAll(".maa-skeleton-card").length'), 2);
            await evaluate('document.querySelector(\'.maa-pills a[href*="status=submitted"]\').click()');
            assert.equal(await evaluate('document.querySelector(".maa-pills [aria-current=page]").textContent'), 'Submitted', 'Clicked tab highlights before the response');
            assert.equal(await evaluate('document.querySelector("[data-my-audit-loading]").hidden'), true, 'Tab transition leads skeleton loading');
            await waitFor('document.querySelectorAll(".maa-card").length === 1 && document.querySelector("[data-my-audit-results]").getAttribute("aria-busy") === "false"');
            assert.equal(await evaluate('new URL(location.href).searchParams.get("status")'), 'submitted');
            assert.equal(await evaluate('window.retainedSummary===document.querySelector("[data-my-audit-summary]") && window.retainedMetric===document.querySelector("[data-metric=total]") && window.retainedFilters===document.querySelector("[data-my-audit-filters]")'), true, 'Only My Audits results are replaced');
            await evaluate('document.querySelector(".maa-filters [data-my-audit-reset]").click()');
            await waitFor('document.querySelectorAll(".maa-card").length === 6 && document.querySelector("[name=status]").value === ""');
            assert.equal(await evaluate('new URL(location.href).searchParams.has("status")'), false);
            await setField('status', 'submitted', 'change');
            await waitFor('document.querySelectorAll(".maa-card").length === 1 && document.querySelector("[data-my-audit-results]").getAttribute("aria-busy") === "false"');
            assert.equal(await evaluate('document.querySelector("[data-metric=total]").textContent'), '6');
            await evaluate('history.back()');
            await waitFor('document.querySelectorAll(".maa-card").length === 6 && document.querySelector("[name=status]").value === ""');
            await setField('search', 'nomatch', 'input');
            await waitFor('document.querySelector(".maa-empty")?.textContent.includes("No matching audits")');
            await evaluate('document.querySelector(".maa-empty [data-my-audit-reset]").click()');
            await waitFor('document.querySelectorAll(".maa-card").length === 6');
            failNext = true;
            await setField('status', 'submitted', 'change');
            await waitFor('!document.querySelector("[data-my-audit-error]").hidden');
            assert.equal(await evaluate('document.querySelectorAll(".maa-card").length'), 6, 'Failure keeps previous cards');
            await evaluate('document.querySelector("[data-my-audit-retry]").click()');
            await waitFor('document.querySelectorAll(".maa-card").length === 1 && document.querySelector("[data-my-audit-error]").hidden');
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
            console.log(`${width}px: compact cards, metrics, state actions, privacy, responsive layout, keyboard, filters, history, empty/error/retry and request reuse passed`);
        }
        await send('Page.navigate', { url: 'http://127.0.0.1:9370/empty.html' });
        await waitFor('!!document.querySelector(".maa-empty") && !document.documentElement.hidden');
        assert.equal(await evaluate('document.querySelector(".maa-empty").textContent.includes("No Assigned Audits Yet")'), true);
        assert.deepEqual(errors, [], 'No runtime exceptions');
    } finally {
        if (ws?.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ id: 999999, method: 'Browser.close' }));
        ws?.close(); browser.kill(); server.closeAllConnections(); server.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
