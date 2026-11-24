const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/help-browser');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    execFileSync('php', ['tests/Fixtures/auditee-help.php'], { cwd: root, windowsHide: true });
    const browser = spawn(process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        ['--headless', '--disable-gpu', '--allow-file-access-from-files', '--remote-debugging-port=9348',
            `--user-data-dir=${path.join(fixtures, 'edge-profile')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    const watchdog = setTimeout(() => { console.error('Help browser checks timed out'); process.exitCode = 1; ws?.close(); browser.kill(); }, 60000);
    try {
        let tabs;
        for (let i = 0; i < 60; i++) {
            try { tabs = await (await fetch('http://127.0.0.1:9348/json')).json(); break; }
            catch { await delay(200); }
        }
        assert.ok(tabs, 'Headless Edge starts');
        ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let sequence = 0;
        const pending = new Map();
        const errors = [];
        ws.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails);
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
            throw new Error(`Timed out: ${expression}; ${JSON.stringify(await evaluate(`({selected: window.help?.selected, focus: document.activeElement?.outerHTML, errors: document.querySelector('.help-center')?.textContent.slice(0,80)})`))}; runtime: ${JSON.stringify(errors)}`);
        };
        const key = async (key, code, windowsVirtualKeyCode) => {
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode, text: key === 'Enter' ? '\r' : key === ' ' ? ' ' : undefined });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode });
        };
        await send('Page.enable');
        await send('Page.bringToFront');
        await send('Emulation.setFocusEmulationEnabled', { enabled: true });
        await send('Runtime.enable');
        const file = pathToFileURL(path.join(fixtures, 'help.html')).href;
        for (const width of [1440, 1024, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: file });
            await waitFor(`!!window.Alpine && document.querySelector('.help-center') && !document.querySelector('.help-center').hasAttribute('x-cloak')`);
            await evaluate(`window.help=Alpine.$data(document.querySelector('.help-center'));window.visible=()=>[...document.querySelectorAll('[data-help-topic]')].filter(e=>e.getClientRects().length).map(e=>e.id)`);
            assert.deepEqual(await evaluate('visible()'), ['prepare']);
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, `No horizontal overflow at ${width}`);
            assert.equal(await evaluate(`getComputedStyle(document.querySelector('#help-topic-select')).display!=='none'`), width < 768);
            for (const id of ['prepare', 'present', 'respond', 'nco', 'standards']) {
                if (width >= 768) {
                    await evaluate(`document.querySelector('[aria-controls="${id}"]').focus()`);
                    await key('Enter', 'Enter', 13);
                } else {
                    await evaluate(`(()=>{const s=document.querySelector('#help-topic-select');s.value='${id}';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
                }
                await waitFor(`visible().join()==='${id}'`);
                assert.equal(await evaluate(`document.querySelector('#help-topic-select').value`), id, 'Mobile selector stays synchronized');
                assert.equal(await evaluate(`document.querySelector('[aria-controls="${id}"]').getAttribute('aria-pressed')`), 'true');
                assert.equal(await evaluate(`document.documentElement.scrollWidth <= innerWidth`), true);
            }
            await evaluate(`help.choose('present')`);
            await waitFor(`visible().join()==='present'`);
            await evaluate(`document.querySelectorAll('#present summary')[1].focus()`);
            await key(' ', 'Space', 32);
            await waitFor(`document.querySelectorAll('#present details')[1].open`);
            const ax = await send('Accessibility.getFullAXTree');
            const headings = ax.result.nodes.filter(n => !n.ignored && n.role?.value === 'heading').map(n => n.name?.value);
            assert.ok(headings.includes('Present evidence without unnecessary copying'));
            assert.ok(!headings.includes('Understanding N / C / O'), 'Hidden panels are excluded from accessibility tree');
            await evaluate(`(()=>{const s=document.querySelector('#help-search');s.focus();s.value='  SIGNATURE  ';s.dispatchEvent(new Event('input',{bubbles:true}));})()`);
            await waitFor(`visible().join()==='present'&&help.filteredTopics.length===1`);
            assert.equal(await evaluate(`document.querySelector('.help-result-count').textContent`), '1 matching topic');
            await evaluate(`document.querySelector('#help-search').value='xyznomatchingtopic';document.querySelector('#help-search').dispatchEvent(new Event('input',{bubbles:true}))`);
            await waitFor(`!visible().length&&document.querySelector('.help-empty').getClientRects().length>0`);
            assert.equal(await evaluate(`document.querySelector('#help-topic-select').disabled`), true);
            await evaluate(`document.querySelector('.help-empty button').focus()`);
            await key('Enter', 'Enter', 13);
            await waitFor(`visible().length===1&&help.filteredTopics.length===5&&document.activeElement.id==='help-search'`);
            await evaluate(`help.query='N/C/O'`);
            await waitFor(`help.filteredTopics.some(t=>t.id==='nco')`);
            await evaluate(`help.clearSearch();help.choose('prepare')`);
            await delay(50);
            if ([1440, 390].includes(width)) {
                const screenshot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
                fs.writeFileSync(path.join(fixtures, `help-${width}.png`), Buffer.from(screenshot.result.data, 'base64'));
            }
            console.log(`PASS ${width}px: topic navigation, one panel, responsive layout, search, empty results, focus, keyboard disclosures, accessibility tree`);
        }
        await send('Page.navigate', { url: file + '#nco' });
        await waitFor(`!!window.Alpine&&document.querySelector('#nco').getClientRects().length>0`);
        await evaluate(`location.hash='standards'`);
        await waitFor(`document.querySelector('#standards').getClientRects().length>0`);
        await evaluate(`Alpine.$data(document.querySelector('.help-center')).topics=[];Alpine.$data(document.querySelector('.help-center')).selected=''`);
        await waitFor(`document.querySelector('#help-empty-title').textContent==='No help topics available'`);
        assert.deepEqual(errors, [], 'No JavaScript runtime errors');
        console.log('PASS legacy hash links, hash changes, empty topic data, and no JavaScript exceptions');
        for (const portal of ['super-admin', 'admin', 'auditor']) {
            for (const width of [1440, 390]) {
                await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width < 600 });
                await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, `${portal}.html`)).href });
                await waitFor(`!!window.Alpine && !document.querySelector('.help-center').hasAttribute('x-cloak')`);
                await evaluate(`window.help=Alpine.$data(document.querySelector('.help-center'));window.visible=()=>[...document.querySelectorAll('[data-help-topic]')].filter(e=>e.getClientRects().length).map(e=>e.id)`);
                assert.equal(await evaluate('help.topics.length'), 5);
                for (const id of await evaluate('help.topics.map(t=>t.id)')) {
                    await evaluate(`help.choose('${id}')`);
                    await waitFor(`visible().join()==='${id}'`);
                    assert.equal(await evaluate(`document.querySelector('#help-topic-select').value`), id);
                    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true);
                }
                await evaluate(`help.query='qzxxnonexistent'`);
                await waitFor(`visible().length===0&&document.querySelector('.help-empty').getClientRects().length>0`);
                await evaluate('help.clearSearch()');
                await waitFor('visible().length===1&&help.filteredTopics.length===5');
                if (width === 1440) {
                    await evaluate('help.choose(help.topics[0].id)');
                    await delay(50);
                    const screenshot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
                    fs.writeFileSync(path.join(fixtures, `${portal}-${width}.png`), Buffer.from(screenshot.result.data, 'base64'));
                }
                console.log(`PASS ${portal} ${width}px: five role-specific topics, single panel, search recovery, no overflow`);
            }
        }
        assert.deepEqual(errors, [], 'All role Help Centers initialize without runtime errors');
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
