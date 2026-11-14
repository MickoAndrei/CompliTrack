// Renders isolated MongoDB test output. Does not authenticate or mutate application data.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/car-register');
const script = fs.readFileSync(path.join(root, 'resources/js/car-history.js'), 'utf8')
    .replace("import { onDocumentReady } from './dom-ready';", 'const onDocumentReady = fn => document.addEventListener("DOMContentLoaded", fn);');
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1:9374');
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    if (url.pathname === '/admin/compliance-records' && url.searchParams.has('history')) {
        res.end(fs.readFileSync(path.join(directory, 'detail.html'))); return;
    }
    if (url.pathname === '/' || url.pathname === '/admin/compliance-records') {
        const html = fs.readFileSync(path.join(directory, 'register.html'), 'utf8')
            .replace(/https?:\/\/[^/"\s]+\//g, '/')
            .replace('</body>', `<script>${script}</script></body>`);
        res.end(html); return;
    }
    if (url.pathname === '/export') { res.end(fs.readFileSync(path.join(directory, 'export.html'))); return; }
    const file = path.resolve(root, 'public', '.' + url.pathname);
    if (!file.startsWith(path.join(root, 'public') + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
    res.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : 'application/octet-stream');
    res.end(fs.readFileSync(file));
});
const delay = ms => new Promise(r => setTimeout(r, ms));
(async () => {
    let browser, ws;
    const timer = setTimeout(() => { console.error('CAR browser check timed out'); browser?.kill(); server.close(); process.exit(1); }, 60000);
    try {
        assert.ok(fs.existsSync(path.join(directory, 'register.html')), 'Generate fixtures with WRITE_CAR_UI_FIXTURES=1 and CorrectiveActionsMongoTest');
        await new Promise(r => server.listen(9374, '127.0.0.1', r));
        browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--remote-debugging-port=9375', `--user-data-dir=${path.join(directory, 'edge')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let tabs;
        for (let i = 0; i < 60; i++) { try { tabs = await (await fetch('http://127.0.0.1:9375/json')).json(); break; } catch { await delay(200); } }
        assert.ok(tabs);
        ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
        await new Promise(r => ws.addEventListener('open', r, { once: true }));
        let seq = 0; const pending = new Map();
        ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } });
        const send = (method, params = {}) => new Promise(r => { const id = ++seq; pending.set(id, r); ws.send(JSON.stringify({ id, method, params })); });
        const evaluate = async expression => { const r = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (r.result.exceptionDetails) throw Error(r.result.exceptionDetails.text); return r.result.result.value; };
        const wait = async expression => { for (let i = 0; i < 80; i++) { if (await evaluate(expression)) return; await delay(50); } throw Error('Timed out: ' + expression); };
        await send('Page.enable');
        for (const width of [1440, 1024, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: 'http://127.0.0.1:9374/' });
            await wait('document.querySelectorAll(".car-table tbody tr").length === 2');
            assert.equal(await evaluate('document.querySelectorAll(".car-metrics article").length'), 5);
            assert.equal(await evaluate('document.querySelectorAll(".car-metric-icon").length'), 5);
            assert.equal(await evaluate('!!document.querySelector(".car-panel .car-export svg")'), true);
            assert.equal(await evaluate('document.querySelector(".car-toolbar")'), null);
            assert.equal(await evaluate('document.querySelector(".car-reset").textContent'), 'Reset Filter');
            assert.equal(await evaluate('document.querySelector(".car-pagination > p").textContent'), '2 records');
            assert.equal(await evaluate('document.querySelector(".car-page-position").textContent'), 'Page 1 of 1');
            assert.equal(await evaluate('document.querySelectorAll(".car-pagination [aria-disabled=true]").length'), 2);
            assert.equal(await evaluate(`(() => { let submits=0; const form=document.querySelector('.car-filters'); form.addEventListener('submit', event=>{event.preventDefault();submits++}); for(const select of form.querySelectorAll('select')) select.dispatchEvent(new Event('change',{bubbles:true})); return submits === form.querySelectorAll('select').length; })()`), true, 'Every dropdown filters automatically');
            assert.equal(await evaluate('document.querySelector(".car-register").scrollWidth <= document.querySelector(".car-register").clientWidth + 1'), true, 'Register fits at ' + width);
            await evaluate(`window.carOriginalFetch = window.fetch; window.fetch = async (...args) => { if (String(args[0]).includes('history=1')) await new Promise(resolve => window.releaseCarDetails = resolve); return window.carOriginalFetch(...args); }; document.querySelector('[data-car-history]').click();`);
            await wait('!!window.releaseCarDetails && !!document.querySelector(".car-dialog-body .car-detail-skeleton")');
            assert.equal(await evaluate('document.querySelector("[data-car-history-body]").getAttribute("aria-busy")'), 'true');
            assert.equal(await evaluate('document.querySelector("[data-car-view-history]").disabled'), true);
            assert.equal(await evaluate('document.querySelectorAll(".car-dialog-body .car-skeleton-overview-field").length'), 8);
            if (width === 1440 || width === 390) {
                const loading = await send('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(directory, `details-loading-${width}.png`), Buffer.from(loading.result.data, 'base64'));
            }
            await evaluate('window.releaseCarDetails(); window.fetch = window.carOriginalFetch;');
            await wait('document.querySelector("#car-history-dialog").open && new Set([...document.querySelectorAll("[data-car-field]")].map(el=>el.dataset.carField)).size === 14');
            assert.equal(await evaluate('document.querySelector(".car-dialog-body .car-detail-skeleton")'), null);
            assert.equal(await evaluate('document.querySelector("[data-car-history-body]").getAttribute("aria-busy")'), 'false');
            assert.equal(await evaluate('document.querySelectorAll(".car-overview-fields > div").length'), 8);
            assert.equal(await evaluate('document.querySelector("#car-tab-issue").getAttribute("aria-selected")'), 'true');
            assert.equal(await evaluate('document.querySelector("#car-dialog-title").textContent.includes("CAR-2026-001")'), true);
            assert.equal(await evaluate('document.querySelector("#car-history-dialog").scrollWidth <= document.querySelector("#car-history-dialog").clientWidth + 1'), true, 'Modal fits at ' + width);
            assert.equal(await evaluate('Math.abs(document.querySelector("#car-history-dialog").getBoundingClientRect().left + document.querySelector("#car-history-dialog").getBoundingClientRect().width / 2 - innerWidth / 2) < 2'), true, 'Modal is centered');
            if (width === 1440 || width === 390) {
                const modal = await send('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(directory, `details-${width}.png`), Buffer.from(modal.result.data, 'base64'));
            }
            await evaluate('document.querySelector("#car-tab-verification").click()');
            assert.equal(await evaluate('!document.querySelector("#car-panel-verification").hidden && document.querySelector("#car-panel-issue").hidden'), true);
            await evaluate('document.querySelector("[data-car-view-history]").click()');
            assert.equal(await evaluate('!document.querySelector("#car-panel-history").hidden && document.querySelector(".car-result-section").hidden'), true);
            await evaluate('document.querySelector("#car-tab-history").dispatchEvent(new KeyboardEvent("keydown",{key:"Home",bubbles:true}))');
            assert.equal(await evaluate('document.querySelector("#car-tab-issue").getAttribute("aria-selected")'), 'true');
            await evaluate('document.querySelector(".car-close-details").click()');
            assert.equal(await evaluate('document.querySelector("#car-history-dialog").open'), false);
            await wait('document.activeElement.matches("[data-car-history]")');
            await evaluate('window.scrollTo(0,0)');
            const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
            fs.writeFileSync(path.join(directory, `register-${width}.png`), Buffer.from(shot.result.data, 'base64'));
        }
        await send('Page.navigate', { url: 'http://127.0.0.1:9374/export' });
        await wait('document.querySelectorAll("thead th").length === 14');
        assert.equal(await evaluate('document.querySelectorAll("tbody tr").length'), 1);
        const pdf = await send('Page.printToPDF', { preferCSSPageSize: true, printBackground: true });
        const pdfBytes = Buffer.from(pdf.result.data, 'base64');
        const mediaBox = pdfBytes.toString('latin1').match(/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)/);
        assert.ok(mediaBox && Number(mediaBox[1]) > Number(mediaBox[2]), 'CAR Log PDF is landscape');
        fs.writeFileSync(path.join(directory, 'car-log.pdf'), pdfBytes);
        console.log('PASS: CAR register at five widths, 14-field modal, focus restoration, and 14-column export.');
    } finally { clearTimeout(timer); ws?.close(); browser?.kill(); server.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
