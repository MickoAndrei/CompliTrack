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
            window.fixtureCalls=[];window.holdArchivePage=true;
            const id=n=>String(n).padStart(24,'0');
            const units=['Agricultural and Biosystem Engineering','Environmental Engineering','Food Processing and Technology','Hospitality Management','Information Technology','Office of the Dean'].map((name,i)=>({_id:id(i+10),collegeId:id(1),name,unitType:i===5?'college_office':'academic_department',canCreate:true,count:i===0?3:0}));
            const categories=[{_id:id(30),collegeId:id(1),parentId:null,code:'1.0',name:'Academic & Instruction Processes',unitType:'academic_department',provisional:true,counts:{[id(10)]:3}},
                {_id:id(31),collegeId:id(1),parentId:id(30),code:'1.0.1',name:'Curriculum & Program Specifications',unitType:'academic_department',provisional:true,counts:{[id(10)]:3}}];
            const tree={campus:'USTP Claveria Campus',colleges:[{_id:id(1),name:'College of Engineering and Technology',code:'CET'}],units,categories,total:3,archiveStorageBytes:3072,canManageOrganization:true};
            const folderPermissions={view:true,create:true,rename:true,move:true,delete:true,manage:true,upload:true};
            units.forEach(unit=>unit.folderPermissions={...folderPermissions});
            categories.forEach(folder=>folder.folderPermissions=Object.fromEntries(units.map(unit=>[unit._id,{...folderPermissions}])));
            let docs=['file','digital-reference','physical-original'].map((recordType,i)=>({_id:id(i+50),title:['Authorized curriculum file','Controlled curriculum reference','Signed original register'][i],fileCode:'BROWSER-'+i,originalName:'curriculum.pdf',departmentId:id(10),collegeId:id(1),categoryId:id(31),departmentName:units[0].name,uploadedBy:'Authorized owner',recordType,versionNumber:1,createdAt:'2026-11-08T08:00:00Z',canManage:true,canEdit:i===0,canOpenEditor:i===0,canDownload:i===0,downloadAllowed:i===0,visibility:'department',description:'Browser fixture only',documentReference:i===1?'QMS-CUR-001':null,physicalLocation:i===2?'Department cabinet A':null,signatureStatus:'not_verified',accessUserIds:[],sharedAuditIds:[]}));
            Object.defineProperty(window,'axios',{configurable:true,get(){return this.fixtureAxios},set(client){
                client.defaults.adapter=async config=>{
                    window.fixtureCalls.push({url:config.url,method:config.method,params:config.params});
                    let data;
                    if(config.url.endsWith('/tree')){if(!window.treeReleased)await new Promise(resolve=>window.releaseTree=resolve);data=tree;}
                    else if(config.url.endsWith('/organization'))data={users:[{_id:id(90),name:'Authorized secretary',email:'browser@example.test',roleId:3,responsibility:'department_secretary',unitIds:[id(10)],collegeIds:[]}],responsibilities:['department_secretary','college_dean','college_secretary','cluster_administrator','faculty_staff']};
                    else if(config.url==='/api/v1/documents/audit-folders'){
                        if(window.failAuditArchive)throw {response:{status:404,data:{message:'Archive access changed'}}};
                        const fields=['planId','auditorId','campusId','collegeId','unitId'];
                        const nodes=[{_id:id(70),name:'Official Internal Quality Audit Plan',reference:'IQA-PLAN-2026-004',kind:'Audit Plan'},
                            {_id:id(80),name:'Assigned auditor',kind:'Assigned Auditor'},{_id:id(90),name:'Claveria',kind:'Campus'},
                            {_id:id(1),name:'College of Engineering and Technology',kind:'College'},{_id:id(10),name:units[0].name,kind:'Department'}];
                        const p=config.params||{};let depth=0,parent={};const breadcrumbs=[{key:'audit-root',name:'Audit Plans',path:{}}];
                        for(const field of fields){if(!p[field])break;parent={...parent,[field]:p[field]};breadcrumbs.push({key:field,name:nodes[depth].name,path:{...parent}});depth++;}
                        data={folders:depth===5?[]:[{...nodes[depth],path:{...parent,[fields[depth]]:nodes[depth]._id}}],breadcrumbs,leaf:depth===5};
                    }
                    else if(config.url==='/api/v1/documents'){
                        const p=config.params||{};
                        if(window.holdArchivePage)await new Promise(resolve=>window.releaseArchivePage=resolve);
                        if(window.failArchivePage)throw Error('Pagination fixture failure');
                        const source=window.paginationFixture?Array.from({length:25},(_,i)=>({...docs[0],_id:id(200+i),title:'Pagination document '+(i+1)})):docs;
                        let filtered=source.filter(doc=>(!p.search||(doc.title+' '+doc.documentReference).toLowerCase().includes(p.search.toLowerCase()))&&(!p.recordType||doc.recordType===p.recordType)&&(!p.unitId||doc.departmentId===p.unitId));
                        const page=window.paginationFixture?Number(window.canonicalArchivePage||p.page||1):1;
                        data={data:window.paginationFixture?filtered.slice((page-1)*12,page*12):filtered,meta:{total:filtered.length,currentPage:page,lastPage:window.paginationFixture?Math.max(1,Math.ceil(filtered.length/12)):1}};
                    } else if(config.url==='/api/v1/documents/folders'){
                        const input=typeof config.data==='string'?JSON.parse(config.data):config.data;
                        const folder={...categories[0],_id:id(95),parentId:input.parentId,name:input.name,departmentId:input.unitId,userManaged:true,counts:{}};
                        categories.push(folder);data={data:folder};
                    } else if(config.url.endsWith('/upload')){const doc={...docs[2],_id:id(99),title:config.data.get('title'),recordType:config.data.get('recordType')};docs.push(doc);data={data:doc};}
                    else if(config.url.endsWith('/save'))data={message:'Save requested. Waiting for CompliTrack to store the revision.'};
                    else if(config.url.endsWith('/editor'))data={sessionId:'fixture-session',scriptUrl:'data:text/javascript,void%200',config:{documentType:'pdf',document:{fileType:'pdf'},editorConfig:{mode:'edit'}}};
                    else if(config.url.endsWith('/status'))data={currentId:id(50),savedVersions:[]};
                    else if(config.method==='patch'||config.method==='delete'||config.method==='post')data={message:'Saved authorized change'};
                    else{const doc=docs.find(doc=>config.url.endsWith(doc._id))||docs[0];data={data:doc,versions:doc.canDownload?[{...doc,versionNumber:2},{...doc,_id:id(49),versionNumber:1}]:[doc],activity:[{action:'Viewed QMS document metadata',actor:'Authorized owner',at:doc.createdAt}]};}
                    return {data,status:200,statusText:'OK',headers:{},config};
                };this.fixtureAxios=client;
            }});
            window.fetch=async()=>new Response('%PDF-1.4'+String.fromCharCode(10)+'Preview fixture',{headers:{'Content-Type':'application/pdf'}});
            window.DocsAPI={DocEditor:class{constructor(id,config){window.fixtureEditorConfig=config;config.events.onAppReady();}destroyEditor(){window.fixtureEditorClosed=true;}}};
        ` });
        for (const width of [1440, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 960, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, 'explorer.html')).href });
            await waitFor('!!window.releaseTree');
            assert.equal(await evaluate('document.querySelector(".qms-loading-skeleton.qms-workspace").checkVisibility()'), true, 'Workspace skeleton appears while folders load');
            assert.equal(await evaluate('document.querySelector(".qms-workspace:not(.qms-loading-skeleton)").checkVisibility()'), false, 'Archive content stays hidden before data arrives');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Skeleton fits viewport');
            assert.equal(await evaluate('document.querySelectorAll(".qms-skeleton-view-nav .qms-skeleton-nav-item").length'), 5, 'Workspace skeleton matches the five document navigation views');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("grid")');
            await waitFor('!document.querySelector(".qms-skeleton-files").classList.contains("is-list")');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Grid skeleton fits viewport');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
            await waitFor('document.querySelector(".qms-skeleton-files").classList.contains("is-list")');
            if ([1440,390].includes(width)) {
                const screenshot = await send('Page.captureScreenshot', { format:'png', captureBeyondViewport:false });
                fs.writeFileSync(path.join(fixtures,`skeleton-${width}.png`),Buffer.from(screenshot.result.data,'base64'));
            }
            await evaluate('window.treeReleased=true;window.releaseTree()');
            await waitFor('!!window.releaseArchivePage');
            assert.equal(await evaluate('document.querySelector(".qms-loading-skeleton.qms-workspace").checkVisibility()'), true, 'Skeleton remains until records arrive');
            await evaluate('window.holdArchivePage=false;window.releaseArchivePage();window.releaseArchivePage=null');
            await waitFor('!!window.Alpine && document.querySelectorAll(".qms-document").length===3');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
            await waitFor('!document.querySelector(".qms-loading-skeleton.qms-workspace").checkVisibility()');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("grid")');
            await waitFor('!document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-documents").classList.contains("is-list")');
            assert.equal(await evaluate('localStorage.getItem("complitrack.qms.layout")'), 'grid', 'Layout preference persists without document metadata');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Loaded grid fits viewport');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
            await waitFor('document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-documents").classList.contains("is-list")');
            assert.equal(await evaluate('document.querySelector(".qms-loading-skeleton.qms-workspace").checkVisibility()'), false, 'Workspace skeleton clears after load');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-pagination button")].every(button=>button.disabled)'), true, 'Single-page controls stay visible and disabled');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Explorer must not overflow viewport');
            assert.equal(await evaluate('document.querySelectorAll(".qms-main .qms-folder").length'), 6, 'Five departments plus distinct Dean office');
            assert.equal(await evaluate('document.querySelector(".header-page-title").textContent.trim()'), 'QMS Documents');
            assert.equal(await evaluate('document.querySelector(".header-page-subtitle").textContent.trim()'), 'Browse authorized records by office, department, and document category.');
            assert.equal(await evaluate('document.querySelector(".qms-heading h2") === null'), true, 'Archive title appears only in the page header');
            assert.equal(await evaluate('document.querySelector("[aria-label=Back]").disabled && document.querySelector("[aria-label=Forward]").disabled && document.querySelector("[aria-label=" + JSON.stringify("Up one folder") + "]").disabled'), true, 'Navigation starts at the root');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-navigation svg")].every(e=>getComputedStyle(e).stroke!=="none" && e.getBoundingClientRect().width>=18)'), true, 'Navigation arrows have visible strokes and size');
            await evaluate('document.querySelector(".qms-main .qms-folder").click()');
            await waitFor('document.querySelector(".qms-categories .qms-folder strong").textContent.includes("Academic")');
            await evaluate('document.querySelector(".qms-categories .qms-folder").click()');
            await waitFor('document.querySelector(".qms-categories .qms-folder strong").textContent.includes("Curriculum")');
            const categoryLocation = await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).categoryId');
            await evaluate('document.querySelector("[aria-label=Back]").click()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).categoryId');
            await evaluate('document.querySelector("[aria-label=Forward]").click()');
            await waitFor(`Alpine.$data(document.querySelector(".qms-explorer")).categoryId === ${JSON.stringify(categoryLocation)}`);
            await evaluate('document.querySelector("[aria-label=" + JSON.stringify("Up one folder") + "]").click()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).categoryId');
            assert.equal(await evaluate('document.querySelector("[aria-label=Forward]").disabled'), true, 'New navigation clears the forward history');
            const requestsBeforeRefresh = await evaluate('window.fixtureCalls.length');
            await evaluate('document.querySelector("[aria-label=" + JSON.stringify("Refresh folder") + "]").click()');
            await waitFor(`window.fixtureCalls.length >= ${requestsBeforeRefresh + 2} && !Alpine.$data(document.querySelector('.qms-explorer')).loading`);
            await evaluate('document.querySelector(".qms-heading .qms-gold").click()');
            await waitFor('document.querySelector("#qms-new-menu").checkVisibility()');
            await evaluate('document.querySelector("#qms-new-menu button").click()');
            await waitFor('document.querySelector("[x-ref=folderDialog]").open');
            assert.equal(await evaluate('document.activeElement===document.querySelector("[x-ref=folderName]")'),true,'New Folder focuses its name field');
            assert.equal(await evaluate('document.querySelector("[x-ref=folderDialog]").getBoundingClientRect().width<=Math.min(460,innerWidth)'),true,'New Folder is compact and fits the viewport');
            await evaluate('(()=>{const input=document.querySelector("[x-ref=folderName]");input.value="AY 2026-2027";input.dispatchEvent(new Event("input",{bubbles:true}));document.querySelector("[x-ref=folderDialog] form").requestSubmit()})()');
            await waitFor('!document.querySelector("[x-ref=folderDialog]").open && !Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).categories.some(folder=>folder.name==="AY 2026-2027")'),true,'Created folder appears inside the current authorized location');
            assert.equal(await evaluate(`[...document.querySelectorAll('.qms-file-menu button')].some(button=>button.textContent.trim()==='View Versions')`), false, 'Document menu omits View Versions');
            await evaluate(`document.querySelector('.qms-menu-trigger').click();[...document.querySelectorAll('.qms-file-menu .qms-action-popover button')].find(button=>button.textContent.trim()==='Open').click()`);
            await waitFor('document.querySelector("[x-ref=editor]").open && !!window.fixtureEditorConfig');
            await waitFor('!document.querySelector("[data-editor-save]").checkVisibility()');
            assert.equal(await evaluate('document.querySelector("[x-ref=details]").open'), false, 'Open does not show document details');
            assert.equal(await evaluate('document.querySelector("[data-editor-save]").checkVisibility()'), false, 'Read-only Open hides Save');
            await evaluate('document.querySelector("[x-ref=editor] [data-editor-back]").click()');
            await waitFor('!document.querySelector("[x-ref=editor]").open');
            assert.equal(await evaluate('document.querySelector("[x-ref=details]").open'), false, 'Returning from Open leaves document details closed');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.editorSession=null;clearTimeout(app.editorPollTimer);})()');
            await evaluate(`[...document.querySelectorAll('.qms-file-menu .qms-action-popover button')].find(button=>button.textContent.trim()==='View Details').click()`);
            await waitFor('document.querySelector("[x-ref=details]").open');
            await evaluate('document.querySelector("[x-ref=details] [data-evidence-preview]").click()');
            await waitFor('!!document.querySelector("#evidence-preview-modal iframe")');
            assert.equal(await evaluate('document.querySelector("[x-ref=details]").open && document.querySelector("#evidence-preview-modal").open'), true, 'Preview stacks over document details');
            await evaluate('document.querySelector("#evidence-preview-modal [data-evidence-preview-close]").click()');
            await waitFor('!document.querySelector("#evidence-preview-modal").open');
            assert.equal(await evaluate('document.querySelector("[x-ref=details]").open'), true, 'Closing preview preserves document details');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).compareVersions()');
            await waitFor('document.querySelector("[x-ref=comparison]").open && document.querySelectorAll(".qms-comparison-pane iframe").length===2');
            assert.equal(await evaluate('document.querySelector("[x-ref=details]").open'),true,'Comparison preserves version history dialog');
            assert.equal(await evaluate('document.querySelector("[x-ref=compareLeft] iframe").title.startsWith("Version 1") && document.querySelector("[x-ref=compareRight] iframe").title.startsWith("Version 2")'),true,'Both revisions display independently');
            assert.equal(await evaluate('(()=>{const r=document.querySelector("[x-ref=comparison]").getBoundingClientRect();return r.width<=innerWidth&&r.height<=innerHeight;})()'),true,'Comparison fits viewport');
            await evaluate('document.querySelector("[x-ref=comparison] .qms-close").click()');
            await waitFor('document.querySelectorAll(".qms-comparison-pane iframe").length===0');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).editDocument()');
            await waitFor('document.querySelector("[x-ref=editor]").open && !!window.fixtureEditorConfig');
            assert.equal(await evaluate('window.fixtureEditorConfig.documentType'), 'pdf', 'PDF editing uses the document server');
            await waitFor('document.querySelector("[data-editor-save]").checkVisibility()');
            assert.equal(await evaluate(`(()=>{const editor=document.querySelector('[x-ref=editor]').getBoundingClientRect(),body=document.querySelector('[x-ref=editorBody]').getBoundingClientRect();return Math.abs(editor.width-innerWidth)<2 && Math.abs(editor.height-innerHeight)<2 && body.height>innerHeight*.6})()`), true, 'Editor fills the viewport and reserves most height for the document');
            await waitFor('document.querySelector("[x-ref=editor]").textContent.includes("Earlier versions are preserved")');
            assert.equal(await evaluate('document.querySelector("[x-ref=editor]").textContent.includes("Earlier versions are preserved")'), true, 'Editor explains revision preservation');
            assert.equal(await evaluate('document.querySelector("[data-editor-save]").checkVisibility()'), true, 'Editing exposes Save');
            await evaluate('document.querySelector("[data-editor-save]").click()');
            await waitFor(`Alpine.$data(document.querySelector('.qms-explorer')).editorMessage.includes('Waiting for CompliTrack')`);
            assert.equal(await evaluate('document.querySelector(".qms-editor-saved").checkVisibility()'), false, 'Save request does not falsely display Saved');
            await evaluate('document.querySelector("[x-ref=editor] [data-editor-back]").click()');
            await waitFor('!!window.fixtureEditorClosed');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.editorSession=null;clearTimeout(app.editorPollTimer);})()');
            await evaluate('document.querySelector("[x-ref=details] .qms-close").click();document.querySelector(".qms-heading .qms-gold").click()');
            await waitFor('document.querySelector("#qms-new-menu").checkVisibility()');
            await evaluate('document.querySelector("[data-qms-upload]").click()');
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
            await evaluate(`document.querySelector('[x-ref=organization]').close();window.paginationFixture=true;const app=Alpine.$data(document.querySelector('.qms-explorer'));app.search='Pagination';app.recordType='file';app.navigate('000000000000000000000001','000000000000000000000010','000000000000000000000031');`);
            await waitFor('document.querySelector(".qms-pagination .app-pagination-status").textContent==="Page 1 of 3"');
            assert.equal(await evaluate('document.querySelector(".qms-pagination button").disabled'), true);
            const documentColumns = await evaluate('getComputedStyle(document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-document")).gridTemplateColumns');
            await evaluate('window.holdArchivePage=true;document.querySelector(".qms-pagination button:last-child").click()');
            await waitFor('!!window.releaseArchivePage');
            await waitFor('document.querySelector(".qms-main > .qms-loading-skeleton").checkVisibility()');
            await waitFor('!document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-main > .qms-documents").checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".qms-main > .qms-loading-skeleton").checkVisibility()'), true, 'Document skeleton appears during pagination');
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-skeleton-record")).gridTemplateColumns'), documentColumns, 'Loading rows use the same columns as the active document list');
            assert.equal(await evaluate('document.querySelector(".qms-main > .qms-documents").checkVisibility()'), false, 'Old document cards hide while loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).page'), 1, 'Page changes only after success');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-pagination button")].every(button=>button.disabled)'), true, 'Both controls disable during requests');
            const requestCount=await evaluate('window.fixtureCalls.length');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).goToPage(2)');
            assert.equal(await evaluate('window.fixtureCalls.length'),requestCount,'Repeated page requests are ignored while loading');
            const params=await evaluate('window.fixtureCalls.at(-1).params');
            assert.equal(params.page,2);assert.equal(params.search,'Pagination');assert.equal(params.recordType,'file');assert.equal(params.unitId,'000000000000000000000010');assert.equal(params.categoryId,'000000000000000000000031');assert.equal(params.collegeId,'000000000000000000000001');
            await evaluate('window.holdArchivePage=false;window.releaseArchivePage();window.releaseArchivePage=null');
            await waitFor('document.querySelector(".qms-pagination .app-pagination-status").textContent==="Page 2 of 3"');
            assert.equal(await evaluate('document.querySelector(".qms-document h4").textContent'),'Pagination document 13');
            await evaluate('window.failArchivePage=true;document.querySelector(".qms-pagination button:last-child").click()');
            await waitFor('!!Alpine.$data(document.querySelector(".qms-explorer")).error');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).page'),2,'Failed requests preserve page');
            assert.equal(await evaluate('document.querySelector(".qms-document h4").textContent'),'Pagination document 13','Failed requests preserve records');
            await evaluate('window.failArchivePage=false;document.querySelector(".qms-pagination button:last-child").click()');
            await waitFor('document.querySelector(".qms-pagination .app-pagination-status").textContent==="Page 3 of 3"');
            assert.equal(await evaluate('document.querySelector(".qms-pagination button:last-child").disabled'),true,'Next stops at last page');
            await evaluate('document.querySelector(".qms-pagination button:first-child").click()');
            await waitFor('document.querySelector(".qms-pagination .app-pagination-status").textContent==="Page 2 of 3"');
            await evaluate('window.canonicalArchivePage=1;document.querySelector(".qms-pagination button:last-child").click()');
            await waitFor('document.querySelector(".qms-pagination .app-pagination-status").textContent==="Page 1 of 3"');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Pagination fits viewport');
            const requestsBeforeFolders=await evaluate('window.fixtureCalls.length');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.search="";app.tree={...app.tree,canManageOrganization:false,canViewDocuments:false,units:app.tree.units.map(unit=>({...unit,canCreate:false}))};app.navigate()})()');
            await waitFor('!document.querySelector(".qms-heading .qms-gold").checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".qms-heading .qms-button").checkVisibility()'),false,'Super Admin cannot manage responsibilities');
            assert.equal(await evaluate('document.querySelector(".qms-documents").checkVisibility()'),false,'Super Admin sees no files');
            assert.equal(await evaluate('document.querySelector(".qms-main .qms-folder").checkVisibility()'),true,'Super Admin still sees folders');
            assert.equal(await evaluate('window.fixtureCalls.length'),requestsBeforeFolders,'Folder-only view requests no file list');
            assert.equal(await evaluate('document.querySelector(".qms-summary").checkVisibility()'),true,'Super Admin sees archive aggregate statistics');
            assert.ok((await evaluate('document.querySelector(".qms-summary").textContent')).includes('3 KB'),'Storage usage is formatted in KB');
            console.log(`PASS ${width}px: matching pagination, boundaries, loading guard, filters, failed requests, server page state`);
            console.log(`PASS ${width}px: folders, categories, search, metadata-only save, stacked preview, responsive dialogs`);
        }
        for (const role of [0, 1, 2, 3]) {
            await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, role === 0 ? 'explorer.html' : `explorer-${role}.html`)).href });
            await waitFor('!!window.releaseTree');
            await evaluate('window.treeReleased=true;window.holdArchivePage=false;window.releaseTree()');
            await waitFor('!!window.Alpine && !Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
            for (const toggleWidth of [1440, 390]) {
                await send('Emulation.setDeviceMetricsOverride', { width:toggleWidth, height:960, deviceScaleFactor:1, mobile:toggleWidth<600 });
                await delay(50);
                const toggle = await evaluate(`(()=>{const group=document.querySelector('.qms-view-toggle');return {width:group.getBoundingClientRect().width,buttons:[...group.querySelectorAll('button')].map(button=>{const b=button.getBoundingClientRect(),icon=button.querySelector('svg').getBoundingClientRect();return {width:b.width,height:b.height,padding:getComputedStyle(button).padding,iconWidth:icon.width,iconHeight:icon.height,offsetX:Math.abs(b.x+b.width/2-icon.x-icon.width/2),offsetY:Math.abs(b.y+b.height/2-icon.y-icon.height/2)}})}})()`);
                assert.ok(toggle.width <= 90, `Role ${role}: compact layout control at ${toggleWidth}px`);
                assert.equal(await evaluate(`(()=>{const icons=[...document.querySelectorAll('.qms-view-nav svg')];return icons.length===5 && icons.every(icon=>{const box=icon.getBoundingClientRect(),path=icon.querySelector('path');return box.width===18 && box.height===18 && getComputedStyle(icon).stroke!=='none' && path.getTotalLength()>0})})()`), true, `Role ${role}: all five navigation icons render consistently at ${toggleWidth}px`);
                for (const button of toggle.buttons) {
                    assert.equal(button.width, toggleWidth < 700 ? 30 : 38, `Role ${role}: layout button width`);
                    assert.equal(button.height, toggleWidth < 700 ? 30 : 32, `Role ${role}: layout button height`);
                    assert.equal(button.padding,'0px', `Role ${role}: layout control ignores form padding`);
                    assert.equal(button.iconWidth,18);assert.equal(button.iconHeight,18);
                    assert.ok(button.offsetX < 1 && button.offsetY < 1, `Role ${role}: layout icon is centered`);
                }
                await evaluate(`document.querySelector('[aria-label="Grid view"]').click()`);
                await waitFor(`document.querySelector('[aria-label="Grid view"]').getAttribute('aria-pressed')==='true'`);
                await evaluate(`document.querySelector('[aria-label="List view"]').click()`);
                await waitFor(`document.querySelector('[aria-label="List view"]').getAttribute('aria-pressed')==='true'`);
            }
            await evaluate(`(()=>{const app=Alpine.$data(document.querySelector('.qms-explorer'));app.navigate('000000000000000000000001','000000000000000000000010','000000000000000000000031')})()`);
            await waitFor('Alpine.$data(document.querySelector(".qms-explorer")).compactPath');
            await waitFor('!!document.querySelector(".qms-menu-trigger svg")');
            for (const layout of ['grid', 'list']) {
                await evaluate(`Alpine.$data(document.querySelector('.qms-explorer')).setLayout('${layout}')`);
                await delay(50);
                assert.equal(await evaluate(`(()=>{const button=document.querySelector('.qms-menu-trigger'),b=button.getBoundingClientRect(),i=button.querySelector('svg').getBoundingClientRect();return getComputedStyle(button).padding==='0px' && Math.abs(b.x+b.width/2-i.x-i.width/2)<1 && Math.abs(b.y+b.height/2-i.y-i.height/2)<1})()`), true, `Role ${role}: document menu icon centered within its ${layout} button`);
            }
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-navigation svg")].every(e=>getComputedStyle(e).stroke!=="none" && e.getBoundingClientRect().width>=18)'), true, `Role ${role}: visible navigation icons`);
            await waitFor('document.querySelector(".qms-breadcrumb-overflow").checkVisibility()');
            assert.equal(await evaluate('Math.round(document.querySelector(".qms-breadcrumbs").getBoundingClientRect().height)'), 38, `Role ${role}: compact path box height`);
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-nav-button")].every(e=>e.getBoundingClientRect().width===34 && e.getBoundingClientRect().height===34)'), true, `Role ${role}: compact navigation buttons`);
            assert.equal(await evaluate('document.querySelectorAll(".qms-breadcrumbs > .qms-crumb").length'), 1, 'Long paths show only the current folder');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, `Role ${role}: long path fits`);
            await evaluate('document.querySelector(".qms-breadcrumb-overflow summary").click()');
            assert.ok(await evaluate('document.querySelectorAll(".qms-breadcrumb-menu button").length >= 3'), 'Collapsed parent folders remain accessible');
            await evaluate('document.querySelector(".qms-breadcrumb-menu button").click()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).collegeId');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).compactPath');
            console.log(`PASS role ${role}: navigation icons, collapsed breadcrumb, ancestor navigation and root expansion`);
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).switchView("audit")');
            await waitFor('document.querySelector(".qms-audit-browser .qms-folder strong")?.textContent === "Official Internal Quality Audit Plan"');
            assert.equal(await evaluate('document.querySelector(".qms-audit-browser").textContent.includes("IQA-PLAN-2026-004")'),true,'Audit folder uses the official plan reference');
            for (let level=0;level<5;level++) {
                await evaluate('document.querySelector(".qms-audit-browser .qms-folder").click()');
                await waitFor(`Object.keys(Alpine.$data(document.querySelector('.qms-explorer')).archivePath).length===${level+1} && !Alpine.$data(document.querySelector('.qms-explorer')).loading`);
            }
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).archiveLeaf'),true,'Plan → auditor → campus → college → department opens authorized evidence');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).breadcrumbs.length'),6,'Breadcrumb retains the full plan hierarchy');
            assert.equal(await evaluate('document.querySelector(".qms-document [data-evidence-preview]").href.includes("planId=000000000000000000000070")'),true,'Preview links retain the selected audit context');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).navigateUp()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).archiveLeaf && !Alpine.$data(document.querySelector(".qms-explorer")).loading');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).navigateHistory(-1)');
            await waitFor('Alpine.$data(document.querySelector(".qms-explorer")).archiveLeaf && !Alpine.$data(document.querySelector(".qms-explorer")).loading');
            await evaluate('window.failAuditArchive=true;Alpine.$data(document.querySelector(".qms-explorer")).refreshLocation()');
            await waitFor('Alpine.$data(document.querySelector(".qms-explorer")).error.includes("Archive access changed")');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).documents.length'),0,'Revocation/error clears previously displayed audit evidence');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Plan hierarchy fits a narrow viewport');
            console.log(`PASS role ${role}: official plan hierarchy, path navigation, contextual preview URLs and stale evidence clearing`);
        }
        assert.equal(exceptions.length,0,JSON.stringify(exceptions));
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error=>{console.error(error);process.exitCode=1});
