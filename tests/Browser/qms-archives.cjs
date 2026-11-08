const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/qms-browser');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    execFileSync('php', ['tests/Fixtures/qms-archives.php'], { cwd: root, windowsHide: true });
    const browser = spawn(process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        ['--headless', '--disable-gpu', '--allow-file-access-from-files', '--remote-debugging-port=9347', `--user-data-dir=${path.join(fixtures, 'edge-profile')}`, 'about:blank'],
        { windowsHide: true, stdio: 'ignore' });
    let ws;
    const watchdog = setTimeout(() => { console.error('QMS browser checks timed out'); process.exitCode = 1; ws?.close(); browser.kill(); }, 60000);
    try {
        let tabs;
        for (let i = 0; i < 60; i++) { try { tabs = await (await fetch('http://127.0.0.1:9347/json')).json(); break; } catch { await delay(200); } }
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
        await send('Page.addScriptToEvaluateOnNewDocument', { source: `
            window.fixtureCalls=[];
            const id=n=>String(n).padStart(24,'0');
            const units=['Agricultural and Biosystem Engineering','Environmental Engineering','Food Processing and Technology','Hospitality Management','Information Technology','Office of the Dean'].map((name,i)=>({_id:id(i+10),collegeId:id(1),name,unitType:i===5?'college_office':'academic_department',canCreate:true,count:i===0?3:0}));
            const categories=[{_id:id(30),collegeId:id(1),parentId:null,code:'1.0',name:'Academic & Instruction Processes',unitType:'academic_department',provisional:true,counts:{[id(10)]:3}},
                {_id:id(31),collegeId:id(1),parentId:id(30),code:'1.0.1',name:'Curriculum & Program Specifications',unitType:'academic_department',provisional:true,counts:{[id(10)]:3}}];
            const tree={campus:'USTP Claveria Campus',colleges:[{_id:id(1),name:'College of Engineering and Technology',code:'CET'}],units,categories,total:3,canManageOrganization:true};
            let docs=['file','digital-reference','physical-original'].map((recordType,i)=>({_id:id(i+50),title:['Authorized curriculum file','Controlled curriculum reference','Signed original register'][i],fileCode:'BROWSER-'+i,originalName:'curriculum.pdf',departmentId:id(10),collegeId:id(1),categoryId:id(31),departmentName:units[0].name,uploadedBy:'Authorized owner',recordType,versionNumber:1,createdAt:'2026-11-08T08:00:00Z',canManage:true,canDownload:i===0,downloadAllowed:i===0,visibility:'department',description:'Browser fixture only',documentReference:i===1?'QMS-CUR-001':null,physicalLocation:i===2?'Department cabinet A':null,signatureStatus:'not_verified',accessUserIds:[],sharedAuditIds:[]}));
            Object.defineProperty(window,'axios',{configurable:true,get(){return this.fixtureAxios},set(client){
                client.defaults.adapter=async config=>{
                    window.fixtureCalls.push({url:config.url,method:config.method,params:config.params});
                    let data;
                    if(config.url.endsWith('/tree'))data=tree;
                    else if(config.url.endsWith('/organization'))data={users:[{_id:id(90),name:'Authorized secretary',email:'browser@example.test',roleId:3,responsibility:'department_secretary',unitIds:[id(10)],collegeIds:[]}],responsibilities:['department_secretary','college_dean','college_secretary','cluster_administrator','faculty_staff']};
                    else if(config.url==='/api/v1/archives'){
                        const p=config.params||{};let filtered=docs.filter(doc=>(!p.search||(doc.title+' '+doc.documentReference).toLowerCase().includes(p.search.toLowerCase()))&&(!p.recordType||doc.recordType===p.recordType)&&(!p.unitId||doc.departmentId===p.unitId));
                        data={data:filtered,meta:{total:filtered.length,currentPage:1,lastPage:1}};
                    } else if(config.url.endsWith('/upload')){const doc={...docs[2],_id:id(99),title:config.data.get('title'),recordType:config.data.get('recordType')};docs.push(doc);data={data:doc};}
                    else if(config.method==='patch'||config.method==='delete'||config.method==='post')data={message:'Saved authorized change'};
                    else{const doc=docs.find(doc=>config.url.endsWith(doc._id))||docs[0];data={data:doc,versions:[doc],activity:[{action:'Viewed QMS document metadata',actor:'Authorized owner',at:doc.createdAt}]};}
                    return {data,status:200,statusText:'OK',headers:{},config};
                };this.fixtureAxios=client;
            }});
            window.fetch=async()=>new Response('%PDF-1.4'+String.fromCharCode(10)+'Preview fixture',{headers:{'Content-Type':'application/pdf'}});
        ` });
        for (const width of [1440, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 960, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'explorer.html')).href });
            await waitFor('!!window.Alpine && document.querySelectorAll(".qms-document").length===3');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Explorer must not overflow viewport');
            assert.equal(await evaluate('document.querySelectorAll(".qms-main .qms-folder").length'), 6, 'Five departments plus distinct Dean office');
            await evaluate('document.querySelector(".qms-main .qms-folder").click()');
            await waitFor('document.querySelector(".qms-categories .qms-folder strong").textContent.includes("Academic")');
            await evaluate('document.querySelector(".qms-categories .qms-folder").click()');
            await waitFor('document.querySelector(".qms-categories .qms-folder strong").textContent.includes("Curriculum")');
            await evaluate('document.querySelector(".qms-document .qms-doc-actions button").click()');
            await waitFor('document.querySelector("[x-ref=details]").open');
            await evaluate('document.querySelector("[x-ref=details] [data-evidence-preview]").click()');
            await waitFor('!!document.querySelector("#evidence-preview-modal iframe")');
            assert.equal(await evaluate('document.querySelector("[x-ref=details]").open && document.querySelector("#evidence-preview-modal").open'), true, 'Preview stacks over document details');
            await evaluate('document.querySelector("#evidence-preview-modal [data-evidence-preview-close]").click()');
            await waitFor('!document.querySelector("#evidence-preview-modal").open');
            assert.equal(await evaluate('document.querySelector("[x-ref=details]").open'), true, 'Closing preview preserves document details');
            await evaluate('document.querySelector("[x-ref=details] .qms-close").click();document.querySelector(".qms-heading .qms-gold").click()');
            await waitFor('document.querySelector("[x-ref=upload]").open');
            await evaluate(`(()=>{const app=Alpine.$data(document.querySelector('.qms-explorer'));app.form.recordType='physical-original';app.form.title='New signed original';app.form.physicalLocation='Department cabinet B';app.form.categoryId='000000000000000000000031';})()`);
            await delay(100);
            assert.equal(await evaluate('document.querySelector("[x-ref=file]").required'), false, 'Physical originals require no upload');
            assert.equal(await evaluate('document.querySelector("[x-ref=uploadForm]").checkValidity()'), true, 'Metadata-only record can submit');
            await evaluate('document.querySelector("[x-ref=uploadForm]").requestSubmit()');
            await waitFor('!document.querySelector("[x-ref=upload]").open');
            assert.equal(await evaluate('document.querySelectorAll(".qms-document").length'), 4, 'Saved record appears');
            await evaluate(`(()=>{const input=document.querySelector('.qms-search input');input.value='Controlled';input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
            await waitFor('document.querySelectorAll(".qms-document").length===1');
            assert.equal(await evaluate('document.querySelector(".qms-document h4").textContent'), 'Controlled curriculum reference');
            if ([1440,390].includes(width)) {
                const screenshot = await send('Page.captureScreenshot', { format:'png', captureBeyondViewport:false });
                fs.writeFileSync(path.join(fixtures,`explorer-${width}.png`),Buffer.from(screenshot.result.data,'base64'));
            }
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).manageOrganization()');
            await waitFor('document.querySelector("[x-ref=organization]").open');
            assert.equal(await evaluate('(()=>{const r=document.querySelector("[x-ref=organization]").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight})()'), true, 'Responsibilities dialog fits viewport');
            console.log(`PASS ${width}px: folders, categories, search, metadata-only save, stacked preview, responsive dialogs`);
        }
        assert.equal(exceptions.length,0,JSON.stringify(exceptions));
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error=>{console.error(error);process.exitCode=1});
