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
        const closed = fs.readFileSync(path.join(fixtures, 'closed.html'), 'utf8');
        const compliant = fs.readFileSync(path.join(fixtures, 'compliant.html'), 'utf8');
        const upload = fs.readFileSync(path.join(fixtures, 'upload.html'), 'utf8');
        for (const width of [1440, 900, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'dashboard.html')).href });
            await waitFor('!!window.Alpine && !!document.querySelector("[data-result-modal-tab=corrective]")');
            await evaluate(`window.originalUrl=location.href;window.detailHtml=${JSON.stringify(cycle)};window.uploadHtml=${JSON.stringify(upload)};window.fetch=async url=>({ok:true,redirected:false,text:async()=>String(url).includes('/auditee/upload')?window.uploadHtml:window.detailHtml});window.action=document.querySelector('[data-result-modal-tab=corrective]');window.action.click();`);
            await waitFor(`!!document.querySelector('.car-timeline') && document.querySelector('[data-result-tab=corrective]').getAttribute('aria-selected')==='true'`);
            assert.equal(await evaluate(`!document.querySelector('[data-result-panel=corrective]').hidden&&document.querySelector('[data-result-panel=overview]').hidden&&location.href===window.originalUrl`), true);
            assert.equal(await evaluate(`document.querySelectorAll('.car-timeline-event[data-car-event=status]').length===5&&document.querySelectorAll('.car-timeline-event.is-current').length===1&&document.querySelector('.car-timeline-event.is-current').dataset.carStatus==='in_progress'`), true);
            assert.equal(await evaluate(`document.querySelector('.car-timeline-event[data-car-status=closed][data-car-event=status]').classList.contains('is-completed')&&!!document.querySelector('.car-timeline-event.is-upcoming[data-car-status=closed]')&&document.querySelector('.car-timeline').textContent.includes('Corrective evidence resubmitted')`), true);
            assert.equal(await evaluate(`(()=>{const marker=document.querySelector('.car-timeline-event.is-completed .car-timeline-marker'),check=marker.querySelector('path');return getComputedStyle(marker).backgroundColor==='rgb(244, 181, 28)'&&getComputedStyle(marker).animationName==='car-check-pop'&&getComputedStyle(check).animationName==='car-check-draw'&&!document.querySelector('.car-timeline-event.is-upcoming .car-timeline-check')&&!document.querySelector('.car-timeline-event.is-current .car-timeline-check');})()`), true, 'Completed steps animate gold checks; current and upcoming stay distinct');
            assert.equal(await evaluate(`(()=>{const modal=document.querySelector('#audit-result-modal'),body=document.querySelector('[data-result-modal-body]'),r=modal.getBoundingClientRect(),timeline=document.querySelector('.car-timeline').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&modal.scrollWidth<=modal.clientWidth&&body.scrollWidth<=body.clientWidth&&timeline.left>=r.left&&timeline.right<=r.right;})()`), true, `Timeline fit at ${width}`);
            assert.equal(await evaluate(`(()=>{const times=[...document.querySelectorAll('.car-timeline time')].map(time=>Date.parse(time.dateTime));return times.every((time,i)=>i===0||time>=times[i-1]);})()`), true);
            if ([1440, 390].includes(width)) {
                const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                fs.writeFileSync(path.join(fixtures, `timeline-${width}.png`), Buffer.from(shot.result.data, 'base64'));
            }
            await evaluate(`window.originalHeading=document.querySelector('[data-result-modal-heading]');window.originalTimeline=document.querySelector('.car-timeline');window.originalTabs=document.querySelector('[data-result-modal-tabs]').firstElementChild;document.querySelector('[data-corrective-reference] textarea').value='Keep my reference draft';document.querySelector('#corrective-evidence a[href^="/auditee/upload"]').click()`);
            await waitFor(`!!document.querySelector('[data-result-upload-slot] [data-evidence-upload]')`);
            assert.equal(await evaluate(`document.querySelector('[data-result-modal-heading]')===window.originalHeading&&document.querySelector('.car-timeline')===window.originalTimeline&&document.querySelector('[data-result-modal-tabs]').firstElementChild===window.originalTabs&&!document.querySelector('[data-result-panel=corrective]').hidden&&!document.querySelector('#audit-result-modal').classList.contains('is-upload')`), true, 'Uploading preserves audit heading, tabs, timeline and corrective panel');
            await evaluate(`document.querySelector('[data-result-upload-back]').click()`);
            assert.equal(await evaluate(`document.querySelector('#audit-result-modal').open&&document.querySelector('[data-result-upload-slot]').hidden&&!document.querySelector('[data-corrective-reference]').hidden&&document.querySelector('[data-corrective-reference] textarea').value==='Keep my reference draft'`), true, 'Returning keeps the same popup and reference draft');
            await evaluate(`document.querySelector('[data-result-modal-close]').click()`);
            await waitFor(`!document.querySelector('#audit-result-modal').open&&document.activeElement===window.action`);
            await evaluate(`window.detailHtml=${JSON.stringify(closed)};window.action.click()`);
            await waitFor(`!!document.querySelector('.car-timeline-event.is-current[data-car-status=closed]')`);
            assert.equal(await evaluate(`!document.querySelector('.car-timeline-event.is-upcoming')&&!document.querySelector('#corrective-evidence')`), true);
            assert.equal(await evaluate(`!!document.querySelector('.car-timeline-event.is-current[data-car-status=closed] .car-timeline-check')`), true, 'Official closure shows a gold check');
            await evaluate(`document.querySelector('[data-result-modal-close]').click()`);
            await waitFor(`!document.querySelector('#audit-result-modal').open&&document.activeElement===window.action`);
            await evaluate(`window.detailHtml=${JSON.stringify(compliant)};window.action.click()`);
            await waitFor(`!!document.querySelector('.car-timeline-empty')`);
            assert.equal(await evaluate(`!document.querySelector('.car-timeline')&&!document.querySelector('#corrective-evidence')`), true);
            console.log(`PASS ${width}px: dashboard tab link, repeated history, current/upcoming stages, timestamps, closure, no-CAR state`);
        }
        // Direct navigation from an old link must retain the requested tab after redirect.
        await send('Page.addScriptToEvaluateOnNewDocument', { source: `window.fetch=async()=>({ok:true,redirected:false,text:async()=>${JSON.stringify(cycle)}});` });
        await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'dashboard.html')).href+'?detail=2123456789abcdef01234567#corrective' });
        await waitFor(`!!document.querySelector('.car-timeline')&&document.querySelector('[data-result-tab=corrective]').getAttribute('aria-selected')==='true'`);
        console.log('PASS direct corrective-tab deep link');
        await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
        assert.equal(await evaluate(`getComputedStyle(document.querySelector('.car-timeline-check path')).animationName==='none'&&getComputedStyle(document.querySelector('.is-completed .car-timeline-marker')).animationName==='none'`), true, 'Reduced motion keeps static checks');
        console.log('PASS reduced-motion checks');
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
