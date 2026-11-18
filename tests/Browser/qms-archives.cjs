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
        await send('Page.bringToFront');
        await send('Emulation.setFocusEmulationEnabled', { enabled: true });
        await send('Page.addScriptToEvaluateOnNewDocument', { source: `
            window.fixtureCalls=[];window.holdArchivePage=true;window.failTree=location.search.includes('fail-tree');
            const id=n=>String(n).padStart(24,'0');
            const units=['Agricultural and Biosystem Engineering','Environmental Engineering','Food Processing and Technology','Hospitality Management','Information Technology','Office of the Dean'].map((name,i)=>({_id:id(i+10),collegeId:id(1),name,unitType:i===5?'college_office':'academic_department',canCreate:true,count:i===0?3:0}));
            const categories=[{_id:id(30),collegeId:id(1),parentId:null,code:'1.0',name:'Academic & Instruction Processes',unitType:'academic_department',provisional:true,counts:{[id(10)]:3}},
                {_id:id(31),collegeId:id(1),parentId:id(30),code:'1.0.1',name:'Curriculum & Program Specifications',unitType:'academic_department',provisional:true,counts:{[id(10)]:3}}];
            const tree={campus:'USTP Claveria Campus',colleges:[{_id:id(1),name:'College of Engineering and Technology',code:'CET'}],units,categories,total:3,archiveStorageBytes:3072};
            const folderPermissions={view:true,create:true,rename:true,move:true,delete:true,manage:true,upload:true};
            units.forEach(unit=>unit.folderPermissions={...folderPermissions});
            categories.forEach(folder=>folder.folderPermissions=Object.fromEntries(units.map(unit=>[unit._id,{...folderPermissions}])));
            tree.homeUnitId=id(10);tree.departmentUnitIds=[id(10)];tree.assignedAudits=[{_id:id(85),name:'Assigned review',status:'draft'}];
            tree.folderOptions={leader:true,units:units.map(u=>({_id:u._id,name:u.name})),assignments:[{_id:id(85),name:'Real assignment',unitId:id(10),auditorId:id(80),canNotes:true}]};
            tree.repositoryFolders=[{_id:id(100),name:'Manually named cycle',collegeId:id(1),parentId:null,folderPolicy:'administration',auditRelated:true,ownerName:'CET',updatedAt:'2026-11-16T08:00:00Z',permissions:folderPermissions},
                {_id:id(101),name:'Department evidence',collegeId:id(1),departmentId:id(10),parentId:id(100),folderPolicy:'department',auditRelated:true,permissions:folderPermissions},
                {_id:id(102),name:'Assigned audit evidence',collegeId:id(1),departmentId:id(10),parentId:id(100),folderPolicy:'evidence',auditRelated:true,auditAssignmentId:id(85),permissions:folderPermissions}];
            let docs=['file','digital-reference','physical-original'].map((recordType,i)=>({_id:id(i+50),title:['Authorized curriculum file','Controlled curriculum reference','Signed original register'][i],fileCode:'BROWSER-'+i,originalName:'curriculum.pdf',departmentId:id(10),collegeId:id(1),categoryId:id(31),departmentName:units[0].name,uploadedBy:'Authorized owner',recordType,versionNumber:1,createdAt:'2026-11-08T08:00:00Z',canManage:true,canEdit:i===0,canOpenEditor:i===0,canDownload:i===0,downloadAllowed:i===0,visibility:'department',description:'Browser fixture only',documentReference:i===1?'QMS-CUR-001':null,physicalLocation:i===2?'Department cabinet A':null,signatureStatus:'not_verified',accessUserIds:[],sharedAuditIds:[]}));
            Object.defineProperty(window,'axios',{configurable:true,get(){return this.fixtureAxios},set(client){
                client.defaults.adapter=async config=>{
                    window.fixtureCalls.push({url:config.url,method:config.method,params:config.params});
                    let data;
                    if(config.url.endsWith('/tree')){if(!window.treeReleased)await new Promise(resolve=>window.releaseTree=resolve);if(window.failTree)throw Error('Tree failure');tree.canManageOrganization=document.body.dataset.portalRole==='1';data=JSON.parse(JSON.stringify(tree));}
                    else if(config.url.endsWith('/organization'))data={users:[{_id:id(90),name:'Authorized department head',email:'browser@example.test',roleId:3,responsibility:'department_head',unitIds:[id(10)],collegeIds:[]}],responsibilities:['department_head','college_dean','college_secretary','cluster_administrator','faculty_staff']};
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
                        if(window.holdArchivePage)await new Promise((resolve,reject)=>{window.releaseArchivePage=resolve;config.signal?.addEventListener('abort',()=>reject(Error('Cancelled fixture')),{once:true});});
                        if(window.failArchivePage)throw Error('Pagination fixture failure');
                        const source=window.paginationFixture?Array.from({length:25},(_,i)=>({...docs[0],_id:id(200+i),title:'Pagination document '+(i+1)})):docs;
                        let filtered=source.filter(doc=>(!p.search||(doc.title+' '+doc.documentReference).toLowerCase().includes(p.search.toLowerCase()))&&(!p.recordType||doc.recordType===p.recordType)&&(!p.unitId||doc.departmentId===p.unitId));
                        const page=window.paginationFixture?Number(window.canonicalArchivePage||p.page||1):1;
                        data={data:window.paginationFixture?filtered.slice((page-1)*12,page*12):filtered,meta:{total:filtered.length,currentPage:page,lastPage:window.paginationFixture?Math.max(1,Math.ceil(filtered.length/12)):1}};
                    } else if(config.url.startsWith('/api/v1/documents/folders/') && config.url.endsWith('/star')) {
                        const input=typeof config.data==='string'?JSON.parse(config.data):config.data;
                        const folder=[...tree.repositoryFolders,...tree.categories,...tree.units].find(f=>config.url.includes(f._id));
                        folder.isStarred=input.starred;data={isStarred:input.starred};
                    } else if(config.url.startsWith('/api/v1/documents/folders/') && config.method==='get') {
                        const folder=[...tree.repositoryFolders,...tree.categories,...tree.units].find(f=>config.url.endsWith(f._id));
                        data={data:{name:folder.name,type:'Folder',ownerName:folder.ownerName||'CET',location:'Authorized repository',createdAt:'2026-11-16T08:00:00Z',accessSummary:'Automatic organizational and audit scope.'}};
                    } else if(config.url==='/api/v1/documents/folders'){
                        const input=typeof config.data==='string'?JSON.parse(config.data):config.data;
                        const folder={...categories[0],_id:id(95),parentId:input.parentId,name:input.name,departmentId:input.unitId,userManaged:true,counts:{}};
                        if(input.folderPolicy)tree.repositoryFolders.push({...folder,folderPolicy:input.folderPolicy,permissions:folderPermissions});
                        else categories.push(folder);data={data:folder};
                    } else if(config.url === '/api/v1/compliance-checks'){data={data:[],meta:{lastPage:1}};} else if(config.url.endsWith('/upload')){const doc={...docs[2],_id:id(99),title:config.data.get('title'),recordType:config.data.get('recordType')};docs.push(doc);data={data:doc};}
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
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".qms-skeleton-sidebar")).overflowY'), 'hidden', 'The file sidebar has no scrollbar while its skeleton loads');
            assert.equal(await evaluate('document.querySelectorAll(".qms-skeleton-view-nav .qms-skeleton-nav-item").length'), 7, 'Workspace skeleton matches the available document navigation views');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("grid")');
            await waitFor('!document.querySelector(".qms-skeleton-files").classList.contains("is-list")');
            assert.equal(await evaluate('Math.round(document.querySelector(".qms-skeleton-folder").getBoundingClientRect().height)'),88,'Grid folder placeholders match the current compact folder cards');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Grid skeleton fits viewport');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
            await delay(30);
            assert.equal(await evaluate('Math.round(document.querySelector(".qms-skeleton-folder").getBoundingClientRect().height)'),60,'List folder placeholders match the current folder row height');
            assert.equal(await evaluate('document.querySelector(".qms-loading-skeleton.qms-workspace .qms-skeleton-files").checkVisibility()'),false,'Folder loading does not add unrelated file placeholders');
            assert.equal(await evaluate('Math.round(document.querySelector(".qms-skeleton-new-folder").getBoundingClientRect().height)'),36,'Loading controls retain the compact creation button height');
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
            const expandedMainWidth = await evaluate('document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-main").getBoundingClientRect().width');
            assert.equal(await evaluate('(()=>{const label=document.querySelector(".qms-sidebar-controls .qms-nav-caption").getBoundingClientRect(),button=document.querySelector(".qms-sidebar-toggle").getBoundingClientRect();return Math.abs(label.top+label.height/2-button.top-button.height/2)<1})()'),true,'Files label and collapse icon share a vertical center');
            await evaluate('document.querySelector(".qms-sidebar-toggle").click()');
            await waitFor('document.querySelector(".qms-sidebar-toggle").getAttribute("aria-expanded")==="false"');
            assert.equal(await evaluate('document.querySelector(".qms-view-nav").checkVisibility()'),false,'Collapsed Files navigation is hidden');
            assert.equal(await evaluate('document.querySelector(".qms-sidebar-toggle").checkVisibility()'),true,'Expand control remains available');
            assert.equal(await evaluate('document.querySelector(".qms-sidebar-toggle").getAttribute("aria-label")'),'Expand files sidebar');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Collapsed sidebar fits viewport');
            if (width > 700) assert.ok(await evaluate('document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-main").getBoundingClientRect().width') > expandedMainWidth,'Collapsing Files gives the listing more horizontal space');
            await evaluate('document.querySelector(".qms-sidebar-toggle").focus()');
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,text:'\r'});
            await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
            await waitFor('document.querySelector(".qms-sidebar-toggle").getAttribute("aria-expanded")==="true"');
            assert.equal(await evaluate('document.querySelector(".qms-view-nav").checkVisibility()'),true,'Keyboard expansion restores Files navigation');
            assert.equal(await evaluate('window.fixtureCalls.filter(c=>c.url.endsWith("/tree")).length'),1,'Initialize tree once');
            assert.equal(await evaluate('window.fixtureCalls.filter(c=>c.url==="/api/v1/documents").length'),1,'Load only the active view initially');
            assert.equal(await evaluate('window.fixtureCalls.some(c=>c.url.includes("document-editor"))'),false,'ONLYOFFICE stays lazy');
            await waitFor('!document.querySelector(".qms-loading-skeleton.qms-workspace").checkVisibility()');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("grid")');
            await waitFor('!document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-documents").classList.contains("is-list")');
            if (width === 1440) {
                const expandedGridColumns = await evaluate('getComputedStyle(document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-folder-grid")).gridTemplateColumns.split(" ").length');
                await evaluate('document.querySelector(".qms-sidebar-toggle").click()');
                await waitFor('document.querySelector(".qms-sidebar-toggle").getAttribute("aria-expanded")==="false"');
                assert.ok(await evaluate('getComputedStyle(document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-folder-grid")).gridTemplateColumns.split(" ").length') > expandedGridColumns,'Folder grid adds a column when collapsing Files creates enough room');
                await evaluate('document.querySelector(".qms-sidebar-toggle").click();Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
                await send('Emulation.setDeviceMetricsOverride',{width:1200,height:1000,deviceScaleFactor:1,mobile:false});
                await waitFor('!document.querySelector(".qms-main .qms-folder-row-owner").checkVisibility()');
                await evaluate('document.querySelector(".qms-sidebar-toggle").click()');
                await waitFor('document.querySelector(".qms-main .qms-folder-row-owner").checkVisibility()');
                const columnAlignment = await evaluate('(()=>{const workspace=document.querySelector(".qms-workspace:not(.qms-loading-skeleton)"),header=workspace.querySelector(".qms-folder-row-heading"),row=workspace.querySelector(".qms-folder");return {header:getComputedStyle(header).gridTemplateColumns,row:getComputedStyle(row).gridTemplateColumns}})()');
                assert.equal(columnAlignment.header,columnAlignment.row,'Folder headings and rows adjust together after collapse');
                await evaluate('document.querySelector(".qms-sidebar-toggle").click();Alpine.$data(document.querySelector(".qms-explorer")).setLayout("grid")');
                await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
                console.log('PASS sidebar reflow: grid adds columns and list metadata returns as pane width increases');
            }
            assert.equal(await evaluate('localStorage.getItem("complitrack.qms.layout")'), 'grid', 'Layout preference persists without document metadata');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Loaded grid fits viewport');
            assert.equal(await evaluate('document.scrollingElement.scrollHeight<=innerHeight'),true,'QMS uses internal scrolling instead of an outer page scrollbar');
            assert.equal(await evaluate('document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-main").clientHeight>0'),true,'The listing keeps usable scrollable height');
            assert.equal(await evaluate('document.querySelector(".qms-location-search input").placeholder'), 'Search My Drive', 'Search follows the current address');
            assert.equal(await evaluate('document.querySelector(".qms-location-bar .qms-address-home").checkVisibility()'), true, 'Address bar has an accessible Home button');
            if ([390, 1440].includes(width)) {
                const capture = await send('Page.captureScreenshot', { format:'png', captureBeyondViewport:false });
                fs.writeFileSync(path.join(fixtures,`explorer-toolbar-${width}.png`),Buffer.from(capture.result.data,'base64'));
            }
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
            await waitFor('document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-documents").classList.contains("is-list")');
            assert.equal(await evaluate('document.querySelector(".qms-loading-skeleton.qms-workspace").checkVisibility()'), false, 'Workspace skeleton clears after load');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-pagination:not(.qms-folder-pagination) button")].every(button=>button.disabled)'), true, 'Single-page controls stay visible and disabled');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Explorer must not overflow viewport');
            assert.equal(await evaluate('document.querySelectorAll(".qms-main .qms-folder").length'), 7, 'My Drive combines the manual repository and authorized organizational folders');
            assert.equal(await evaluate('document.querySelector(".qms-documents").checkVisibility()'),false,'My Drive landing shows folders without document entries');
            assert.equal(await evaluate('document.querySelector(".qms-doc-header").checkVisibility()'),false,'My Drive landing omits the Documents heading');
            assert.equal(await evaluate('document.querySelector(".qms-main > .qms-filter-row").checkVisibility()'),false,'My Drive landing omits the authorized-record count');
            assert.equal(await evaluate('document.querySelector(".qms-pagination:not(.qms-folder-pagination)").checkVisibility()'),false,'My Drive landing omits document pagination');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
            await delay(40);
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-folder-row-heading,.qms-list-head")].filter(heading=>heading.checkVisibility()).length'),1,'Folder and document sections share a single visible set of column headings');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-main .qms-folder")].every(row=>row.getBoundingClientRect().height>=60 && row.getBoundingClientRect().height<=92)'),true,'Folder rows stay compact while allowing full modified timestamps to wrap');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-main .qms-folder-row-date")].filter(date=>date.checkVisibility()).every(date=>getComputedStyle(date).whiteSpace==="normal" && date.scrollWidth<=date.clientWidth && date.scrollHeight<=date.clientHeight)'),true,'Visible modified dates fit without truncation');
            await evaluate('document.querySelector(".qms-folder-row-heading button").click()');
            await waitFor('document.querySelector(".qms-main > .qms-folders .qms-folder strong").textContent === "Office of the Dean"');
            await evaluate('document.querySelector(".qms-folder-row-heading button").click()');
            await waitFor('document.querySelector(".qms-main > .qms-folders .qms-folder strong").textContent === "Agricultural and Biosystem Engineering"');
            assert.equal(await evaluate('document.querySelector(".header-page-title").textContent.trim()'), 'QMS Documents');
            assert.equal(await evaluate('document.querySelector(".header-page-subtitle").textContent.trim()'), 'Browse authorized records by office, department, and document category.');
            assert.equal(await evaluate('document.querySelector(".qms-heading h2") === null'), true, 'QMS title appears only in the page header');
            assert.equal(await evaluate('document.querySelector("[aria-label=Back]").disabled && document.querySelector("[aria-label=Forward]").disabled && document.querySelector("[aria-label=" + JSON.stringify("Up one folder") + "]").disabled'), true, 'Navigation starts at the root');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-navigation svg")].every(e=>getComputedStyle(e).stroke!=="none" && e.getBoundingClientRect().width>=18)'), true, 'Navigation arrows have visible strokes and size');
            await evaluate('window.holdArchivePage=true;document.querySelector(".qms-main > .qms-folders .qms-folder").click()');
            await waitFor('!!window.releaseArchivePage');
            assert.equal(await evaluate('document.querySelector(".qms-main > .qms-loading-skeleton").checkVisibility()'),false,'Navigating between folders does not flash stale file-card placeholders');
            await evaluate('window.holdArchivePage=false;window.releaseArchivePage();window.releaseArchivePage=null');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
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
            await waitFor('document.querySelector("[x-ref=folderDialog]").open');
            assert.equal(await evaluate('document.activeElement===document.querySelector("[x-ref=folderName]")'),true,'New Folder focuses its name field');
            assert.equal(await evaluate('document.querySelector("[x-ref=folderDialog]").getBoundingClientRect().width<=Math.min(710,innerWidth)'),true,'New Folder follows the design and fits the viewport');
            await evaluate('(()=>{const input=document.querySelector("[x-ref=folderName]");input.value="AY 2026-2027";input.dispatchEvent(new Event("input",{bubbles:true}));document.querySelector("[x-ref=folderDialog] form").requestSubmit()})()');
            await waitFor('!document.querySelector("[x-ref=folderDialog]").open && !Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).categories.some(folder=>folder.name==="AY 2026-2027")'),true,'Created folder appears inside the current authorized location');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).navigate("000000000000000000000001","000000000000000000000010","000000000000000000000031")');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading && document.querySelector(".qms-documents").checkVisibility()');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).browsingFolders'),false,'Leaf folders retain access to their documents');
            await delay(40);
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-folder-row-heading")].some(heading=>heading.checkVisibility())'),false,'Leaf folders do not show an empty folder table heading');
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
            await evaluate('document.querySelector("[x-ref=details] .qms-close").click()');
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
            await evaluate(`document.querySelector('[x-ref=organization]').close();window.paginationFixture=true;const app=Alpine.$data(document.querySelector('.qms-explorer'));app.search='Pagination';app.recordType='file';app.load(true);`);
            await waitFor('document.querySelector(".qms-pagination:not(.qms-folder-pagination) .app-pagination-status").textContent==="Page 1 of 3"');
            assert.equal(await evaluate('document.querySelector(".qms-pagination:not(.qms-folder-pagination) button").disabled'), true);
            const documentColumns = await evaluate('getComputedStyle(document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-document")).gridTemplateColumns');
            await evaluate('window.holdArchivePage=true;document.querySelector(".qms-pagination:not(.qms-folder-pagination) button:last-child").click()');
            await waitFor('!!window.releaseArchivePage');
            await waitFor('document.querySelector(".qms-main > .qms-loading-skeleton").checkVisibility()');
            await waitFor('!document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-main > .qms-documents").checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".qms-main > .qms-loading-skeleton").checkVisibility()'), true, 'Document skeleton appears during pagination');
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-skeleton-record")).gridTemplateColumns'), documentColumns, 'Loading rows use the same columns as the active document list');
            assert.equal(await evaluate('document.querySelector(".qms-main > .qms-documents").checkVisibility()'), false, 'Old document cards hide while loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).page'), 1, 'Page changes only after success');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-pagination:not(.qms-folder-pagination) button")].every(button=>button.disabled)'), true, 'Both controls disable during requests');
            const requestCount=await evaluate('window.fixtureCalls.length');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).goToPage(2)');
            assert.equal(await evaluate('window.fixtureCalls.length'),requestCount,'Repeated page requests are ignored while loading');
            const params=await evaluate('window.fixtureCalls.at(-1).params');
            assert.equal(params.page,2);assert.equal(params.search,'Pagination');assert.equal(params.recordType,'file');assert.equal(params.unitId,'000000000000000000000010');assert.equal(params.categoryId,'000000000000000000000031');assert.equal(params.collegeId,'000000000000000000000001');
            await evaluate('window.holdArchivePage=false;window.releaseArchivePage();window.releaseArchivePage=null');
            await waitFor('document.querySelector(".qms-pagination:not(.qms-folder-pagination) .app-pagination-status").textContent==="Page 2 of 3"');
            assert.equal(await evaluate('document.querySelector(".qms-document h4").textContent'),'Pagination document 13');
            await evaluate('window.failArchivePage=true;document.querySelector(".qms-pagination:not(.qms-folder-pagination) button:last-child").click()');
            await waitFor('!!Alpine.$data(document.querySelector(".qms-explorer")).error');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).page'),2,'Failed requests preserve page');
            assert.equal(await evaluate('document.querySelector(".qms-document h4").textContent'),'Pagination document 13','Failed requests preserve records');
            await evaluate('window.failArchivePage=false;document.querySelector(".qms-pagination:not(.qms-folder-pagination) button:last-child").click()');
            await waitFor('document.querySelector(".qms-pagination:not(.qms-folder-pagination) .app-pagination-status").textContent==="Page 3 of 3"');
            assert.equal(await evaluate('document.querySelector(".qms-pagination:not(.qms-folder-pagination) button:last-child").disabled'),true,'Next stops at last page');
            await evaluate('document.querySelector(".qms-pagination:not(.qms-folder-pagination) button:first-child").click()');
            await waitFor('document.querySelector(".qms-pagination:not(.qms-folder-pagination) .app-pagination-status").textContent==="Page 2 of 3"');
            await evaluate('window.canonicalArchivePage=1;document.querySelector(".qms-pagination:not(.qms-folder-pagination) button:last-child").click()');
            await waitFor('document.querySelector(".qms-pagination:not(.qms-folder-pagination) .app-pagination-status").textContent==="Page 1 of 3"');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Pagination fits viewport');
            const requestsBeforeFolders=await evaluate('window.fixtureCalls.length');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.search="";app.tree={...app.tree,canManageOrganization:false,canViewDocuments:false,repositoryFolders:app.tree.repositoryFolders.map(folder=>({...folder,currentCycle:!folder.parentId})),units:app.tree.units.map(unit=>({...unit,canCreate:false}))};app.navigate()})()');
            await waitFor('!document.querySelector(".qms-heading .qms-gold").checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".qms-heading .qms-button").checkVisibility()'),false,'Super Admin cannot manage responsibilities');
            await waitFor('!document.querySelector(".qms-documents").checkVisibility()');
            assert.equal(await evaluate('document.querySelector(".qms-main .qms-folder").checkVisibility()'),true,'Super Admin still sees folders');
            assert.equal(await evaluate('window.fixtureCalls.length'),requestsBeforeFolders,'Folder-only view requests no file list');
            assert.equal(await evaluate('document.querySelector(".qms-location-search input").checkVisibility()'),true,'Folder-only users retain the search bar');
            assert.equal(await evaluate('document.querySelector(".qms-location-search input").getAttribute("aria-label")'),'Search authorized folders');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.search="Manually";void app.load(true)})()');
            await waitFor('Alpine.$data(document.querySelector(".qms-explorer")).repositoryChildren.length===1');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).repositoryChildren[0].name'),'Manually named cycle','Folder-only search filters the current authorized cycle');
            assert.equal(await evaluate('window.fixtureCalls.length'),requestsBeforeFolders,'Folder search grants no document API access');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).search=""');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).navExpanded=true');
            await delay(30);
            assert.equal(await evaluate('document.querySelector(".qms-summary").checkVisibility()'),true,'Super Admin sees archive aggregate statistics in the file navigation');
            assert.ok((await evaluate('document.querySelector(".qms-summary").textContent')).includes('3 KB'),'Storage usage is formatted in KB');
            console.log(`PASS ${width}px: matching pagination, boundaries, loading guard, filters, failed requests, server page state`);
            console.log(`PASS ${width}px: folders, categories, search, metadata-only save, stacked preview, responsive dialogs`);
        }
        let sharedControlStyles;
        for (const role of [0, 1, 2, 3]) {
            await send('Emulation.setDeviceMetricsOverride', {width:956,height:360,deviceScaleFactor:1,mobile:false});
            await send('Page.navigate', { url: pathToFileURL(path.join(fixtures, role === 0 ? 'explorer.html' : `explorer-${role}.html`)).href });
            await waitFor('!!window.releaseTree');
            await evaluate('window.treeReleased=true;window.holdArchivePage=false;window.releaseTree()');
            await waitFor('!!window.Alpine && !Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-view-nav button")].some(button=>button.textContent.trim()==="My Department")'),![0,1].includes(role),'Super Admin and ATL omit My Department');
            const controlStyles = await evaluate(`Object.fromEntries(['.qms-view-nav button','.qms-view-nav button:nth-of-type(2)','.qms-crumb button','.qms-new-control>.qms-gold','.qms-category-header h3'].map(selector=>{const el=document.querySelector(selector),s=getComputedStyle(el);return [selector,{font:s.fontFamily,size:s.fontSize,weight:s.fontWeight,padding:s.padding,radius:s.borderRadius,lineHeight:s.lineHeight}]}))`);
            if (!sharedControlStyles) sharedControlStyles = controlStyles;
            else assert.deepEqual(controlStyles,sharedControlStyles,`Role ${role}: QMS typography, spacing and button shapes match at the screenshot viewport`);
            assert.equal(await evaluate(`(()=>{const el=document.querySelector('.qms-view-nav button:nth-of-type(2)>span:last-child');return el.getBoundingClientRect().height<=parseFloat(getComputedStyle(el).lineHeight)+1})()`),true,`Role ${role}: My Department stays on one line`);
            assert.equal(await evaluate('document.scrollingElement.scrollHeight<=innerHeight && document.querySelector("#main-content").scrollHeight<=document.querySelector("#main-content").clientHeight'),true,`Role ${role}: no scrollbar outside QMS`);
            for (const toggleWidth of [1440, 390]) {
                await send('Emulation.setDeviceMetricsOverride', { width:toggleWidth, height:960, deviceScaleFactor:1, mobile:toggleWidth<600 });
                await delay(50);
                const toggle = await evaluate(`(()=>{const group=document.querySelector('.qms-view-toggle');return {width:group.getBoundingClientRect().width,buttons:[...group.querySelectorAll('button')].map(button=>{const b=button.getBoundingClientRect(),icon=button.querySelector('svg').getBoundingClientRect();return {width:b.width,height:b.height,padding:getComputedStyle(button).padding,iconWidth:icon.width,iconHeight:icon.height,offsetX:Math.abs(b.x+b.width/2-icon.x-icon.width/2),offsetY:Math.abs(b.y+b.height/2-icon.y-icon.height/2)}})}})()`);
                assert.ok(toggle.width <= 90, `Role ${role}: compact layout control at ${toggleWidth}px`);
                assert.equal(await evaluate(`(()=>{const button=document.querySelector('.qms-address-home'),b=button.getBoundingClientRect(),icon=button.querySelector('svg').getBoundingClientRect(),address=button.closest('.qms-breadcrumbs').getBoundingClientRect();return getComputedStyle(button).padding==='0px' && Math.abs(b.x+b.width/2-icon.x-icon.width/2)<1 && Math.abs(b.y+b.height/2-icon.y-icon.height/2)<1 && Math.abs(address.y+address.height/2-b.y-b.height/2)<1})()`),true,`Role ${role}: Home icon centered in its button and address bar at ${toggleWidth}px`);
                assert.equal(await evaluate(`(()=>{const address=document.querySelector('.qms-breadcrumbs').getBoundingClientRect();return [...document.querySelectorAll('.qms-breadcrumbs .qms-path-chevron,.qms-breadcrumbs .qms-crumb>button')].filter(el=>el.checkVisibility()).every(el=>{const b=el.getBoundingClientRect();return Math.abs(address.y+address.height/2-b.y-b.height/2)<1 && (!el.matches('.qms-path-chevron') || el.querySelector('svg'))})})()`),true,`Role ${role}: SVG separators and breadcrumb labels share the icon's vertical center at ${toggleWidth}px`);
                assert.equal(await evaluate(`(()=>{const icons=[...document.querySelectorAll('.qms-view-nav svg')];return icons.length===${role === 0 ? 2 : role === 1 ? 5 : 7} && icons.every(icon=>{const box=icon.getBoundingClientRect(),path=icon.querySelector('path');return box.width===18 && box.height===18 && getComputedStyle(icon).stroke!=='none' && path.getTotalLength()>0})})()`), true, `Role ${role}: available navigation icons render consistently at ${toggleWidth}px`);
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
            assert.equal(await evaluate('Math.round(document.querySelector(".qms-breadcrumbs").getBoundingClientRect().height)'), 40, `Role ${role}: compact path box height`);
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-nav-button")].every(e=>e.getBoundingClientRect().width===34 && e.getBoundingClientRect().height===34)'), true, `Role ${role}: compact navigation buttons`);
            assert.equal(await evaluate('document.querySelectorAll(".qms-breadcrumbs > .qms-crumb").length'), 1, 'Long paths show only the current folder');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, `Role ${role}: long path fits`);
            await evaluate('document.querySelector(".qms-breadcrumb-overflow summary").click()');
            assert.ok(await evaluate('document.querySelectorAll(".qms-breadcrumb-menu button").length >= 3'), 'Collapsed parent folders remain accessible');
            await evaluate('document.querySelector(".qms-breadcrumb-menu button").click()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).collegeId');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).compactPath');
            console.log(`PASS role ${role}: navigation icons, collapsed breadcrumb, ancestor navigation and root expansion`);
            // Assert Chromium's accessibility tree and actual keyboard events, not only aria attributes.
            await evaluate('window.folderActionMenu=[...document.querySelectorAll(".qms-main > .qms-folders .qms-folder-actions")].find(el=>el.checkVisibility())');
            const menu='window.folderActionMenu';
            const key=async (key,code,windowsVirtualKeyCode)=>{await send('Input.dispatchKeyEvent',{type:'keyDown',key,code,windowsVirtualKeyCode,...(key==='Enter'?{text:'\r'}:{})});await send('Input.dispatchKeyEvent',{type:'keyUp',key,code,windowsVirtualKeyCode});await delay(30)};
            const openMenu=async()=>{await evaluate(menu+'.querySelector("summary").focus()');await key('Enter','Enter',13);await waitFor(menu+'.open')};
            await openMenu();
            for(const [index,name] of [[0,/Open/],[1,/Star/],[2,/View Details|View details/]]){
                const object=await send('Runtime.evaluate',{expression:menu+'.querySelectorAll("button")['+index+']',returnByValue:false});
                const tree=await send('Accessibility.getPartialAXTree',{objectId:object.result.result.objectId,fetchRelatives:false});
                const button=tree.result.nodes.find(node=>node.role?.value==='button');
                assert.ok(button&&!button.ignored,'Folder action is exposed as a button');assert.match(button.name.value,name,'Folder action has its actual accessible name');
            }
            await key('Tab','Tab',9);
            assert.equal(await evaluate('document.activeElement==='+menu+'.querySelector("button")'),true,'Open is keyboard reachable');
            await key('Escape','Escape',27);
            assert.equal(await evaluate('!'+menu+'.open && document.activeElement==='+menu+'.querySelector("summary")'),true,'Escape closes actions and returns focus');
            await openMenu();await key('Tab','Tab',9);await key('Tab','Tab',9);await key('Enter','Enter',13);
            await waitFor('!'+menu+'.open');
            assert.equal(await evaluate('document.activeElement==='+menu+'.querySelector("summary")'),true,'Star returns focus to its trigger');
            await waitFor(menu+'.querySelectorAll("button")[1].textContent==="Unstar"');
            await openMenu();await key('Tab','Tab',9);await key('Tab','Tab',9);await key('Tab','Tab',9);await key('Enter','Enter',13);
            await waitFor('document.querySelector("[x-ref=folderDetails]").open');
            await key('Escape','Escape',27);await waitFor('!document.querySelector("[x-ref=folderDetails]").open');
            assert.equal(await evaluate('document.activeElement==='+menu+'.querySelector("summary")'),true,'Details Escape returns focus to its trigger');
            await openMenu();await key('Tab','Tab',9);await key('Enter','Enter',13);
            await waitFor('!!Alpine.$data(document.querySelector(".qms-explorer")).unitId');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).navigate()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).unitId');
            console.log('PASS role '+role+': AX names, keyboard Open/Star/Details, Escape and focus return');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).switchView("cycles")');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).repositoryChildren[0].name'),'Manually named cycle','Manual folder labels are retained');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.openRepositoryFolder(app.tree.repositoryFolders[0])})()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).breadcrumbs.at(-1).name'),'Manually named cycle','Breadcrumb reflects the actual manual folder');
            assert.equal(await evaluate('document.querySelector(".qms-location-search input").placeholder'),'Search Manually named cycle','Search placeholder follows manual folder navigation');
            assert.equal(await evaluate('document.querySelector("[aria-label="+JSON.stringify("Up one folder")+"]").disabled'),false,'Up is enabled inside a repository folder');
            await evaluate('document.querySelector("[aria-label="+JSON.stringify("Up one folder")+"]").click()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).repositoryId');
            await evaluate('document.querySelector("[aria-label=Back]").click()');
            await waitFor('Alpine.$data(document.querySelector(".qms-explorer")).repositoryId === "000000000000000000000100"');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.documents=[];app.setLayout("grid")})()');
            await delay(60);
            assert.equal(await evaluate('document.querySelector(".qms-empty").checkVisibility()'),false,'A folder-only location stays uncluttered');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-repository-browser .qms-folder-icon img")].every(img=>img.complete&&img.naturalWidth===37&&img.naturalHeight===37&&img.getBoundingClientRect().width===24&&img.getBoundingClientRect().height===24)'),true,'Folder icons render compactly without losing their supplied artwork');
            if(role===1){
                for(const folderWidth of [1440,390]){
                    await send('Emulation.setDeviceMetricsOverride',{width:folderWidth,height:1000,deviceScaleFactor:1,mobile:folderWidth<600});
                    await delay(80);
                    const capture=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                    fs.writeFileSync(path.join(fixtures,'minimal-folders-'+folderWidth+'.png'),Buffer.from(capture.result.data,'base64'));
                }
            }
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).openFolderDialog()');
            await waitFor('document.querySelector("[x-ref=folderDialog]").open');
            assert.equal(await evaluate('document.querySelectorAll(".qms-policy-option").length'),4,'ATL can choose all four folder policies');
            assert.equal(await evaluate('document.querySelector(".qms-folder-location").textContent.includes("Manually named cycle")'),true,'Folder dialog shows the actual parent');
            assert.equal(await evaluate('document.querySelector("[x-ref=folderDialog]").getBoundingClientRect().right<=innerWidth'),true,'Automatic-access dialog fits the viewport');
            assert.equal(await evaluate('(()=>{const dialog=document.querySelector("[x-ref=folderDialog]"),footer=dialog.querySelector("footer").getBoundingClientRect();return footer.bottom<=dialog.getBoundingClientRect().bottom+1 && footer.bottom<=innerHeight})()'),true,'Folder actions stay visible while the form scrolls');
            if(role===1){
                for(const manualWidth of [390,1440]){
                    await send('Emulation.setDeviceMetricsOverride',{width:manualWidth,height:1000,deviceScaleFactor:1,mobile:manualWidth<600});
                    await delay(80);
                    const capture=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                    fs.writeFileSync(path.join(fixtures,'automatic-access-'+manualWidth+'.png'),Buffer.from(capture.result.data,'base64'));
                }
            }
            await evaluate('document.querySelector("[x-ref=folderDialog]").close()');
            console.log(`PASS role ${role}: manual repository, real breadcrumbs, folder policies and responsive scope dialog`);
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).switchView("department")');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).unitId'),'000000000000000000000010','My Department opens the home repository rather than every department');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.tree.departmentUnitIds=["000000000000000000000010","000000000000000000000011"];app.switchView("department")})()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).folders.length'),2,'Multiple department memberships offer only the authorized choices');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.openBrowseFolder(app.folders[1])})()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).currentView'), 'department','Selecting a departmental folder preserves the shortcut context');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).tree.departmentUnitIds=["000000000000000000000010"]');
            await evaluate('window.savedFolderTree=JSON.parse(JSON.stringify([...Alpine.$data(document.querySelector(".qms-explorer")).tree.repositoryFolders]))');
            await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.tree.repositoryFolders=[];app.tree.assignedAudits=[];app.switchView("assigned")})()');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).documents=[]');
            assert.equal(await evaluate('document.querySelector(".qms-empty h3").textContent'),'No assigned audits yet.','Assigned Audits explains missing assignments');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).tree.assignedAudits=[{_id:"000000000000000000000085"}]');
            await delay(30);
            assert.equal(await evaluate('document.querySelector(".qms-empty h3").textContent'),'No folders have been created for your assigned audits.','Valid assignments do not generate folders');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
            await delay(20);
            assert.equal(await evaluate('document.querySelector(".qms-list-head").checkVisibility()'),true,'Empty Assigned Audits retains list column headings');
            assert.equal(await evaluate('[...document.querySelectorAll(".qms-folder-row-heading,.qms-list-head")].filter(heading=>heading.checkVisibility()).length'),1,'Empty Assigned Audits shows one set of column headings');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("grid")');
            await delay(20);
            assert.equal(await evaluate('document.querySelector(".qms-list-head").checkVisibility()'),false,'Grid layout hides list column headings');
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).switchView("recent")');
            await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
            assert.equal(await evaluate('window.fixtureCalls.filter(call=>call.url==="/api/v1/documents").at(-1).params.perPage'),5,'Recent requests at most five opened files');
            assert.equal(await evaluate('document.querySelector(".qms-pagination:not(.qms-folder-pagination)").checkVisibility()'),false,'Recent has no pagination');
            for (const [view,message] of [['shared','No documents have been shared with you.'],['starred','No starred files or folders yet.'],['cycles','No audit records available.']]) {
                await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).switchView('+JSON.stringify(view)+')');
                await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
                await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).documents=[]');
                await delay(20);
                assert.equal(await evaluate('document.querySelector(".qms-empty h3").textContent'),message,'Contextual '+view+' empty state');
                if (view === 'starred') {
                    await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));for(const folder of [...app.tree.units,...app.tree.categories])folder.isStarred=false})()');
                    await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list")');
                    await waitFor('document.querySelector(".qms-list-head").checkVisibility()');
                    assert.equal(await evaluate('[...document.querySelectorAll(".qms-list-head > span")].slice(0,4).map(span=>span.textContent).join("|")'),'Name|Owner|Date modified|File size','Empty Starred retains the column headings');
                }
            }
            await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).tree.repositoryFolders=window.savedFolderTree');
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
        for (const [folderId,allowed] of [['000000000000000000000100',true],['000000000000000000000010',true],['000000000000000000000999',false]]) {
            await send('Page.navigate',{url:pathToFileURL(path.join(fixtures,'explorer-1.html')).href+'?folder='+folderId});
            await waitFor('!!window.releaseTree');
            await evaluate('window.treeReleased=true;window.holdArchivePage=false;window.releaseTree()');
            await waitFor('!!window.Alpine && !Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
            if(allowed) assert.equal(await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));return app.repositoryId || app.unitId})()'),folderId,'Direct links open authorized nested or department folders');
            else assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).error'),'This location is unavailable.','Unavailable direct links do not disclose folder existence');
            assert.equal(await evaluate('window.fixtureCalls.filter(c=>c.url==="/api/v1/documents").length'),allowed?1:0,'Direct links skip unrelated root listing');
        }
        await send('Page.navigate',{url:pathToFileURL(path.join(fixtures,'explorer-1.html')).href});
        await waitFor('!!window.releaseTree');
        await evaluate('window.treeReleased=true;window.holdArchivePage=false;window.releaseTree()');
        await waitFor('!!window.Alpine && !Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
        await evaluate(`(()=>{const app=Alpine.$data(document.querySelector('.qms-explorer')),unit=app.tree.units[0];app.tree.repositoryFolders=[];app.tree.units=Array.from({length:25},(_,i)=>({...unit,_id:String(500+i).padStart(24,'0'),name:'Folder '+String(i+1).padStart(2,'0')}))})()`);
        await waitFor('document.querySelector(".qms-folder-pagination .app-pagination-status").textContent==="Page 1 of 3"');
        assert.equal(await evaluate('document.querySelectorAll(".qms-main > .qms-folders .qms-folder").length'),12,'Folder pages contain twelve rows');
        assert.equal(await evaluate('document.querySelector(".qms-folder-pagination button:first-child").disabled'),true,'Previous stops at the first folder page');
        const folderRequestCount=await evaluate('window.fixtureCalls.filter(call=>call.url.startsWith("/api/v1/documents")).length');
        await evaluate('document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-main").scrollTop=80');
        await evaluate('document.querySelector(".qms-folder-pagination button:last-child").click()');
        await waitFor('document.querySelector(".qms-folder-pagination .app-pagination-status").textContent==="Page 2 of 3"');
        assert.equal(await evaluate('document.querySelector(".qms-main > .qms-folders .qms-folder strong").textContent'),'Folder 13');
        assert.equal(await evaluate('document.querySelector(".qms-workspace:not(.qms-loading-skeleton) .qms-main").scrollTop'),0,'Changing folder pages returns to the first row');
        await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("grid")');
        assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).folderCurrentPage'),2,'Layout switching preserves the folder page');
        await evaluate('document.querySelector(".qms-folder-pagination button:last-child").click()');
        await waitFor('document.querySelector(".qms-folder-pagination .app-pagination-status").textContent==="Page 3 of 3"');
        assert.equal(await evaluate('document.querySelectorAll(".qms-main > .qms-folders .qms-folder").length'),1,'Last folder page contains the remaining row');
        assert.equal(await evaluate('document.querySelector(".qms-folder-pagination button:last-child").disabled'),true);
        assert.equal(await evaluate('window.fixtureCalls.filter(call=>call.url.startsWith("/api/v1/documents")).length'),folderRequestCount,'Folder pagination makes no extra QMS API calls: '+JSON.stringify(await evaluate('window.fixtureCalls.map(call=>({url:call.url,params:call.params}))')));
        await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.search="Folder 25";void app.load(true)})()');
        await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading && document.querySelector(".qms-folder-pagination .app-pagination-status").textContent==="Page 1 of 1"');
        assert.equal(await evaluate('document.querySelectorAll(".qms-main > .qms-folders .qms-folder").length'),1,'Search filters folders before pagination');
        await evaluate('(()=>{const app=Alpine.$data(document.querySelector(".qms-explorer"));app.search="";void app.load(true)})()');
        await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
        await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).setLayout("list");Alpine.$data(document.querySelector(".qms-explorer")).goToFolderPage(2);document.querySelector(".qms-folder-row-heading button").click()');
        await delay(30);
        assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).folderCurrentPage'),1,'Changing folder sort resets pagination');
        await evaluate('document.querySelector(".qms-main > .qms-folders .qms-folder").click()');
        await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
        assert.equal(await evaluate('document.querySelector(".qms-folder-pagination").checkVisibility()'),false,'Folder controls hide inside a repository location');
        console.log('PASS folder pagination: shared design, page boundaries, search, sorting, layouts and no extra requests');
        await send('Page.navigate',{url:pathToFileURL(path.join(fixtures,'explorer-1.html')).href+'?fail-tree'});
        await waitFor('!!window.releaseTree');
        await evaluate('window.treeReleased=true;window.holdArchivePage=false;window.releaseTree()');
        await waitFor('!!window.Alpine && !Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
        assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).treeReady'),false,'Tree failure does not pretend initialization succeeded');
        assert.equal(await evaluate('document.querySelector(".qms-empty").checkVisibility()'),false,'Failed tree never displays an empty folder');
        await evaluate('window.failTree=false;document.querySelector(".qms-alert button").click()');
        await waitFor('Alpine.$data(document.querySelector(".qms-explorer")).treeReady && !Alpine.$data(document.querySelector(".qms-explorer")).loading');
        await evaluate('window.holdArchivePage=true;void Alpine.$data(document.querySelector(".qms-explorer")).switchView("recent")');
        await waitFor('Alpine.$data(document.querySelector(".qms-explorer")).loading');
        await evaluate('window.oldNavigation=Alpine.$data(document.querySelector(".qms-explorer")).navigationRequest;window.holdArchivePage=false;void Alpine.$data(document.querySelector(".qms-explorer")).switchView("shared")');
        await waitFor('!Alpine.$data(document.querySelector(".qms-explorer")).loading');
        assert.equal(await evaluate('window.oldNavigation.signal.aborted'),true,'Rapid navigation aborts the old request');
        assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).error'),'','Cancellation does not show an API failure');
        console.log('PASS initial request counts, lazy ONLYOFFICE, direct link requests, tree retry and rapid navigation cancellation');
        for (const role of [2,3]) {
            await send('Page.navigate',{url:pathToFileURL(path.join(fixtures,'explorer-'+role+'.html')).href});
            await waitFor('!!window.releaseTree');
            await evaluate('window.treeReleased=true;window.holdArchivePage=false;window.releaseTree()');
            await waitFor('!!window.Alpine && !Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
            await evaluate('window.uploadApp=Alpine.$data(document.querySelector(".qms-explorer"));uploadApp.openRepositoryFolder(uploadApp.tree.repositoryFolders[1])');
            await waitFor('!uploadApp.loading');
            await evaluate('document.querySelector("[data-qms-upload]").click()');
            await waitFor('document.querySelector("[x-ref=upload]").open && !uploadApp.uploadChecksLoading');
            assert.equal(await evaluate('document.querySelector("#qms-file-upload-title").textContent'),'Upload files');
            assert.equal(await evaluate('Array.from(document.querySelectorAll("[x-ref=uploadForm] select")).some(el=>el.getAttribute("x-model")==="form.categoryId")'),false,'Upload destination cannot be changed');
            assert.equal(await evaluate('document.querySelector("[x-ref=upload]").getAttribute("aria-labelledby")'),'qms-file-upload-title');
            const uploadShot = await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
            fs.writeFileSync(path.join(fixtures,`folder-upload-${role}.png`),Buffer.from(uploadShot.result.data,'base64'));
            assert.equal(await evaluate('(()=>{const d=document.querySelector("[x-ref=upload]"),r=d.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&d.scrollWidth<=d.clientWidth})()'),true,'Folder upload popup fits the mobile viewport: '+JSON.stringify(await evaluate('(()=>{const d=document.querySelector("[x-ref=upload]"),r=d.getBoundingClientRect();return {left:r.left,right:r.right,width:innerWidth,scroll:d.scrollWidth,client:d.clientWidth}})()')));
            await evaluate('uploadApp.selectUploadFile(new File(["%PDF-1.4 test"],"folder.pdf",{type:"application/pdf"}));uploadApp.form.copyAuthorized=true;window.savedFolderUpload=null;const adapter=axios.defaults.adapter;axios.defaults.adapter=async config=>{if(config.url.endsWith("/upload"))window.savedFolderUpload=Object.fromEntries(config.data.entries());return adapter(config)}');
            await evaluate('document.querySelector("[x-ref=uploadForm]").requestSubmit()');
            await waitFor('!!window.savedFolderUpload && !document.querySelector("[x-ref=upload]").open');
            assert.equal(await evaluate('window.savedFolderUpload.categoryId'),'000000000000000000000101','File is saved into the open folder');
            assert.equal(await evaluate('window.savedFolderUpload.recordType'),'file');
            await evaluate('uploadApp.tree.repositoryFolders[1].permissions.upload=false;uploadApp.create()');
            assert.equal(await evaluate('document.querySelector("[x-ref=upload]").open'),false,'Read-only folders cannot open upload');
            await evaluate(`uploadApp.repositoryId='';uploadApp.categoryId='';uploadApp.unitId=uploadApp.tree.units[0]._id;uploadApp.currentView='department';uploadApp.tree.units[0].folderPermissions={view:true,upload:false,correctiveUpload:true};void uploadApp.create()`);
            await waitFor('document.querySelector("[x-ref=upload]").open && !uploadApp.uploadChecksLoading');
            assert.equal(await evaluate('document.querySelector("[x-ref=uploadForm] select").required'),true,'Finding-only uploads require a related open finding');
            await evaluate('uploadApp.$refs.upload.close();uploadApp.tree.units[0].folderPermissions.correctiveUpload=false;void uploadApp.create()');
            assert.equal(await evaluate('document.querySelector("[x-ref=upload]").open'),false,'A root without general or corrective upload rights remains read-only');

        }
        console.log('PASS auditor and auditee folder upload popup, destination and read-only permissions');
        assert.equal(exceptions.length,0,JSON.stringify(exceptions));
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error=>{console.error(error);process.exitCode=1});
