const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/table-sorting');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
fs.mkdirSync(directory, { recursive: true });
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/build/manifest.json')));
const asset = key => pathToFileURL(path.join(root, 'public/build', manifest[key].file)).href;
fs.writeFileSync(path.join(directory, 'fixture.html'), `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="${asset('resources/css/app.css')}"></head><body>
<main id="main-content"><table id="records" x-data="{heading:'Name',rows:[{id:1,name:'User 10',count:100,date:'2026-01-01'},{id:2,name:'User 2',count:9,date:'2025-12-31'},{id:3,name:'Alpha',count:20,date:'2026-11-01'}]}">
<thead><tr><th x-text="heading"></th><th>Count</th><th>Date</th><th>Actions</th></tr></thead><tbody><template x-for="row in rows" :key="row.id"><tr><td x-text="row.name"></td><td x-text="row.count"></td><td><time :datetime="row.date" x-text="row.date"></time></td><td><button>Edit</button></td></tr></template></tbody></table>
<div id="replacement"></div><table data-no-sort><thead><tr><th>Preview</th></tr></thead><tbody><tr><td>Preserve document</td></tr></tbody></table></main>
<script type="module" src="${asset('resources/js/app.js')}"></script></body></html>`);
(async () => {
    let ws, browser;
    const timer = setTimeout(() => { ws?.close(); browser?.kill(); process.exitCode = 1; }, 60000);
    try {
        browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--allow-file-access-from-files', '--remote-debugging-port=9377', `--user-data-dir=${path.join(directory, 'edge')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let tabs;
        for (let i = 0; i < 60; i++) { try { tabs = await (await fetch('http://127.0.0.1:9377/json')).json(); break; } catch { await delay(100); } }
        ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let sequence = 0; const pending = new Map(), errors = [];
        ws.addEventListener('message', event => { const value = JSON.parse(event.data); if (value.method === 'Runtime.exceptionThrown') errors.push(value.params.exceptionDetails); if (pending.has(value.id)) { pending.get(value.id)(value); pending.delete(value.id); } });
        const send = (method, params = {}) => new Promise(resolve => { const id = ++sequence; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params })); });
        const evaluate = async expression => { const response = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }); if (response.result.exceptionDetails) throw Error(JSON.stringify(response.result.exceptionDetails)); return response.result.result.value; };
        const wait = async expression => { for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await delay(50); } throw Error('Timed out: ' + expression); };
        await send('Runtime.enable'); await send('Page.enable');
        await send('Page.navigate', { url: pathToFileURL(path.join(directory, 'fixture.html')).href });
        await wait(`document.querySelectorAll('#records .table-sort-button').length===3 && document.querySelectorAll('#records tbody tr').length===3`);
        const values = column => evaluate(`[...document.querySelectorAll('#records tbody tr')].map(row=>row.cells[${column}].textContent)`);
        const click = column => evaluate(`document.querySelectorAll('#records th')[${column}].querySelector('button').click()`);
        await click(0); assert.deepEqual(await values(0), ['Alpha', 'User 2', 'User 10']);
        await click(0); assert.deepEqual(await values(0), ['User 10', 'User 2', 'Alpha']);
        assert.equal(await evaluate(`document.querySelector('#records th').getAttribute('aria-sort')`), 'descending');
        await click(1); assert.deepEqual(await values(1), ['9', '20', '100']);
        await click(2); assert.deepEqual(await values(2), ['2025-12-31', '2026-01-01', '2026-11-01']);
        await evaluate(`Alpine.$data(document.querySelector('#records')).rows=[{id:4,name:'Zeta',count:8,date:'2026-02-01'},{id:5,name:'Beta',count:7,date:'2025-01-01'}]`);
        await wait(`document.querySelectorAll('#records tbody tr').length===2 && document.querySelector('#records tbody tr').textContent.includes('Beta')`);
        await evaluate(`Alpine.$data(document.querySelector('#records')).heading='Updated name'`);
        await wait(`document.querySelector('#records th button')?.textContent.includes('Updated name')`);
        await send('Page.bringToFront');
        await evaluate(`document.querySelector('#records th button').focus()`);
        assert.equal(await evaluate(`document.activeElement.classList.contains('table-sort-button')`), true);
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', text: '\r', windowsVirtualKeyCode: 13 });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
        assert.equal(await evaluate(`document.querySelector('#records th').getAttribute('aria-sort')`), 'ascending');
        await evaluate(`document.querySelector('#replacement').innerHTML='<table><thead><tr><th>Reference</th></tr></thead><tbody><tr><td>B</td></tr><tr><td>A</td></tr></tbody></table>'`);
        await wait(`!!document.querySelector('#replacement .table-sort-button')`);
        await evaluate(`document.querySelector('#replacement button').click()`);
        assert.equal(await evaluate(`document.querySelector('#replacement tbody tr').textContent`), 'A');
        assert.equal(await evaluate(`document.querySelector('[data-no-sort] .table-sort-button')`), null);
        assert.deepEqual(errors, []);
        console.log('PASS text/numeric/date sorting, direction arrows, keyboard activation, Alpine row and heading refresh, AJAX table replacement, and excluded action/preview columns');
    } finally { clearTimeout(timer); ws?.close(); browser?.kill(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
