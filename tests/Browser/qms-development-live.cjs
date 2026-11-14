// Read private development credentials locally; never print passwords or tokens.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const report = JSON.parse(fs.readFileSync(path.join(root, 'storage/app/private/qms-development/report.json'), 'utf8'));
const credentials = JSON.parse(fs.readFileSync(report.credentialsFile, 'utf8'));
const output = path.join(root, 'storage/framework/testing/qms-live'); fs.mkdirSync(output, { recursive: true });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    const browser = spawn(process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        ['--headless', '--disable-gpu', '--remote-debugging-port=9348', `--user-data-dir=${path.join(output, 'edge-profile')}`, 'about:blank'], { windowsHide: true, stdio:'ignore' });
    let ws; const timer = setTimeout(() => { console.error('Live QMS browser checks timed out'); process.exitCode=1; ws?.close(); browser.kill(); }, 60000);
    try {
        let tabs; for(let i=0;i<60;i++){try{tabs=await(await fetch('http://127.0.0.1:9348/json')).json();break}catch{await delay(200)}}
        assert.ok(tabs,'Headless browser must start');
        ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
        await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
        let sequence=0;const pending=new Map();
        ws.addEventListener('message',event=>{const message=JSON.parse(event.data);if(pending.has(message.id)){pending.get(message.id)(message);pending.delete(message.id)}});
        const send=(method,params={})=>new Promise(resolve=>{const id=++sequence;pending.set(id,resolve);ws.send(JSON.stringify({id,method,params}))});
        const evaluate=async expression=>{const response=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(response.result.exceptionDetails)throw new Error('Browser evaluation failed');return response.result.result.value};
        const waitFor=async expression=>{for(let i=0;i<150;i++){if(await evaluate(expression))return;await delay(100)}throw new Error('Live UI did not reach the expected state')};
        await send('Page.enable');await send('Network.enable');
        const login=async key=>{
            const account=report.accounts.find(account=>account.key===key);assert.ok(account&&credentials[account.email],'New demo account credentials must exist');
            await send('Network.clearBrowserCookies');await send('Page.navigate',{url:'http://localhost:8000/login'});
            await waitFor('!!document.querySelector("#loginEmail") && !!window.Alpine && typeof Alpine.$data(document.querySelector("#loginEmail").closest("[x-data]")).email === "string"');
            const values=JSON.stringify({email:account.email,password:credentials[account.email].password});
            await evaluate(`(()=>{const values=${values};for(const name of ['email','password']){const input=document.querySelector(name==='email'?'#loginEmail':'#loginPassword');input.value=values[name];input.dispatchEvent(new Event('input',{bubbles:true}));}document.querySelector('button[type=submit]').click();})()`);
            await waitFor('location.pathname==="/auditee/dashboard" && !!document.querySelector(".auditee-dashboard")');
        };
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:960,deviceScaleFactor:1,mobile:false});
        await login('CET-IT:department_secretary');
        assert.equal(await evaluate('document.body.innerText.includes("Authorized oversight")'),true,'Department secretary sees scoped oversight');
        await send('Page.navigate',{url:'http://localhost:8000/auditee/documents'});
        await waitFor('!!document.querySelector(".qms-document") && !Alpine.$data(document.querySelector(".qms-explorer")).loading');
        assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).tree.units.length'),1,'Secretary sees one department');
        await evaluate('Array.from(document.querySelectorAll(".qms-document")).find(card=>card.querySelector("h4").textContent.includes("IT syllabus")).querySelector(".qms-doc-actions button").click()');
        await waitFor('document.querySelector("[x-ref=details]").open');
        await evaluate('document.querySelector("[x-ref=details] [data-evidence-preview]").click()');
        await waitFor('!!document.querySelector("#evidence-preview-modal iframe[data-word-ready]")');
        assert.equal(await evaluate('document.querySelector("#evidence-preview-modal iframe").contentDocument.querySelectorAll("table").length > 0'),true,'Actual imported syllabus preserves its tables');
        assert.equal(await evaluate('document.querySelector("[x-ref=details]").open'),true,'Real stored file preview preserves details');
        const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync(path.join(output,'it-document-preview.png'),Buffer.from(shot.result.data,'base64'));
        console.log('PASS live IT secretary: authentication, authorized records, real encrypted-file preview, preserved details');
        await login('CET-ENV:department_secretary');
        const itFile=report.documents.find(document=>document.filename==='MOCK-IT-syllabus.docx');assert.ok(itFile);
        assert.equal(await evaluate(`fetch('/api/v1/documents/${itFile.id}/download',{headers:{Accept:'application/json'}}).then(response=>response.status)`),404,'Cross-department file URL is denied');
        assert.equal(await evaluate(`fetch('/api/v1/documents?search=IT%20syllabus',{headers:{Accept:'application/json'}}).then(response=>response.json()).then(data=>data.meta.total)`),0,'Cross-department search is empty');
        console.log('PASS live Environmental Engineering secretary: IT direct download denied and search isolated');
        await login('college_dean');
        await send('Emulation.setDeviceMetricsOverride',{width:390,height:960,deviceScaleFactor:1,mobile:true});
        await send('Page.navigate',{url:'http://localhost:8000/auditee/documents'});
        await waitFor('!!document.querySelector(".qms-document") && !Alpine.$data(document.querySelector(".qms-explorer")).loading');
        assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).tree.units.length'),6,'Dean sees five departments plus Office of the Dean');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Actual seeded mobile archive has no horizontal overflow');
        const mobile=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync(path.join(output,'dean-mobile-archives.png'),Buffer.from(mobile.result.data,'base64'));
        console.log('PASS live CET Dean: college-wide folders, actual records, mobile layout');
        await send('Browser.close');
    } finally {clearTimeout(timer);ws?.close();browser.kill()}
})().catch(error=>{console.error(error.message);process.exitCode=1});
