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
            const id=n=>String(n).padStart(24,'0');
            const units=Object.entries(${JSON.stringify(names)}).map(([code,name],i)=>({_id:id(i+10),collegeId:id(1),name,code,unitType:'academic_department',canCreate:true,count:0}));
            units.push({_id:id(20),collegeId:id(1),name:'Office of the Dean',unitType:'college_office',canCreate:true,count:0});
            const data={stats:{totalUsers:12,cetDepartments:5,activeAudits:3,openCars:2,archivedDocuments:8,archiveStorageBytes:1536},
                departmentCompliance:units.slice(0,5).map((unit,i)=>({id:unit._id,name:unit.name,code:unit.code,active:true,percentage:i===0?50:i===1?0:null,counts:{C:i===0?1:0,N:i<2?1:0,O:0}})),
                recentAuditActivity:Array.from({length:6},(_,i)=>({id:id(50+i),reference:'BROWSER-AUDIT-'+i,department:units[i%5].name,status:'submitted',at:'2026-11-09T01:00:00Z'})),
                correctiveAttention:Array.from({length:6},(_,i)=>({id:id(60+i),reference:'BROWSER-CAR-'+i,department:units[i%5].name,assignee:'Assigned account',status:'open',dueDate:'2026-11-01T00:00:00Z',overdue:true,source:'Checklist-linked CAR'})),
                generatedAt:'2026-11-09T01:00:00Z',recordSummary:{completedAudits:2,legacyChecks:3,checklistLinkedCars:1},userGrowth:['Jun','Jul','Aug','Sep','Oct','Nov'].map((month,i)=>({month:month+' 2026',count:[0,0,2,5,10,12][i]}))};
            window.calls=[];window.dashboardFixture=data;window.failDashboard=false;window.healthOk=false;
            window.holdDashboard=true;window.holdHealth=true;window.pendingDashboard=[];window.pendingHealth=[];
            window.healthIntervals=[];window.clearedIntervals=[];
            const nativeInterval=window.setInterval.bind(window),nativeClear=window.clearInterval.bind(window);
            window.setInterval=(callback,ms,...args)=>{const id=nativeInterval(callback,ms,...args);if(ms===60000)window.healthIntervals.push({id,callback});return id;};
            window.clearInterval=id=>{window.clearedIntervals.push(id);nativeClear(id);};
            Object.defineProperty(window,'axios',{configurable:true,get(){return this.fixtureAxios},set(client){
                client.defaults.adapter=async config=>{
                    window.calls.push({url:config.url,method:config.method});let body={};
                    if(config.url==='/api/v1/system/dashboard'&&window.holdDashboard)await new Promise(resolve=>window.pendingDashboard.push(resolve));
                    if(config.url.endsWith('/dashboard/health')&&window.holdHealth)await new Promise(resolve=>window.pendingHealth.push(resolve));
                    if(config.url.includes('/departments')&&window.holdDepartmentRecords)await new Promise(resolve=>window.pendingDepartmentRecords.push(resolve));
                    if((config.url.endsWith('/tree')||config.url.endsWith('/organization'))&&window.holdOrganization)await new Promise(resolve=>window.pendingOrganization.push(resolve));
                    if(config.url==='/api/v1/system/dashboard'){if(window.failDashboard)throw Error('Fixture outage');body={data:structuredClone(data)};}
                    else if(config.url.endsWith('/dashboard/health'))body={data:{laravel:'ok',mongodb:'ok',realtime:window.healthOk?'ok':'unavailable',checkedAt:'2026-11-09T01:00:00Z',components:[{name:'Laravel',status:'Operational'},{name:'Node.js Service',status:window.healthOk?'Operational':'Unavailable'},{name:'MongoDB Cluster',status:'Operational'},{name:'FCM Push Service',status:window.healthOk?'Not Configured':'Unknown'}]}};
                    else if(config.url.endsWith('/tree'))body={campus:'USTP Claveria Campus',colleges:[{_id:id(1),name:'College of Engineering and Technology',code:'CET'}],units,categories:[],total:0,canManageOrganization:true};
                    else if(config.url.endsWith('/organization'))body={users:[{_id:id(90),name:'Department Head account',email:'browser@example.test',roleId:3,departmentId:id(10),responsibility:'department_head',unitIds:[id(10)],collegeIds:[]}],responsibilities:['department_head','department_secretary','college_dean','college_secretary','faculty_staff']};
                    else if(config.url.includes('/departments'))body={data:units.slice(0,5).map(unit=>({_id:unit._id,departmentCode:unit.code,departmentName:unit.name,collegeName:'CET',campusName:'Claveria',isActive:true})),meta:{total:5,currentPage:1,lastPage:1}};
                    else if(config.url==='/api/v1/archives')body={data:[],meta:{total:0,currentPage:1,lastPage:1}};
                    else if(config.method==='patch')body={message:'Saved test responsibility'};
                    else body={data:[],meta:{total:0,currentPage:1,lastPage:1}};
                    return {data:body,status:200,statusText:'OK',headers:{},config};
                };this.fixtureAxios=client;
            }});
        ` });
        for (const width of [1440, 1024, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(directory, 'dashboard.html')).href });
            await waitFor('window.pendingDashboard?.length===1 && window.pendingHealth?.length===1');
            await waitFor('document.querySelector(".sa-skeleton-comparison").getClientRects().length>0');
            assert.equal(await evaluate('[...document.querySelectorAll(".sa-skeleton-value")].filter(el=>el.getClientRects().length).length'), 6, 'Every metric has a loading skeleton');
            assert.equal(await evaluate('document.querySelectorAll(".sa-skeleton-comparison .sa-skeleton-row").length'), 5);
            assert.equal(await evaluate('document.querySelectorAll(".sa-skeleton-activity-row").length'), 6);
            assert.equal(await evaluate('document.querySelector(".sa-skeleton-chart").getClientRects().length>0'), true);
            assert.equal(await evaluate('document.querySelector(".sa-skeleton-health").getClientRects().length>0'), true);
            assert.equal(await evaluate('document.querySelector(".sa-trend-svg").getClientRects().length'), 0, 'Graph waits for data');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Skeleton layout fits the viewport');
            if ([1440,390].includes(width)) {
                const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
                fs.writeFileSync(path.join(directory,`skeleton-${width}.png`),Buffer.from(shot.result.data,'base64'));
            }
            await evaluate('window.holdDashboard=false;window.pendingDashboard.splice(0).forEach(resolve=>resolve())');
            await waitFor('document.querySelectorAll(".sa-comparison .sa-department-row").length===5');
            await waitFor('document.querySelector(".sa-skeleton-comparison").getClientRects().length===0');
            assert.equal(await evaluate('document.querySelector(".sa-skeleton-health").getClientRects().length>0'), true, 'Health loads independently from dashboard records');
            await evaluate('window.holdHealth=false;window.pendingHealth.splice(0).forEach(resolve=>resolve())');
            assert.equal(await evaluate('document.querySelectorAll(".sa-stat").length'), 6);
            assert.equal(await evaluate('document.querySelectorAll(".sa-stat.metric-stat-card .metric-icon-box svg").length'), 6, 'Statistics reuse shared dashboard cards');
            assert.equal(await evaluate('document.querySelectorAll(".sa-trend-point").length'), 6, 'Graph plots every month');
            assert.equal(await evaluate('document.querySelector(".sa-trend-line").getAttribute("points").trim().split(/\\s+/).length'), 6);
            assert.equal(await evaluate('document.querySelector(".sa-trend-svg").getAttribute("aria-label").includes("Nov 2026: 12 users")'), true);
            await evaluate('document.querySelector(".sa-trend-point").focus()');
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".sa-trend-tooltip")).display'), 'block', 'Graph values are keyboard accessible');
            await evaluate('document.querySelector(".sa-trend-point").blur()');
            assert.equal(await evaluate('document.querySelectorAll(".sa-stat strong")[5].textContent'), '1.5 KB');
            assert.equal(await evaluate('document.querySelectorAll(".sa-comparison-value strong")[1].textContent'), '0%');
            assert.equal(await evaluate('document.querySelectorAll(".sa-comparison-value strong")[2].textContent'), 'Not Yet Assessed');
            await waitFor('document.querySelectorAll(".system-health-list .system-health-row").length===4');
            await waitFor('document.querySelector(".sa-skeleton-health").getClientRects().length===0');
            await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".sa-skeleton-value")).animationName'), 'none', 'Skeletons respect reduced motion');
            await send('Emulation.setEmulatedMedia',{features:[]});
            assert.equal(await evaluate('document.querySelector(".sa-health dl").textContent.includes("Unavailable")'), true);
            assert.equal(await evaluate('window.healthIntervals.length'), 1, 'Health auto-refreshes every 60 seconds');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, `No horizontal overflow at ${width}`);
            assert.equal(await evaluate('document.querySelector(".sidebar-brand-logo").naturalWidth>0'), true);
            assert.equal(await evaluate('document.querySelectorAll(".sidebar-section").length'), 0);
            assert.equal(await evaluate('document.querySelectorAll(".app-sidebar nav a").length'), 7);
            try {
                await waitFor('[...document.querySelectorAll(".sa-activity")].every(list=>list.clientHeight>0 && list.scrollHeight>list.clientHeight)');
            } catch (error) {
                throw new Error(`${width}px list sizing: ${JSON.stringify(await evaluate('[...document.querySelectorAll(".sa-activity")].map(list=>({height:list.clientHeight,scrollHeight:list.scrollHeight,maxHeight:list.style.maxHeight,rows:list.querySelectorAll("li").length}))'))}`, {cause:error});
            }
            assert.equal(await evaluate(` [...document.querySelectorAll('.sa-activity')].every(list=>{
                const rows=list.querySelectorAll('li');
                const fiveHeight=rows[4].getBoundingClientRect().bottom-rows[0].getBoundingClientRect().top;
                return Math.abs(list.clientHeight-fiveHeight)<=1 && getComputedStyle(list).scrollbarWidth==='none';
            })`), true, 'Both lists display five full rows with hidden scrollbars');
            if (width >= 768) {
                await waitFor(`(()=>{
                    const lists=[...document.querySelectorAll('.sa-activity')];
                    return Math.abs(lists[0].clientHeight-lists[1].clientHeight)<=2;
                })()`);
                assert.equal(await evaluate(`(()=>{
                    const panels=[...document.querySelectorAll('.sa-detail-grid > article')];
                    const list=panels[0].querySelector('.sa-activity');
                    return Math.abs(panels[0].getBoundingClientRect().height-panels[1].getBoundingClientRect().height)<=1
                        && panels[0].getBoundingClientRect().bottom-list.getBoundingClientRect().bottom<24;
                })()`), true, 'Audit row spacing fills the matching panel height');
            }
            assert.equal(await evaluate(` [...document.querySelectorAll('.sa-activity')].every(list=>{
                list.scrollTop=list.scrollHeight;
                return list.scrollTop>0 && list.tabIndex===0;
            })`), true, 'Remaining items can be scrolled to and lists accept keyboard focus');
            await evaluate('document.querySelectorAll(".sa-activity").forEach(list=>list.scrollTop=0)');
            if (width === 1440) {
                await evaluate('window.fullActivityLists={recentAuditActivity:window.dashboardFixture.recentAuditActivity,correctiveAttention:window.dashboardFixture.correctiveAttention}');
                for (const count of [5, 1, 0]) {
                    await evaluate(`Object.keys(window.fullActivityLists).forEach(key=>window.dashboardFixture[key]=window.fullActivityLists[key].slice(0,${count}));Alpine.$data(document.querySelector('.sa-overview')).load()`);
                    await waitFor(`!Alpine.$data(document.querySelector('.sa-overview')).loading && [...document.querySelectorAll('.sa-activity')].every(list=>list.querySelectorAll('li').length===${count} && list.style.maxHeight==='')`);
                    assert.equal(await evaluate('[...document.querySelectorAll(".sa-activity")].every(list=>list.scrollHeight<=list.clientHeight+1)'), true, `${count} items need no scrolling`);
                }
                await evaluate('Object.assign(window.dashboardFixture,window.fullActivityLists);Alpine.$data(document.querySelector(".sa-overview")).load()');
                await waitFor('!Alpine.$data(document.querySelector(".sa-overview")).loading && [...document.querySelectorAll(".sa-activity")].every(list=>list.scrollHeight>list.clientHeight)');
            }
            await evaluate('window.healthOk=true;window.healthIntervals[0].callback()');
            await waitFor('!document.querySelector(".sa-health dl").textContent.includes("Unavailable")');
            assert.equal(await evaluate('document.querySelector(".sa-health dl").textContent.includes("Not Configured")'), true);
            await evaluate('document.querySelector(".sa-health-refresh").click()');
            await waitFor('!Alpine.$data(document.querySelector(".sa-overview")).healthLoading');
            await delay(80);
            if ([1440, 390].includes(width)) {
                await evaluate('window.scrollTo(0, 0)');
                await delay(60);
                const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
                fs.writeFileSync(path.join(directory, `dashboard-${width}.png`), Buffer.from(shot.result.data, 'base64'));
            }
            await evaluate('window.failDashboard=true;Alpine.$data(document.querySelector(".sa-overview")).load()');
            await waitFor('Alpine.$data(document.querySelector(".sa-overview")).error.length>0');
            await evaluate('window.failDashboard=false;Alpine.$data(document.querySelector(".sa-overview")).load()');
            await waitFor('!Alpine.$data(document.querySelector(".sa-overview")).loading');
            await evaluate('window.dashboardFixture.stats.archiveStorageBytes=1048576;Alpine.$data(document.querySelector(".sa-overview")).load()');
            await waitFor('document.querySelectorAll(".sa-stat strong")[5].textContent==="1 MB"');
            await evaluate('Alpine.$data(document.querySelector(".sa-overview")).destroy()');
            assert.equal(await evaluate('window.clearedIntervals.includes(window.healthIntervals[0].id)'), true, 'Health refresh timer is cleaned up');
            console.log(`PASS ${width}px: skeletons, independent health loading, reduced motion, cards, graph, retry, sidebar`);
        }
        await send('Page.addScriptToEvaluateOnNewDocument', { source: 'window.holdDepartmentRecords=location.pathname.endsWith("/departments.html");window.holdOrganization=window.holdDepartmentRecords;window.pendingDepartmentRecords=[];window.pendingOrganization=[];' });
        for (const width of [1440, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(directory, 'departments.html')).href });
            await waitFor('window.pendingDepartmentRecords.length===1 && window.pendingOrganization.length===2');
            await waitFor('[...document.querySelectorAll("table.sa-skeleton-table")].filter(table=>table.getClientRects().length).length===2');
            assert.equal(await evaluate('document.querySelectorAll("table.sa-skeleton-table tbody tr").length'), 10, 'Both sections show five skeleton rows');
            assert.equal(await evaluate('document.querySelector(".sa-organization-records").getClientRects().length'), 0, 'Responsibility records wait for data');
            assert.equal(await evaluate('document.querySelectorAll(".resource-empty-state").length'), 0, 'Loading does not show an empty state');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, `Department skeletons fit ${width}px`);
            await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
            assert.equal(await evaluate('getComputedStyle(document.querySelector("table.sa-skeleton-table .sa-skeleton")).animationName'), 'none');
            await send('Emulation.setEmulatedMedia',{features:[]});
            await evaluate('window.holdDepartmentRecords=false;window.pendingDepartmentRecords.splice(0).forEach(resolve=>resolve())');
            await waitFor('document.querySelectorAll(".data-table tbody tr").length===5');
            assert.equal(await evaluate('document.querySelector(".sa-organization table.sa-skeleton-table").getClientRects().length>0'), true, 'Responsibilities continue loading independently');
            await evaluate('window.holdOrganization=false;window.pendingOrganization.splice(0).forEach(resolve=>resolve())');
            await waitFor('document.querySelector(".sa-organization-records").getClientRects().length>0 && document.querySelector(".sa-organization-records").textContent.includes("Department Head account")');
            assert.equal(await evaluate('document.querySelectorAll(".sa-organization-records tbody tr").length'), 5);
            assert.equal(await evaluate('document.querySelectorAll(".sa-college-office").length'), 1);
            assert.equal(await evaluate('[...document.querySelectorAll("table.sa-skeleton-table")].some(table=>table.getClientRects().length)'), false, 'Skeletons disappear after loading');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true);
            console.log(`PASS ${width}px: department and responsibility skeletons, independent loading, reduced motion, loaded records`);
        }
        await send('Page.navigate', { url: pathToFileURL(path.join(directory, 'archives.html')).href + '?manage=responsibilities' });
        await waitFor('document.querySelector("[x-ref=organization]").open');
        await evaluate(`(()=>{const data=Alpine.$data(document.querySelector('.qms-explorer'));data.selectedUser=String(90).padStart(24,'0');data.selectUser();})()`);
        assert.equal(await evaluate('Alpine.$data(document.querySelector(".qms-explorer")).responsibility'), 'department_head');
        const r = await evaluate('(()=>{const r=document.querySelector("[x-ref=organization]").getBoundingClientRect();return {left:r.left,right:r.right,bottom:r.bottom};})()');
        assert.ok(r.left >= 0 && r.right <= 320 && r.bottom <= 900);
        for (const width of [1440, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
            await send('Page.navigate', { url: pathToFileURL(path.join(directory, 'profile.html')).href });
            await waitFor('document.querySelector(".super-admin-profile-page")?._x_dataStack');
            assert.equal(await evaluate('document.querySelector(".profile-photo-open").disabled'), true, 'No preview for an empty avatar');
            await evaluate(`Alpine.$data(document.querySelector('.super-admin-profile-page')).avatarUrl=${JSON.stringify(pathToFileURL(path.join(root, 'public/images/ustp-claveria-logo.jpg')).href)}`);
            await waitFor('!document.querySelector(".profile-photo-open").disabled && document.querySelector(".profile-photo-open img").naturalWidth>0');
            await evaluate('document.querySelector(".profile-photo-open").focus();document.querySelector(".profile-photo-open").click()');
            await waitFor('document.querySelector(".profile-photo-preview").open');
            assert.equal(await evaluate('(()=>{const modal=document.querySelector(".profile-photo-preview"),img=modal.querySelector("img"),box=modal.getBoundingClientRect();return img.naturalWidth>0&&img.getBoundingClientRect().width>document.querySelector(".profile-photo-open").clientWidth&&box.left>=0&&box.right<=innerWidth&&box.top>=0&&box.bottom<=innerHeight&&getComputedStyle(img).objectFit==="contain";})()'), true, 'Full photo fits the viewport without cropping');
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await waitFor('!document.querySelector(".profile-photo-preview").open');
            assert.equal(await evaluate('document.activeElement===document.querySelector(".profile-photo-open")'), true, 'Closing returns focus to the photo');
            await evaluate('document.querySelector(".profile-photo-open").click()');
            await waitFor('document.querySelector(".profile-photo-preview").open');
            await evaluate('document.querySelector(".profile-photo-preview button").click()');
            await waitFor('!document.querySelector(".profile-photo-preview").open');
            await evaluate('document.querySelector(".profile-photo-open").click()');
            await waitFor('document.querySelector(".profile-photo-preview").open');
            await send('Input.dispatchMouseEvent',{type:'mousePressed',x:2,y:2,button:'left',clickCount:1});
            await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:2,y:2,button:'left',clickCount:1});
            await waitFor('!document.querySelector(".profile-photo-preview").open');
            await evaluate('window.cameraSelected=false;document.querySelector("[x-ref=avatarInput]").click=()=>{window.cameraSelected=true};document.querySelector(".super-admin-profile-camera").click()');
            assert.equal(await evaluate('window.cameraSelected&&!document.querySelector(".profile-photo-preview").open'), true, 'Camera still selects a new upload');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true);
            console.log(`PASS ${width}px: full-size profile picture, close, Escape, backdrop, focus return, upload control`);
        }
        assert.equal(exceptions.length, 0, JSON.stringify(exceptions));
        console.log('PASS existing responsibility dialog navigation and department/college separation');
        await send('Browser.close');
    } finally { clearTimeout(watchdog); ws?.close(); browser.kill(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
