const {spawn, execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {pathToFileURL} = require('node:url');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/super-admin-browser');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    execFileSync('php', ['tests/Fixtures/super-admin-dashboard.php'], {cwd:root, windowsHide:true});
    const browser = spawn(process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        ['--headless','--disable-gpu','--allow-file-access-from-files','--remote-debugging-port=9355',`--user-data-dir=${path.join(directory,'users-edge-profile')}`,'about:blank'], {windowsHide:true, stdio:'ignore'});
    let ws;
    const watchdog = setTimeout(()=>{ws?.close(); browser.kill(); process.exitCode=1;},60000);
    try {
        let tabs;
        for(let i=0;i<60;i++){try{tabs=await(await fetch('http://127.0.0.1:9355/json')).json();break;}catch{await delay(200);}}
        assert.ok(tabs);
        ws = new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
        await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
        let sequence=0; const pending=new Map(), exceptions=[];
        ws.addEventListener('message',event=>{const value=JSON.parse(event.data);if(value.method==='Runtime.exceptionThrown')exceptions.push(value.params.exceptionDetails);if(pending.has(value.id)){pending.get(value.id)(value);pending.delete(value.id);}});
        const send=(method,params={})=>new Promise(resolve=>{const id=++sequence;pending.set(id,resolve);ws.send(JSON.stringify({id,method,params}));});
        const evaluate=async expression=>{const response=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(response.result.exceptionDetails)throw Error(JSON.stringify(response.result.exceptionDetails));return response.result.result.value;};
        const waitFor=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out: '+expression);};
        await send('Page.enable'); await send('Runtime.enable');
        await send('Page.addScriptToEvaluateOnNewDocument',{source:`
            const id=n=>String(n).padStart(24,'0');
            const names=['Agricultural and Biosystem Engineering','Environmental Engineering','Food Processing and Technology','Hospitality Management','Information Technology'];
            const units=names.map((name,i)=>({_id:id(100+i),name,code:['CET-ABE','CET-ENV','CET-FPT','CET-HM','CET-IT'][i],collegeId:id(200),unitType:'academic_department',active:true,verified:true,authorizedCet:true}));
            units.push({_id:id(110),name:'Office of the Dean',code:'CET-DEAN',collegeId:id(200),unitType:'college_office',active:true,verified:true,authorizedCet:false});
            const options={clusters:[{id:'cluster_a',name:'Cluster A (Claveria Group)'},{id:'cluster_b',name:'Cluster B (Cagayan de Oro/Main Group)'}],departments:units,colleges:[{_id:id(200),name:'College of Engineering and Technology',code:'CET'}],responsibilities:['department_head','department_secretary','college_dean','college_secretary','cluster_administrator','faculty_staff']};
            const users=Array.from({length:12},(_,i)=>({_id:id(i+1),fullName:'Test Person '+i,email:'person'+i+'@example.test',userCode:'ATE-'+i,roleId:i%4,isActive:i!==11,departmentId:units[i%5]._id,departmentName:units[i%5].name,assignedDepartmentIds:[],archiveResponsibility:i%4===3?'department_head':null,archiveUnitIds:i%4===3?[units[i%5]._id]:[],archiveCollegeIds:[],organizationalUnits:i%4===3?[units[i%5].name]:[],organizationalColleges:[],isAuditTeamLeader:i===1,canDeactivate:i%4!==0&&i!==1}));
            users[3].fullName='<script>Unsafe name</script>';
            window.fixtureUsers=users;window.calls=[];window.failSave=false;window.failList=false;window.holdList=true;window.pendingList=[];
            Object.defineProperty(window,'axios',{configurable:true,get(){return this.fixtureAxios},set(client){
                client.defaults.adapter=async config=>{
                    let body={data:[]};const payload=typeof config.data==='string'?JSON.parse(config.data):config.data;
                    window.calls.push({url:config.url,method:config.method,params:config.params,payload});
                    if(config.url==='/api/v1/users'&&config.method==='get'){
                        if(window.holdList)await new Promise(resolve=>window.pendingList.push(resolve));
                        if(window.failList)throw Error('Fixture list outage');
                        const p=config.params||{};let filtered=users.filter(u=>(p.roleId===undefined||u.roleId===Number(p.roleId))&&(!p.departmentId||u.departmentId===p.departmentId)&&(!p.responsibility||u.archiveResponsibility===p.responsibility)&&(!p.status||u.isActive===(p.status==='active'))&&(!p.search||[u.fullName,u.email,u.userCode].some(v=>v.toLowerCase().includes(p.search.toLowerCase()))));
                        const perPage=Number(p.perPage||10),page=Number(p.page||1);
                        body={data:filtered.slice((page-1)*perPage,page*perPage),meta:{total:filtered.length,currentPage:page,lastPage:Math.max(1,Math.ceil(filtered.length/perPage)),perPage},options,summary:{total:users.length,active:users.filter(u=>u.isActive).length,auditors:users.filter(u=>u.roleId===2).length,auditees:users.filter(u=>u.roleId===3).length}};
                    }else if(config.url==='/api/v1/users/options'){
                        body={options};
                    }else if(config.url==='/api/v1/users'&&config.method==='post'){
                        if(window.failSave)throw {response:{data:{message:'The email has already been taken.',errors:{email:['Use another email address.']}}}};
                        const row={...payload,_id:id(90),userCode:'ATE-NEW',canDeactivate:true,organizationalUnits:[],organizationalColleges:[],departmentName:units[0].name};users.push(row);body={data:row};
                    }else if(config.url.startsWith('/api/v1/users/')){
                        const key=config.url.split('/')[4],row=users.find(u=>u._id===key);
                        if(config.method==='get')body={data:{...row,scope:{primaryRole:'Auditee',policy:'Only authorized assigned records.',active:row.isActive,archiveUnits:[row.departmentName],accessibleArchiveCount:2,assignedAudits:[],reviewAuditCount:0},activity:Array.from({length:20},()=>({action:'Updated user',actor:'Super Admin',at:'2026-11-10T01:00:00Z'}))}};
                        else if(config.method==='patch'){Object.assign(row,payload);body={data:row};}
                        else if(config.method==='delete'){row.isActive=false;body={message:'Deactivated'};}
                        else if(config.url.endsWith('/reactivate')){row.isActive=true;body={message:'Reactivated'};}
                        else body={message:'Password reset link sent'};
                    }
                    return {data:structuredClone(body),status:200,statusText:'OK',headers:{},config};
                };this.fixtureAxios=client;
            }});
        `});
        const state='Alpine.$data(document.querySelector(".um-page"))';
        for(const width of [1440,1024,768,390,320]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
            await send('Page.navigate',{url:pathToFileURL(path.join(directory,'users.html')).href});
            await waitFor('window.pendingList.length===1');
            assert.equal(await evaluate('document.querySelector(".um-loading").getClientRects().length>0'),true);
            assert.equal(await evaluate('document.querySelector(".um-table-wrap").getClientRects().length'),0);
            await evaluate('window.holdList=false;window.pendingList.splice(0).forEach(resolve=>resolve())');
            await waitFor('document.querySelectorAll(".um-table tbody tr").length===10');
            assert.equal(await evaluate('document.querySelector(".um-summary strong").textContent'),'12');
            assert.equal(await evaluate('document.querySelector(".um-table td strong").textContent'),'Test Person 0');
            assert.equal(await evaluate('document.querySelector(".um-table td small").textContent'),'ATE-0');
            await waitFor('document.querySelector(".um-table-wrap").getClientRects().length>0');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Page fits '+width+' '+JSON.stringify(await evaluate('({document:document.documentElement.scrollWidth,viewport:innerWidth,wrap:[...document.querySelectorAll(".um-table-wrap,.um-loading")].map(el=>({width:el.clientWidth,scroll:el.scrollWidth,overflow:getComputedStyle(el).overflowX,display:getComputedStyle(el).display}))})')));
            await evaluate('document.querySelectorAll(".um-pagination button")[1].click()');
            await waitFor(`${state}.page===2 && !${state}.loading`);
            assert.equal(await evaluate('document.querySelectorAll(".um-table tbody tr").length'),2);
            await evaluate(`${state}.filters.roleId='3';${state}.filters.status='active';${state}.load(1)`);
            await waitFor(`${state}.meta.total===2 && !${state}.loading`);
            await evaluate(`${state}.clearUserFilters()`);await waitFor(`${state}.meta.total===12 && !${state}.loading`);
            for(const term of ['person7@','ATE-7','Unsafe name']){
                await evaluate(`${state}.search=${JSON.stringify(term)};${state}.load(1)`);await waitFor(`${state}.meta.total===1 && !${state}.loading`);
            }
            assert.equal(await evaluate('document.querySelectorAll(".um-table script").length'),0,'User strings are escaped');
            await evaluate(`${state}.filters.departmentId='000000000000000000000103';${state}.filters.responsibility='department_head';${state}.load(1)`);
            await waitFor(`${state}.meta.total===1 && !${state}.loading`);
            await evaluate('document.querySelector(".um-menu-trigger").click()');
            await waitFor('document.querySelector(".um-menu").matches(":popover-open")');
            assert.equal(await evaluate('document.activeElement.textContent'),'View details');
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown',windowsVirtualKeyCode:40});
            assert.equal(await evaluate('document.activeElement.textContent'),'Edit user');
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await waitFor('!document.querySelector(".um-menu").matches(":popover-open")');
            await evaluate(`${state}.viewUser(${state}.rows[0])`);await waitFor(`${state}.details && !${state}.detailLoading`);
            assert.equal(await evaluate('document.querySelector("[x-ref=userDetails]").textContent.includes("Updated user")'),true);
            assert.equal(await evaluate(`(()=>{const list=document.querySelector('.um-activity');list.scrollTop=list.scrollHeight;return list.scrollHeight>list.clientHeight&&list.scrollTop>0&&list.tabIndex===0&&getComputedStyle(list).scrollbarWidth==='none';})()`),true,'Account activity scrolls without a visible scrollbar');
            await evaluate('document.querySelector("[x-ref=userDetails]").close()');
            await evaluate(`${state}.startUserCreate()`);await waitFor('document.querySelector("[x-ref=userEditor]").open');
            assert.equal(await evaluate('document.querySelector("[x-ref=userEditor]").querySelectorAll("fieldset").length'),3);
            assert.equal(await evaluate('(()=>{const el=document.querySelector("[x-ref=userEditor]");return el.getBoundingClientRect().width<=innerWidth&&el.scrollWidth<=el.clientWidth;})()'),true,'Responsive modal');
            await evaluate(`${state}.userForm.archiveResponsibility='college_dean';${state}.changeResponsibility()`);
            await waitFor(`${state}.collegeResponsibility()`);
            assert.equal(await evaluate(`[...document.querySelectorAll('select')].find(select=>select.getAttribute('x-model')==='userForm.archiveCollegeIds[0]').required`),true);
            await waitFor(`!${state}.userOptionsLoading`);
            for(const responsibility of ['department_head','college_secretary','cluster_administrator','faculty_staff','']){
                await evaluate(`(()=>{const select=document.querySelector('[x-model="userForm.archiveResponsibility"]');select.value=${JSON.stringify(responsibility)};select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
                const model=responsibility==='cluster_administrator'?'userForm.archiveCluster':responsibility==='department_head'?'userForm.archiveUnitIds[0]':responsibility==='college_secretary'?'userForm.archiveCollegeIds[0]':'userForm.departmentId';
                await waitFor(`(()=>{const select=[...document.querySelectorAll('[x-ref=userEditor] select')].find(el=>el.getAttribute('x-model')===${JSON.stringify(model)});return select.getClientRects().length>0;})()`);
                const dropdown=await evaluate(`(()=>{const select=[...document.querySelectorAll('[x-ref=userEditor] select')].find(el=>el.getAttribute('x-model')===${JSON.stringify(model)});return {visible:select.getClientRects().length>0,disabled:select.disabled,options:select.options.length};})()`);
                assert.ok(dropdown.visible&&!dropdown.disabled&&dropdown.options>1,'Dependent dropdown available for '+responsibility+' '+JSON.stringify(dropdown));
                if(responsibility==='cluster_administrator'){
                    assert.deepEqual(await evaluate(`Array.from(document.querySelector('[x-model="userForm.archiveCluster"]').options).map(option=>option.textContent)`),['Choose a cluster','Cluster A (Claveria Group)','Cluster B (Cagayan de Oro/Main Group)']);
                    assert.equal(await evaluate(`document.querySelector('[x-model="userForm.archiveCollegeIds[0]"]').closest('label').getClientRects().length`),0);
                }
            }
            await evaluate(`(()=>{const select=[...document.querySelectorAll('[x-ref=userEditor] select')].find(el=>el.getAttribute('x-model.number')==='userForm.roleId');select.value='2';select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            assert.equal(await evaluate(`document.querySelector('[x-model="userForm.archiveResponsibility"]').disabled`),false,'Auditors can select a position');
            await waitFor("document.querySelector('[data-auditor-unit-picker]').getClientRects().length>0");
            assert.equal(await evaluate(`document.querySelectorAll('[x-model="userForm.assignedDepartmentIds"]').length`),0,'Auditor assignments use a dropdown');
            await evaluate(`(()=>{const select=document.querySelector('[data-auditor-unit-picker]');select.value='000000000000000000000100';select.dispatchEvent(new Event('change',{bubbles:true}));select.value='000000000000000000000101';select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            await waitFor(`${state}.userForm.assignedDepartmentIds.length===2 && document.querySelectorAll('.um-selected-unit').length===2`);
            await evaluate("document.querySelector('.um-selected-unit button').click()");
            await waitFor(`${state}.userForm.assignedDepartmentIds.length===1 && ${state}.userForm.assignedDepartmentIds[0]==='000000000000000000000101'`);
            if(width>600) assert.equal(await evaluate(`(()=>{const role=[...document.querySelectorAll('select')].find(select=>select.getAttribute('x-model.number')==='userForm.roleId').getBoundingClientRect();const active=document.querySelector('.um-account-control').getBoundingClientRect();return Math.abs(role.top+role.height/2-active.top-active.height/2)<3;})()`),true,'Account active is centered beside the role dropdown');

            await evaluate(`${state}.userForm.departmentId='000000000000000000000100';${state}.userForm.assignedDepartmentIds=['000000000000000000000100'];${state}.userForm.archiveResponsibility='college_dean';${state}.changeResponsibility()`);
            assert.equal(await evaluate(`${state}.userForm.departmentId`),'000000000000000000000100','Position changes preserve the auditor home department');
            await waitFor(`document.querySelector('[x-model="userForm.archiveCollegeIds[0]"]').closest('label').getClientRects().length>0`);
            await evaluate(`${state}.changePrimaryRole()`);
            assert.equal(await evaluate(`${state}.userForm.archiveResponsibility`),'college_dean','Selecting Auditor preserves the position');
            await evaluate(`${state}.startUserEdit(window.fixtureUsers[1]);${state}.userForm.archiveResponsibility='faculty_staff';${state}.changeResponsibility()`);
            assert.equal(await evaluate(`${state}.userForm.departmentId===window.fixtureUsers[1].departmentId`),true,'Changing ATL responsibility preserves locked department');
            await waitFor(`document.querySelector('[x-model="userForm.departmentId"]').closest('label').getClientRects().length===0 && !document.querySelector('[x-model="userForm.departmentId"]').required`);
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await waitFor('!document.querySelector("[x-ref=userEditor]").open');
            if([1440,390].includes(width)){
                await evaluate(`${state}.clearUserFilters()`);await waitFor(`!${state}.loading`);
                await evaluate('window.scrollTo(0,0)'); await delay(60);
                const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(directory,'users-'+width+'.png'),Buffer.from(shot.result.data,'base64'));
            }
            console.log('PASS '+width+'px: users, skeleton, search, all filters, pagination, escaped text, keyboard menu, details and responsive editor');
        }
        await evaluate(`${state}.startUserCreate();Object.assign(${state}.userForm,{fullName:'New User',email:'new@example.test',roleId:3,departmentId:'000000000000000000000100',archiveResponsibility:'department_head',archiveUnitIds:['000000000000000000000100']});window.failSave=true;${state}.saveUser()`);
        await waitFor(`${state}.formError && !${state}.saving`);
        assert.equal(await evaluate('document.querySelector("[x-ref=userEditor]").open'),true,'Validation errors keep editor open');
        await evaluate(`window.failSave=false;${state}.saveUser()`);await waitFor(`!${state}.saving && !document.querySelector('[x-ref=userEditor]').open && ${state}.summary.total===13`);
        await evaluate(`${state}.startUserEdit(window.fixtureUsers.find(u=>u._id==='000000000000000000000090'));${state}.userForm.fullName='Edited User';${state}.saveUser()`);
        await waitFor(`!${state}.saving && window.fixtureUsers.find(u=>u._id==='000000000000000000000090').fullName==='Edited User'`);
        const patch=await evaluate('window.calls.filter(call=>call.method==="patch").at(-1).payload');assert.deepEqual(patch,{fullName:'Edited User'},'Unchanged fields are not rewritten');
        for(const action of ['reset','deactivate','reactivate']){
            await evaluate(`${state}.requestUserAction('${action}',window.fixtureUsers.find(u=>u._id==='000000000000000000000090'));${state}.confirmUserAction()`);
            await waitFor(`!${state}.saving && !document.querySelector('[x-ref=userAction]').open && !${state}.loading`);
        }
        await evaluate(`window.failList=true;${state}.load()`);await waitFor(`${state}.error && !${state}.loading`);
        await waitFor('document.querySelector(".um-table-wrap").getClientRects().length===0 && [...document.querySelectorAll(".page-notification.is-error")].some(node=>node.getClientRects().length>0 && node.textContent.includes("Users could not be loaded"))');
        await evaluate(`window.failList=false;[...document.querySelectorAll('.page-notification.is-error')].find(node=>node.textContent.includes('Users could not be loaded')).querySelector('.page-notification-actions button').click()`);await waitFor(`!${state}.loading && !${state}.error`);
        assert.equal(exceptions.length,0,JSON.stringify(exceptions));
        console.log('PASS create, atomic edit payload, validation errors, reset confirmation, deactivate/reactivate, failure and retry');
        await send('Browser.close');
    }finally{clearTimeout(watchdog);ws?.close();browser.kill();}
})().catch(error=>{console.error(error);process.exitCode=1;});
