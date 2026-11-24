// Real route/controller snapshots from isolated MongoDB tests, replayed without application writes.
const fs=require('node:fs'), path=require('node:path'), http=require('node:http'), assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'../..'), dir=path.join(root,'storage/framework/testing/live-retest'), pub=path.join(root,'public');
const cases=JSON.parse(fs.readFileSync(path.join(dir,'reports.json'))), manifest=JSON.parse(fs.readFileSync(path.join(pub,'build/manifest.json')));
const detailUrl=fs.readFileSync(path.join(dir,'auditee-url.txt'),'utf8'), sleep=ms=>new Promise(r=>setTimeout(r,ms));
function html(name){return fs.readFileSync(path.join(dir,name),'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,'')
    .replace(/((?:href|src)=")https?:\/\/[^/" ]+/g,'$1').replace('</body>',`<script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></body>`);}
const server=http.createServer((req,res)=>{
    const url=new URL(req.url,'http://127.0.0.1');
    if(req.method!=='GET'){res.writeHead(405);res.end();return;}
    if(url.pathname==='/reports'||url.pathname==='/auditee/my-findings'){res.setHeader('Content-Type','text/html');res.end(html(url.pathname==='/reports'?'reports.html':'auditee-index.html'));return;}
    if(url.pathname===detailUrl){res.setHeader('Content-Type','text/html');res.end(fs.readFileSync(path.join(dir,'auditee-fragment.html')));return;}
    if(url.pathname==='/api/v1/reports'){
        const key=url.searchParams.get('year')==='2024'?'empty':url.searchParams.get('planId')?'selected':'all', data=cases[key];
        assert.equal(url.searchParams.get('year'),String(data.filter.year));
        assert.equal(url.searchParams.get('planId')||'',data.filter.planId||'');
        if(url.searchParams.get('format')==='csv'){res.setHeader('Content-Type','text/csv; charset=UTF-8');res.setHeader('Content-Disposition',`attachment; filename="${key}.csv"`);res.end(data.csv);}
        else{res.setHeader('Content-Type','application/json');res.end(JSON.stringify({data:data.report}));}return;
    }
    if(url.pathname.startsWith('/api/')){res.setHeader('Content-Type','application/json');res.end(JSON.stringify({data:[],meta:{total:0,unread:0},unreadCount:0}));return;}
    const file=path.resolve(pub,'.'+decodeURIComponent(url.pathname));
    if(!file.startsWith(pub+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
    res.setHeader('Content-Type',{'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2'}[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));
});
(async()=>{
    await new Promise(r=>server.listen(9380,'127.0.0.1',r));
    const browser=spawn(process.env.EDGE_BINARY||'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless','--disable-gpu','--remote-debugging-port=9381',`--user-data-dir=${path.join(dir,'edge')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
    let ws;const watchdog=setTimeout(()=>{console.error('Retest timed out');ws?.close();browser.kill();server.close();process.exitCode=1;},60000);
    try{
        let tabs;for(let i=0;i<60;i++){try{tabs=await(await fetch('http://127.0.0.1:9381/json')).json();break;}catch{await sleep(100);}}
        assert.ok(tabs);ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
        let seq=0;const pending=new Map(),errors=[];
        ws.addEventListener('message',e=>{const d=JSON.parse(e.data);if(d.id){pending.get(d.id)?.(d);pending.delete(d.id);}if(d.method==='Runtime.exceptionThrown')errors.push(d.params.exceptionDetails.exception?.description||d.params.exceptionDetails.text);});
        const send=(method,params={})=>new Promise(r=>{const id=++seq;pending.set(id,r);ws.send(JSON.stringify({id,method,params}));});
        const evaluate=async expression=>{const d=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(d.result?.exceptionDetails)throw Error(d.result.exceptionDetails.exception?.description);return d.result?.result?.value;};
        const wait=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await sleep(70);}throw Error('Timeout '+expression+' '+JSON.stringify(errors));};
        await send('Runtime.enable');await send('Page.enable');await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:'http://127.0.0.1:9380/reports'});await wait('window.Alpine && document.querySelector(".annual-report") && !Alpine.$data(document.querySelector(".annual-report")).loading');
        for(const [key,snapshot] of Object.entries(cases)){
            await evaluate(`window.report=Alpine.$data(document.querySelector('.annual-report'));report.year=${snapshot.filter.year};report.planId=${JSON.stringify(snapshot.filter.planId||'')};report.load()`);
            await wait('!report.loading && report.current');
            const rendered=await evaluate(`[...document.querySelectorAll('.annual-report table')].map(t=>[...t.tBodies[0].rows].map(r=>[...r.cells].map(c=>c.textContent.trim())))`);
            const fields=[['unit','planned','assigned','inProgress','submitted','returned','accepted','C','N','O','compliance','outstandingEvidence'],['unit','openCars','overdueCars','awaitingVerificationCars','closedCars','unknownCars','linkedCars','legacyChecks','legacyCompliant']];
            fields.forEach((keys,i)=>assert.deepEqual(rendered[i],snapshot.report.rows.map(row=>keys.map(k=>k==='compliance'?(row[k]===null?'Not yet assessed':row[k]+'%'):String(row[k])))));
            const downloads=path.join(dir,'downloads',key+'-'+Date.now());fs.mkdirSync(downloads,{recursive:true});
            await send('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:downloads});
            await evaluate('document.querySelector("a.export").click()');
            const csvFile=path.join(downloads,key+'.csv');for(let i=0;i<100&&!fs.existsSync(csvFile);i++)await sleep(70);
            assert.equal(fs.readFileSync(csvFile,'utf8'),snapshot.csv,'Browser-downloaded CSV equals the real controller response');
            if(key==='empty')assert.equal(await evaluate('document.querySelector(".annual-report").innerText.includes("No authorized units match")'),true);
            console.log(`PASS downloaded ${key} CSV and both displayed tables: ${snapshot.report.rows.length} authorized units`);
        }
        await evaluate(`report.year=2026;report.planId=${JSON.stringify(cases.selected.filter.planId)};report.load()`);await wait('!report.loading && report.current');
        const printedPlan=cases.selected.report.plans.find(plan=>plan.id===cases.selected.filter.planId).title;
        assert.ok(await evaluate('document.querySelector(".annual-report").innerText.includes('+JSON.stringify('Audit plan: '+printedPlan)+')'),'Selected plan identity survives print');
        await send('Emulation.setEmulatedMedia',{media:'print'});
        await send('Emulation.setDeviceMetricsOverride',{width:1047,height:1000,deviceScaleFactor:1,mobile:false});
        const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(dir,'print-layout.png'),Buffer.from(shot.result.data,'base64'));
        const pdf=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});fs.writeFileSync(path.join(dir,'annual-report.pdf'),Buffer.from(pdf.result.data,'base64'));
        const layout=await evaluate(`({header:document.querySelector('.app-header').checkVisibility(),nav:document.querySelector('.sidebar').checkVisibility(),form:document.querySelector('.annual-report form').checkVisibility(),rows:[...document.querySelectorAll('.annual-report tbody')].map(t=>t.rows.length),clipped:[...document.querySelectorAll('.annual-report th,.annual-report td')].some(c=>c.getBoundingClientRect().right>innerWidth||c.getBoundingClientRect().left<0||c.scrollWidth>c.clientWidth+1)})`);
        fs.writeFileSync(path.join(dir,'print-layout.json'),JSON.stringify(layout,null,2));
        assert.equal(layout.header,false,'Unrelated application header must not print');assert.equal(layout.nav,false);assert.equal(layout.form,false);assert.deepEqual(layout.rows,[6,6]);assert.equal(layout.clipped,false,'All print cells fit');
        console.log('PASS print output: both tables/all six units, no navigation or clipped cells; PDF saved');
        await send('Emulation.setEmulatedMedia',{media:''});
        for(const width of [1440,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<700});
            await send('Page.navigate',{url:'http://127.0.0.1:9380/auditee/my-findings?detail='+detailUrl.split('/').at(-1)});
            await wait('document.querySelector("#audit-result-modal")?.open && document.querySelector("#audit-result-modal [data-result-detail]")');
            await evaluate('document.querySelector("#audit-result-modal [data-result-tab=corrective]").click()');
            const times=await evaluate(`[...document.querySelectorAll('#audit-result-modal .car-timeline time')].map(t=>({text:t.textContent,at:t.dateTime}))`);
            assert.ok(times.length>=2);for(const time of times){assert.match(time.text,/Nov 4, 2026 1:42:20 PM PST \(UTC\+08:00\)/);assert.equal(new Date(time.at).toISOString(),'2026-11-04T05:42:20.123Z');}
            assert.match(await evaluate('document.querySelector("#audit-result-modal [data-result-panel=corrective]").innerText'),/Deadline: Nov 5, 2026/);
            await evaluate('document.querySelector("#audit-result-modal [data-result-tab=evidence]").click()');assert.match(await evaluate('document.querySelector("#audit-result-modal [data-result-panel=evidence]").innerText'),/1:42:20 PM PST/);
            console.log(`PASS ${width}px actual auditee modal/standalone redirect fragment: Manila seconds, exact instant, unchanged deadline/evidence history`);
        }
        assert.deepEqual(errors,[]);await send('Browser.close');
    }finally{clearTimeout(watchdog);ws?.close();browser.kill();server.close();}
})().catch(e=>{console.error(e);process.exitCode=1;server.close();});
