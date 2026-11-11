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
        await send('Page.addScriptToEvaluateOnNewDocument',{source:`
            localStorage.clear();
            window.fixtureUnread=125; window.fixtureActivities=[];
            Object.defineProperty(window,'axios',{configurable:true,get(){return this.fixtureAxios},set(client){
                client.defaults.adapter=async config=>({data:{data:config.url==='/api/v1/logs'?window.fixtureActivities:[],meta:{total:config.url==='/api/v1/notifications'?window.fixtureUnread:0,lastPage:1}},status:200,statusText:'OK',headers:{},config});this.fixtureAxios=client;
            }});
        `});
        for(const role of [0,1,2,3]) for(const width of [1440,390,320]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:640,deviceScaleFactor:1,mobile:width<600});
            await send('Page.navigate',{url:pathToFileURL(path.join(directory,role===0?'profile.html':`profile-role-${role}.html`)).href});
            await waitFor('document.querySelector(".header-notification-count")?.textContent==="99+" && document.querySelector(".header-notification-count").checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".header-notification-count").checkVisibility()'),true,'Badge appears before opening dropdown');
            const style=await evaluate('(()=>{const s=getComputedStyle(document.querySelector(".header-notification-count"));return {background:s.backgroundColor,color:s.color}})()');
            assert.equal(style.background,'rgb(220, 38, 38)');assert.equal(style.color,'rgb(255, 255, 255)');
            await evaluate('window.fixtureUnread=3;Alpine.$data(document.querySelector(".header-notification-count")).refreshUnreadCount()');
            await waitFor('document.querySelector(".header-notification-count").textContent==="3"');
            await evaluate('window.fixtureUnread=0;Alpine.$data(document.querySelector(".header-notification-count")).refreshUnreadCount()');
            await waitFor('!document.querySelector(".header-notification-count").checkVisibility()');
            console.log(`PASS role ${role}, ${width}px: initial unread count, 99+ cap, updated count, hidden at zero`);
            if(role!==0) continue;
            await evaluate('window.fixtureActivities=[{_id:"activity-one",action:"Created audit"},{_id:"activity-two",action:"Submitted audit"}];Alpine.$data(document.querySelector(".header-notification-count")).refreshUnreadCount()');
            await waitFor('document.querySelector(".header-notification-count").textContent==="2" && document.querySelector(".header-notification-count").checkVisibility()');
            await evaluate('Alpine.$data(document.querySelector(".header-notification-count")).markAllRead()');
            await waitFor('!document.querySelector(".header-notification-count").checkVisibility()');
            await evaluate('window.fixtureActivities=[]');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".header-notification-count"));app.$nextTick=callback=>callback();app.open=true;app.loading=true})()');
            await waitFor('document.querySelector(".header-notification-skeleton")?.checkVisibility()');
            const skeletonHeight=await evaluate('document.querySelector(".header-notification-panel").getBoundingClientRect().height');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".header-notification-count"));app.notifications=Array.from({length:8},(_,i)=>({_id:String(i),title:"A very long notification title that should fit the panel",message:"A long notification message with additional details",isRead:false,createdAt:"2026-11-10T08:00:00Z"}));app.loading=false})()');
            await waitFor('document.querySelector(".header-notification-item")?.checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".header-notification-panel").getBoundingClientRect().height'),skeletonHeight,'Loaded panel keeps the skeleton height');
            assert.equal(await evaluate('document.querySelector(".header-notification-item-icon svg:last-child").checkVisibility()'),true,'Notification bell icon is visible');
            const fit=await evaluate('(()=>{const p=document.querySelector(".header-notification-panel"),r=p.getBoundingClientRect();return {left:r.left,right:r.right,bottom:r.bottom,height:innerHeight,width:innerWidth,scroll:p.scrollHeight,client:p.clientHeight}})()');
            assert.ok(fit.left>=0&&fit.right<=fit.width&&fit.bottom<=fit.height&&fit.scroll<=fit.client+1,JSON.stringify(fit));
            await evaluate('document.querySelector(".header-notification-panel footer button:last-of-type").click()');
            await waitFor('Alpine.$data(document.querySelector(".header-notification-count")).previewPage===2');
            assert.equal(await evaluate('document.querySelector(".header-notification-panel").getBoundingClientRect().height'),skeletonHeight,'Short final page keeps the same height');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".header-notification-count"));app.notifications=[];app.activities=[{_id:"activity-icon",action:"Updated profile",userName:"Test user"}];app.previewPage=1})()');
            await waitFor('document.querySelector(".header-notification-item-icon svg:first-child")?.checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".header-notification-panel").getBoundingClientRect().height'),skeletonHeight,'Activity-only panel keeps the same height');
            await evaluate('Alpine.$data(document.querySelector(".header-notification-count")).activities=[]');
            await waitFor('document.querySelector(".header-notification-empty").checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".header-notification-panel").getBoundingClientRect().height'),skeletonHeight,'Empty panel keeps the same height');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Header fits viewport');
            console.log('PASS '+width+'px: initial unread count, red badge with white text, 99+ cap, updated count, hidden at zero');
        }
        assert.equal(exceptions.length,0,JSON.stringify(exceptions));
        await send('Browser.close');
    }finally{clearTimeout(watchdog);ws?.close();browser.kill()}
})().catch(error=>{console.error(error);process.exitCode=1});
