const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/evidence-browser');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    execFileSync('php', ['tests/Fixtures/evidence-requests.php'], { cwd: root, windowsHide: true });
    const browserPath = process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
    const browser = spawn(browserPath, ['--headless', '--disable-gpu', '--allow-file-access-from-files', '--remote-debugging-port=9341',
        `--user-data-dir=${path.join(fixtures, 'edge-profile')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    const watchdog = setTimeout(() => { console.error('Browser checks timed out'); process.exitCode = 1; ws?.close(); browser.kill(); }, 60000);
    try {
        let tabs;
        for (let i = 0; i < 60; i++) {
            try { tabs = await (await fetch('http://127.0.0.1:9341/json')).json(); break; }
            catch { await delay(200); }
        }
        assert.ok(tabs, 'Headless Edge must start');
        ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let sequence = 0;
        const pending = new Map();
        ws.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (pending.has(message.id)) { pending.get(message.id)(message); pending.delete(message.id); }
        });
        const send = (method, params = {}) => new Promise(resolve => {
            const id = ++sequence; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params }));
        });
        const evaluate = async expression => {
            const response = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (response.result.exceptionDetails) throw new Error(JSON.stringify(response.result.exceptionDetails));
            return response.result.result.value;
        };
        const waitFor = async expression => {
            for (let i = 0; i < 80; i++) { if (await evaluate(expression)) return; await delay(50); }
            throw new Error(`Timed out: ${expression}`);
        };
        await send('Page.enable');
        const detail = fs.readFileSync(path.join(fixtures, 'detail.html'), 'utf8');
        const locked = fs.readFileSync(path.join(fixtures, 'locked.html'), 'utf8');
        const populated = fs.readFileSync(path.join(fixtures, 'populated.html'), 'utf8');
        for (const width of [1440, 900, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'populated.html')).href });
            await waitFor('!!window.Alpine && !!document.querySelector("[data-evidence-open]")');
            await evaluate(`window.fixtureDetail=${JSON.stringify(detail)};window.fixtureLocked=${JSON.stringify(locked)};window.fixturePage=${JSON.stringify(populated)};
                window.originalUrl=location.href;window.holdDetail=true;window.fetch=async (url,options)=>{if(window.holdDetail)await new Promise(resolve=>window.releaseDetail=resolve);return {ok:true,redirected:false,text:async()=>window.fixtureDetail};};window.openerControl=document.querySelector('[data-evidence-open]');`);
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, `Page overflow at ${width}`);
            assert.equal(await evaluate('document.querySelectorAll(".evidence-summary-card").length'), 4);
            assert.equal(await evaluate(`parseFloat(getComputedStyle(document.querySelector('.evidence-search input')).paddingLeft)>=38`), true, 'Search text clears its icon');
            if ([1440, 390].includes(width)) {
                const screenshot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                fs.writeFileSync(path.join(fixtures, `page-${width}.png`), Buffer.from(screenshot.result.data, 'base64'));
            }
            await evaluate('window.openerControl.click()');
            assert.equal(await evaluate('document.querySelector("[data-evidence-body]").getAttribute("aria-busy")==="true"'), true);
            await evaluate('window.holdDetail=false;window.releaseDetail()');
            await waitFor('!!document.querySelector("[data-evidence-detail] .workflow-form") && document.querySelector("[data-evidence-body]").getAttribute("aria-busy")==="false"');
            await delay(50);
            assert.equal(await evaluate(`(()=>{const m=document.querySelector('#evidence-request-modal'),r=m.getBoundingClientRect();return m.open&&r.left>=0&&r.right<=innerWidth&&m.scrollWidth<=m.clientWidth&&location.href===window.originalUrl&&document.querySelector('#main-content').style.overflow==='hidden';})()`), true, `Modal fit at ${width}`);
            assert.equal(await evaluate(`(()=>{const form=document.querySelector('#evidence-request-modal form');return form.querySelector('input[type=file]').disabled&&!form.querySelector('select[name=availability]').checkVisibility();})()`), true, 'Alpine initializes response controls');
            await evaluate(`const method=document.querySelector('#evidence-request-modal select[name=method]');method.value='physical-original';method.dispatchEvent(new Event('change',{bubbles:true}));`);
            await delay(40);
            assert.equal(await evaluate(`!document.querySelector('select[name=availability]').disabled&&document.querySelector('select[name=availability]').checkVisibility()`), true);
            await evaluate(`document.querySelector('#evidence-request-modal select[name=method]').value='file';document.querySelector('#evidence-request-modal select[name=method]').dispatchEvent(new Event('change',{bubbles:true}));`);
            await delay(40);
            assert.equal(await evaluate(`!document.querySelector('input[type=file]').disabled&&document.querySelector('input[type=file]').required&&document.querySelector('input[name=copyAuthorized]').required`), true);
            await evaluate(`document.querySelector('#evidence-request-modal select[name=method]').value='digital-reference';document.querySelector('#evidence-request-modal select[name=method]').dispatchEvent(new Event('change',{bubbles:true}));`);
            await delay(40);
            await evaluate(`window.fetch=async()=>({ok:false,status:422,json:async()=>({errors:{notes:['Enter permitted evidence notes.']}})});document.querySelector('#evidence-request-modal textarea').value='Preserved response';document.querySelector('#evidence-request-modal form').requestSubmit(document.querySelector('#evidence-request-modal button[value=submit]'));`);
            await waitFor(`document.querySelector('[data-evidence-message]').textContent==='Enter permitted evidence notes.'`);
            assert.equal(await evaluate(`document.querySelector('#evidence-request-modal textarea').value==='Preserved response'&&document.querySelector('#evidence-request-modal').open`), true);
            await evaluate(`window.sentAction='';window.fetch=async(url,options)=>options?.method==='POST'?(window.sentAction=options.body.get('action'),{ok:true,json:async()=>({message:'Draft saved'})}):{ok:true,redirected:false,text:async()=>String(url).includes('requestId=')?window.fixtureDetail:window.fixturePage};document.querySelector('#evidence-request-modal form').requestSubmit(document.querySelector('#evidence-request-modal button[value=draft]'));`);
            await waitFor(`document.querySelector('[data-evidence-message]').textContent==='Draft saved'&&!!document.querySelector('#evidence-request-modal form')&&!document.querySelector('#evidence-request-modal form').dataset.saving&&document.querySelector('[data-evidence-body]').getAttribute('aria-busy')==='false'`);
            assert.equal(await evaluate('window.sentAction'), 'draft');
            await evaluate(`document.querySelector('.evidence-modal-header [data-evidence-close]').focus()`);
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, modifiers: 8 });
            assert.equal(await evaluate(`document.activeElement===document.querySelector('.evidence-modal-footer [data-evidence-close]')`), true);
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            if ([1440, 390].includes(width)) {
                const screenshot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                fs.writeFileSync(path.join(fixtures, `modal-${width}.png`), Buffer.from(screenshot.result.data, 'base64'));
            }
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await waitFor(`!document.querySelector('#evidence-request-modal').open&&document.querySelector('#main-content').style.overflow!=='hidden'&&document.activeElement.matches('[data-evidence-open]')`);
            await evaluate(`window.fetch=async()=>({ok:false,status:404});document.querySelector('[data-evidence-open]').click()`);
            await waitFor(`!!document.querySelector('[data-evidence-retry]')`);
            await evaluate(`window.fetch=async()=>({ok:true,redirected:false,text:async()=>window.fixtureLocked});document.querySelector('[data-evidence-retry]').click()`);
            await waitFor(`!!document.querySelector('.evidence-read-only')`);
            assert.equal(await evaluate(`!document.querySelector('#evidence-request-modal form')`), true);
            await evaluate(`document.querySelector('[data-evidence-close]').click()`);
            await evaluate(`window.fetch=async()=>({ok:true,redirected:false,text:async()=>window.fixtureDetail});document.querySelector('[data-evidence-response]').click()`);
            await waitFor(`document.activeElement.matches('#evidence-request-modal select[name=method]')`);
            await evaluate(`document.querySelector('[data-evidence-close]').click()`);
            console.log(`PASS ${width}px: cards, popup, methods, validation, draft, keyboard, errors, read-only, response focus`);
        }
        await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'empty.html')).href });
        await waitFor('!!window.Alpine && !!document.querySelector(".evidence-empty")');
        assert.equal(await evaluate(`document.querySelector('.evidence-empty').textContent.includes('No evidence requests yet')&&document.documentElement.scrollWidth<=innerWidth`), true);
        await send('Page.addScriptToEvaluateOnNewDocument', { source: `window.fetch=async()=>({ok:true,redirected:false,text:async()=>${JSON.stringify(detail)}});` });
        await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'populated.html')).href+'#request-22222222-2222-4222-8222-222222222222' });
        await waitFor(`!!document.querySelector('[data-evidence-detail]')`);
        console.log('PASS empty state and notification deep link outside current page');
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
