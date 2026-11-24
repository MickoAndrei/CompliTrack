const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..'), fixture = path.join(root, 'storage/framework/testing/walkthrough-browser'), publicRoot = path.join(root, 'public');
const manifest = JSON.parse(fs.readFileSync(path.join(publicRoot, 'build/manifest.json')));
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const id = '0123456789abcdef01234568';
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    if (req.method !== 'GET') { res.writeHead(405); res.end('Read-only fixtures'); return; }
    if (['/reports', '/plans'].includes(url.pathname)) {
        let html = fs.readFileSync(path.join(fixture, url.pathname.slice(1)+'.html'), 'utf8');
        html = html.replace('</body>', `<script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></body>`);
        res.setHeader('Content-Type', 'text/html'); res.end(html); return;
    }
    if (url.pathname.startsWith('/api/')) {
        let data = { data: [], meta: {total:0,unread:0}, unreadCount:0 };
        if (url.pathname === '/api/v1/reports') data = {data: {year: Number(url.searchParams.get('year')), generatedAt:'2026-12-31T23:59:59Z', scope:'Accepted checklist C / (C + N), observations separate; fixture results.', plans:[{id,title:'Controlled annual plan'}], rows:[{unitId:id,unit:'Information Technology with a long authorized department name',planned:1,assigned:2,inProgress:0,submitted:1,returned:0,accepted:1,C:1,N:1,O:1,compliance:50,outstandingEvidence:1,openCars:1,overdueCars:0,awaitingVerificationCars:0,closedCars:1,unknownCars:0,linkedCars:2,legacyChecks:0,legacyCompliant:0}]}};
        if (url.pathname === '/api/v1/audit-plans') data = {plans:[],canPrepare:true,canApprove:false,units:[],reviewers:[],archives:[{id,title:'Controlled annual plan',code:'IQA-PLAN-01',type:'physical-original',version:2,eligible:true},{id:'0123456789abcdef01234569',title:'Legacy record',code:'LEGACY-1',type:'digital-reference',version:1,eligible:false}]};
        res.setHeader('Content-Type','application/json'); res.end(JSON.stringify(data)); return;
    }
    const file = path.resolve(publicRoot,'.'+decodeURIComponent(url.pathname));
    if (!file.startsWith(publicRoot+path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {res.writeHead(404);res.end();return;}
    res.setHeader('Content-Type', {'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2'}[path.extname(file)] || 'application/octet-stream'); res.end(fs.readFileSync(file));
});
(async () => {
    execFileSync('php',['tests/Fixtures/walkthrough-ui.php'],{cwd:root,windowsHide:true});
    await new Promise(resolve=>server.listen(9370,'127.0.0.1',resolve));
    const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless','--disable-gpu','--remote-debugging-port=9371',`--user-data-dir=${path.join(fixture,'edge')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
    let ws; const watchdog=setTimeout(()=>{ws?.close();browser.kill();server.close();process.exitCode=1;},60000);
    try {
        let tabs;for(let i=0;i<60;i++){try{tabs=await(await fetch('http://127.0.0.1:9371/json')).json();break;}catch{await sleep(150);}}
        assert.ok(tabs,'Edge starts');ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
        await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));let seq=0;const pending=new Map(),errors=[];
        ws.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.id){pending.get(data.id)?.(data);pending.delete(data.id);}if(data.method==='Runtime.exceptionThrown')errors.push(data.params.exceptionDetails.exception?.description || data.params.exceptionDetails.text);});
        const send=(method,params={})=>new Promise(resolve=>{const n=++seq;pending.set(n,resolve);ws.send(JSON.stringify({id:n,method,params}));});
        const evaluate=async expression=>{const data=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(data.result?.exceptionDetails)throw Error(data.result.exceptionDetails.exception?.description);return data.result?.result?.value;};
        const wait=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await sleep(80);}throw Error('Timeout: '+expression+' '+JSON.stringify(errors));};
        await send('Runtime.enable');await send('Page.enable');
        for(const width of [1440,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<700});
            await send('Page.navigate',{url:'http://127.0.0.1:9370/reports'});
            await wait('document.querySelector(".annual-report tbody tr") && !Alpine.$data(document.querySelector(".annual-report")).loading');
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'),true,`Report fits ${width}px`);
            assert.match(await evaluate('document.querySelector(".annual-report").innerText'),/50%/);
            assert.match(await evaluate('document.querySelector(".annual-report").innerText'),/Jan 1, 2027|1 Jan 2027/);
            await evaluate('window.reportApp=Alpine.$data(document.querySelector(".annual-report"));reportApp.year=2025;reportApp.planId="'+id+'"');
            await wait('document.querySelector(".annual-report a.export").checkVisibility() === false');
            await evaluate('reportApp.load()');await wait('!reportApp.loading && reportApp.report.year===2025');
            assert.match(await evaluate('document.querySelector("a.export").href'),/year=2025.*planId=/);
            await send('Page.navigate',{url:'http://127.0.0.1:9370/plans'});
            await wait('window.Alpine && document.querySelector(".plan-page") && !Alpine.$data(document.querySelector(".plan-page")).loading');
            await evaluate('window.planApp=Alpine.$data(document.querySelector(".plan-page"));planApp.edit()');await wait('document.querySelector("dialog").open');
            assert.equal(await evaluate('planApp.documentChoices.length'),1,'Unclassified archives excluded by default');
            assert.match(await evaluate('document.querySelector("dialog select").textContent'),/IQA-PLAN-01.*physical-original.*v2/);
            await evaluate('planApp.showLegacy=true');assert.equal(await evaluate('planApp.documentChoices.length'),2);
            await evaluate('planApp.draft.procedureReference="none";planApp.save()');assert.match(await evaluate('planApp.error'),/procedure/);
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'),true,`Plan dialog fits ${width}px`);
            console.log(`PASS ${width}px: annual filters/CSV parameters, Manila history display, plan classification and procedure validation`);
        }
        assert.deepEqual(errors,[]);await send('Browser.close');
    } finally {clearTimeout(watchdog);ws?.close();browser.kill();server.close();}
})().catch(error=>{console.error(error);process.exitCode=1;server.close();});
