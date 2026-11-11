const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/super-admin-browser');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    execFileSync('php', ['tests/Fixtures/super-admin-dashboard.php'], { cwd: root, windowsHide: true });
    const names = JSON.parse(fs.readFileSync(path.join(directory, 'departments.json')));
    const browser = spawn(process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        ['--headless', '--disable-gpu', '--allow-file-access-from-files', '--remote-debugging-port=9352', `--user-data-dir=${path.join(directory, 'edge-profile')}`, 'about:blank'],
        { windowsHide: true, stdio: 'ignore' });
    let ws;
    const watchdog = setTimeout(() => { console.error('Super Admin browser checks timed out'); process.exitCode = 1; ws?.close(); browser.kill(); }, 60000);
    try {
        let tabs;
        for (let i = 0; i < 60; i++) { try { tabs = await (await fetch('http://127.0.0.1:9352/json')).json(); break; } catch { await delay(200); } }
        assert.ok(tabs, 'Headless browser must start');
        ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let sequence = 0; const pending = new Map(); const exceptions = [];
        ws.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails);
            if (pending.has(message.id)) { pending.get(message.id)(message); pending.delete(message.id); }
        });
        const send = (method, params = {}) => new Promise(resolve => { const id = ++sequence; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params })); });
        const evaluate = async expression => {
            const response = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (response.result.exceptionDetails) throw new Error(JSON.stringify(response.result.exceptionDetails));
            return response.result.result.value;
        };
        const waitFor = async expression => { for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await delay(50); } throw new Error(`Timed out: ${expression}`); };
        await send('Page.enable'); await send('Runtime.enable');
        await send('Emulation.setFocusEmulationEnabled', { enabled: true });
        await send('Page.addScriptToEvaluateOnNewDocument', { source: `
            window.exportCalls=[];window.csvBlob=null;window.downloadName=null;
            URL.createObjectURL=blob=>{window.csvBlob=blob;return 'blob:fixture'};
            URL.revokeObjectURL=()=>{};
            HTMLAnchorElement.prototype.click=function(){window.downloadName=this.download};
            Object.defineProperty(window,'axios',{configurable:true,get(){return this.fixtureAxios},set(client){
                client.defaults.adapter=async config=>{
                    let data={data:[],meta:{total:0,lastPage:1}};
                    if(config.url==='/api/v1/logs'){
                        const p=config.params||{};
                        if(p.perPage===100){window.exportCalls.push({...p});await new Promise(resolve=>setTimeout(resolve,100));}
                        const rows=Array.from({length:101},(_,i)=>({_id:String(i),userName:'CSV User',action:'Viewed record '+i,entityType:'archive',createdAt:'2026-11-10T08:00:00Z'}));
                        const page=Number(p.page||1),perPage=Number(p.perPage||10);
                        data={data:rows.slice((page-1)*perPage,page*perPage),meta:{total:101,currentPage:page,lastPage:Math.ceil(101/perPage)}};
                    }
                    return {data,status:200,statusText:'OK',headers:{},config};
                };this.fixtureAxios=client;
            }});
        ` });
        for (const width of [1440,390,320]) {
            await send('Emulation.setDeviceMetricsOverride', {width,height:640,deviceScaleFactor:1,mobile:width<600});
            await send('Page.navigate',{url:pathToFileURL(path.join(directory,'logs.html')).href});
            await waitFor('!!document.querySelector("input[type=search]") && !!document.querySelector("[data-log-id]")');
            await evaluate('const input=document.querySelector("input[type=search]");input.value="Viewed";input.dispatchEvent(new Event("input",{bubbles:true}));const sort=document.querySelector("select[x-model=sortBy]");sort.value="userName:asc";sort.dispatchEvent(new Event("change",{bubbles:true}))');
            await waitFor('!Alpine.$data(document.querySelector("section[x-data]")).loading');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector("section[x-data]"));app.draftStartDate="2026-11-01";app.draftEndDate="2026-11-10"})()');
            await evaluate('document.querySelector(".audit-log-date-range button").click()');
            await waitFor('!Alpine.$data(document.querySelector("section[x-data]")).loading');
            await evaluate('document.querySelector("[data-log-export]").click()');
            await waitFor('Alpine.$data(document.querySelector("section[x-data]")).exporting');
            assert.equal(await evaluate('document.querySelector("[data-log-export]").disabled'),true,'Export button disables while exporting');
            await waitFor('!!window.downloadName && !Alpine.$data(document.querySelector("section[x-data]")).exporting');
            const calls=await evaluate('window.exportCalls');
            assert.equal(calls.length,2);assert.equal(calls[0].search,'Viewed');assert.equal(calls[1].sortBy,'userName');assert.equal(calls[1].page,2);assert.equal(calls[0].startDate,"2026-11-01");assert.equal(calls[1].endDate,"2026-11-10");
            const csv=await evaluate('window.csvBlob.text()');
            assert.equal(csv.split('\r\n').length,102,'All 101 logs plus headers exported');
            assert.ok(csv.includes('Viewed record 100'));
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Logs toolbar fits viewport');
            assert.equal(await evaluate('Alpine.$data(document.querySelector("section[x-data]")).perPage'),10,'Logs default to ten items at every viewport size');
            assert.equal(await evaluate('document.querySelectorAll("[data-log-id]").length'),10,'Ten logs are visible on the first page');
            await evaluate('document.querySelector(".audit-log-date-range button:last-of-type").click()');
            await waitFor('!Alpine.$data(document.querySelector("section[x-data]")).startDate');
            console.log('PASS '+width+'px: export button, busy state, matching search/sort, all pages, CSV download, responsive toolbar');
        }
        assert.equal(exceptions.length,0,JSON.stringify(exceptions));
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error=>{console.error(error);process.exitCode=1});
