const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/car-timeline-browser');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    execFileSync('php', ['tests/Fixtures/car-timeline.php'], { cwd: root, windowsHide: true });
    const browser = spawn(process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        ['--headless', '--disable-gpu', '--allow-file-access-from-files', '--remote-debugging-port=9342',
        `--user-data-dir=${path.join(fixtures, 'edge-profile')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    const watchdog = setTimeout(() => { console.error('CAR browser checks timed out'); process.exitCode = 1; ws?.close(); browser.kill(); }, 60000);
    try {
        let tabs;
        for (let i = 0; i < 60; i++) {
            try { tabs = await (await fetch('http://127.0.0.1:9342/json')).json(); break; } catch { await delay(200); }
        }
        assert.ok(tabs, 'Headless browser must start');
        ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let sequence = 0;
        const pending = new Map();
        ws.addEventListener('message', event => { const message = JSON.parse(event.data); if (pending.has(message.id)) { pending.get(message.id)(message); pending.delete(message.id); } });
        const send = (method, params = {}) => new Promise(resolve => { const id = ++sequence; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params })); });
        const evaluate = async expression => {
            const response = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (response.result.exceptionDetails) throw new Error(JSON.stringify(response.result.exceptionDetails));
            return response.result.result.value;
        };
        const waitFor = async expression => { for (let i = 0; i < 80; i++) { if (await evaluate(expression)) return; await delay(50); } throw new Error(`Timed out: ${expression}`); };
        await send('Page.enable');
        const cycle = fs.readFileSync(path.join(fixtures, 'cycles.html'), 'utf8');
        const wordBytes = fs.readFileSync(path.join(fixtures, 'preview.docx')).toString('base64');
        for (const width of [1440, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'dashboard.html')).href });
            await waitFor('!!window.Alpine && !!document.querySelector("[data-result-modal-tab=corrective]")');
            await evaluate(`window.detailHtml=${JSON.stringify(cycle)};window.wordBytes=${JSON.stringify(wordBytes)};window.revokedURLs=[];const revoke=URL.revokeObjectURL.bind(URL);URL.revokeObjectURL=url=>{window.revokedURLs.push(url);revoke(url);};window.fetch=async url=>{
                if(!String(url).includes('/api/v1/archives/'))return {ok:true,redirected:false,text:async()=>window.detailHtml};
                if(window.failPreview){window.failPreview=false;return new Response('',{status:403});}
                if(String(url).includes('000000000000000000000003'))return new Response(Uint8Array.from(atob(window.wordBytes),c=>c.charCodeAt(0)),{headers:{'Content-Type':'application/vnd.openxmlformats-officedocument.wordprocessingml.document'}});
                if(String(url).includes('000000000000000000000002'))return new Response(Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8e0AAAAASUVORK5CYII='),c=>c.charCodeAt(0)),{headers:{'Content-Type':'image/png'}});
                return new Response('%PDF-1.4'+String.fromCharCode(10)+'Preview test',{headers:{'Content-Type':'application/pdf'}});
            };document.querySelector('[data-result-modal-open]').click();`);
            await waitFor(`!!document.querySelector('[data-result-tab=evidence]')`);
            await evaluate(`document.querySelector('[data-result-tab=evidence]').click();window.resultDialog=document.querySelector('#audit-result-modal');window.resultHeader=document.querySelector('[data-result-modal-heading]');window.evidencePanel=document.querySelector('[data-result-panel=evidence]');window.previewLink=document.querySelector('[data-file-name="preview.pdf"]');window.initialUrl=location.href;window.previewLink.click();`);
            await waitFor(`!!document.querySelector('#evidence-preview-modal iframe')`);
            assert.equal(await evaluate(`window.resultDialog.open&&document.querySelector('#evidence-preview-modal').open&&window.resultHeader===document.querySelector('[data-result-modal-heading]')&&window.evidencePanel===document.querySelector('[data-result-panel=evidence]')&&!window.evidencePanel.hidden&&location.href===window.initialUrl`), true, 'Preview stacks above unchanged result details');
            assert.equal(await evaluate(`(()=>{const r=document.querySelector('#evidence-preview-modal').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight&&r.top>=0;})()`), true, 'Preview fits viewport');
            await evaluate(`window.lastUrl=document.querySelector('[data-evidence-preview-download]').href;document.querySelector('#evidence-preview-modal [data-evidence-preview-close]').focus()`);
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await waitFor(`!document.querySelector('#evidence-preview-modal').open&&document.activeElement===window.previewLink`);
            assert.equal(await evaluate(`window.resultDialog.open&&!window.evidencePanel.hidden&&window.revokedURLs.includes(window.lastUrl)`), true, 'Escape closes only preview and releases file data');
            await evaluate(`document.querySelector('[data-file-name="preview.png"]').click()`);
            await waitFor(`!!document.querySelector('#evidence-preview-modal img')&&document.querySelector('#evidence-preview-modal img').complete`);
            await evaluate(`document.querySelector('#evidence-preview-modal [data-evidence-preview-close]').click()`);
            await waitFor(`!document.querySelector('#evidence-preview-modal').open&&document.querySelector('[data-evidence-preview-download]').hidden`);
            await evaluate(`document.querySelector('[data-file-name="preview.docx"]').click()`);
            await waitFor(`!!document.querySelector('#evidence-preview-modal iframe[data-word-ready]')`);
            assert.equal(await evaluate(`(()=>{const f=document.querySelector('#evidence-preview-modal iframe');const d=f.contentDocument;return d.body.textContent.includes('Authorized Word evidence preview')&&d.body.textContent.includes('<script>must remain plain text</script>')&&!d.querySelector('script')&&d.querySelectorAll('table td').length===2&&d.querySelectorAll('section.docx').length===2&&[...d.querySelectorAll('span')].some(s=>s.textContent.includes('Authorized Word')&&d.defaultView.getComputedStyle(s).fontWeight==='700')&&f.sandbox.contains('allow-same-origin')&&!f.sandbox.contains('allow-scripts')&&d.documentElement.scrollWidth<=f.clientWidth+1;})()`), true, 'Word renders formatted tables, bold headings and pages safely, fitted to viewport');
            if ([1440, 390].includes(width)) {
                const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                fs.writeFileSync(path.join(fixtures, `evidence-preview-${width}.png`), Buffer.from(shot.result.data, 'base64'));
            }
            await evaluate(`document.querySelector('#evidence-preview-modal [data-evidence-preview-close]').click()`);
            await waitFor(`!document.querySelector('#evidence-preview-modal').open&&document.querySelector('[data-evidence-preview-download]').hidden`);
            await evaluate(`window.failPreview=true;window.previewLink.click()`);
            await waitFor(`!!document.querySelector('[data-evidence-preview-retry]')`);
            assert.equal(await evaluate(`document.querySelector('[data-evidence-preview-download]').hidden&&window.resultDialog.open`), true, 'Denied file has no download or preview');
            await evaluate(`document.querySelector('[data-evidence-preview-retry]').click()`);
            await waitFor(`!!document.querySelector('#evidence-preview-modal iframe')`);
            await evaluate(`document.querySelector('#audit-result-modal').close()`);
            await waitFor(`!document.querySelector('#evidence-preview-modal').open&&document.querySelector('[data-evidence-preview-download]').hidden`);
            console.log(`PASS ${width}px: stacked PDF/image/Word previews, preserved parent, focus, cleanup, authorization error and retry`);
        }
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
