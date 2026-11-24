// Run scripts/verify-atl-workspace.php first. Reads private live snapshots;
// review POSTs are simulated locally and are NEVER forwarded to the application.
const {spawn} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/atl-live');
const publicRoot = path.join(root, 'public');
const review = JSON.parse(fs.readFileSync(path.join(fixtures, 'review.json')));
const original = fs.readFileSync(path.join(fixtures, 'review.html'), 'utf8');
let decision = null, conflict = false, version = review.version, posts = 0;
const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    if (req.method === 'POST') {
        assert.equal(url.pathname, `/audits/${review.id}`);
        let payload = ''; for await (const chunk of req) payload += chunk;
        assert.ok(payload.includes('name="reviewNotes"')); assert.ok(payload.includes('name="_token"'));
        assert.ok(payload.includes('Browser test review notes'));
        posts++; res.setHeader('Content-Type', 'application/json');
        if (conflict) { conflict = false; version++; res.writeHead(409); res.end(JSON.stringify({message:'This audit changed. Review the latest submission.'})); return; }
        const action = payload.match(/name="action"\r\n\r\n(accept|return)/)?.[1];
        assert.ok(action); decision = action;
        res.end(JSON.stringify({message:'Audit review recorded.',status:action==='accept'?'accepted':'returned'})); return;
    }
    if (url.pathname === `/audits/${review.id}`) {
        res.setHeader('Content-Type', 'text/html');
        let html = original.replace(/name="version" value="\d+"/, `name="version" value="${version}"`);
        if (decision) html = html.replace(/<form[^>]+data-atl-review-form[\s\S]*?<\/form>/, `<p>Recorded local browser-test decision: ${decision}</p>`);
        res.end(html); return;
    }
    if (url.pathname === '/api/v1/admin/workspace') { res.setHeader('Content-Type','application/json');res.end(fs.readFileSync(path.join(fixtures,'dashboard.json')));return; }
    if (url.pathname === '/admin/compliance-records' && url.searchParams.get('history')==='1') {
        const id=url.searchParams.get('car'); assert.match(id,/^[a-f0-9]{24}$/i);
        res.setHeader('Content-Type','text/html');res.end(fs.readFileSync(path.join(fixtures,`car-${id}.html`)));return;
    }
    const apiFixtures = {'/api/v1/notifications':'notifications','/api/v1/users':'users','/api/v1/departments':'departments','/api/v1/compliance-checks':'checks','/api/v1/reports':'reports','/api/v1/documents/tree':'tree','/api/v1/documents':'archives'};
    if(apiFixtures[url.pathname]) {res.setHeader('Content-Type','application/json');res.end(fs.readFileSync(path.join(fixtures,apiFixtures[url.pathname]+'.json')));return;}
    const pages=['dashboard','assignments','users','departments','compliance-records','notifications','reports','archives','monitor'];
    const fixturePage=pages.find(page=>url.pathname===`/${page}.html`);
    const file = fixturePage ? path.join(fixtures,`${fixturePage}.html`) : path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if ((!fixturePage && !file.startsWith(publicRoot + path.sep)) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404);res.end();return; }
    res.setHeader('Content-Type', ({'.js':'text/javascript','.css':'text/css','.html':'text/html','.jpg':'image/jpeg'})[path.extname(file)] || 'application/octet-stream');
    if (fixturePage) {
        const manifest = JSON.parse(fs.readFileSync(path.join(publicRoot,'build/manifest.json')));
        res.end(fs.readFileSync(file,'utf8').replace(/src="\/build\/assets\/app-[^"]+\.js"/, `src="/build/${manifest['resources/js/app.js'].file}"`));
    } else res.end(fs.readFileSync(file));
});
(async () => {
    await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(9340,'127.0.0.1',resolve);});
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless','--disable-gpu','--remote-debugging-port=9341',`--user-data-dir=${path.join(fixtures,'edge')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
    let ws;
    try {
        let tabs;
        for(let i=0;i<40;i++){try{tabs=await(await fetch('http://127.0.0.1:9341/json',{signal:AbortSignal.timeout(1000)})).json();break;}catch{await new Promise(r=>setTimeout(r,200));}}
        assert.ok(tabs,'Browser started');
        ws = new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
        await new Promise(r=>ws.addEventListener('open',r,{once:true}));
        let seq=0;const pending=new Map(),errors=[];
        ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
        const send=(method,params={})=>new Promise(r=>{const id=++seq;pending.set(id,r);ws.send(JSON.stringify({id,method,params}));});
        const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.result.exceptionDetails)throw Error(r.result.exceptionDetails.text);return r.result.result.value;};
        const waitFor=async expression=>{for(let i=0;i<60;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error(`Timed out: ${expression}`);};
        await send('Page.enable');await send('Runtime.enable');
        for(const width of [1440,768,390]) {
            decision=null;version=review.version;
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
            await send('Page.navigate',{url:'http://127.0.0.1:9340/dashboard.html'});
            await waitFor(`document.querySelectorAll('.atl-attention-row').length > 0`);
            assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`),false,`Dashboard fits ${width}px`);
            assert.equal(await evaluate(`document.querySelectorAll('.stat-card').length`),4);
            assert.equal(await evaluate(`document.querySelectorAll('.atl-compliance-row').length`),5);
            assert.equal(await evaluate(`document.body.innerText.includes('Quick actions')`),false);
            const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(fixtures,`dashboard-${width}.png`),Buffer.from(shot.result.data,'base64'));
            await evaluate(`window.reviewOpener=document.querySelector('a[data-atl-review][href="/audits/${review.id}"]');window.reviewOpener.click()`);
            await waitFor(`!!document.querySelector('[data-atl-review-form]')`);
            assert.ok(await evaluate(`(()=>{const d=document.querySelector('#atl-review-dialog'),r=d.getBoundingClientRect();return d.open&&r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()`),`Popup fits ${width}px`);
            assert.ok(await evaluate(`document.querySelector('.atl-review-body').scrollHeight>document.querySelector('.atl-review-body').clientHeight`),'Long review body scrolls');
            assert.equal(await evaluate(`document.querySelector('[data-atl-review-form]').checkValidity()`),false,'Review notes required');
            if(width===1440) {
                conflict=true;
                await evaluate(`document.querySelector('[name=reviewNotes]').value='Browser test review notes';document.querySelector('[name=reviewNotes]').dispatchEvent(new Event('input'));document.querySelector('button[value=accept]').click()`);
                await waitFor(`document.querySelector('[data-atl-review-feedback]').textContent.includes('This audit changed')`);
                assert.equal(await evaluate(`Number(document.querySelector('[name=version]').value)`),version);
            }
            const modalShot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(fixtures,`review-${width}.png`),Buffer.from(modalShot.result.data,'base64'));
            await evaluate(`document.querySelector('[name=reviewNotes]').value='Browser test review notes';document.querySelector('button[value=${width===1440?'return':'accept'}]').click()`);
            await waitFor(`document.querySelector('[data-atl-review-feedback]').textContent==='Audit review recorded.'`);
            assert.equal(await evaluate(`!!document.querySelector('[data-atl-review-form]')`),false,'Decision removes action form');
            await evaluate(`document.querySelector('[data-atl-review-close]').click()`);
            await waitFor(`!document.querySelector('#atl-review-dialog').open&&document.activeElement?.href===window.reviewOpener.href`);
            // Reopen a read-only result; Escape must close the native dialog.
            await evaluate(`window.reviewOpener.click()`);await waitFor(`!!document.querySelector('.atl-review-record')`);
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await waitFor(`!document.querySelector('#atl-review-dialog').open`);
            console.log(`${width}px: real-record dashboard, review popup, notes, decision, focus, Escape passed`);
        }
        for(const width of [1440,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
            for(const page of ['assignments','users','departments','compliance-records','notifications','reports','archives','monitor']) {
                await send('Page.navigate',{url:`http://127.0.0.1:9340/${page}.html`});
                await waitFor(`!!window.Alpine`);
                if(['users','departments','compliance-records','notifications'].includes(page)) {
                    await waitFor(`document.querySelector('.admin-resource-page')?._x_dataStack?.[0]?.loading === false`);
                    assert.ok(await evaluate(`document.querySelector('.admin-resource-page')._x_dataStack[0].rows.length>0`),`${page} loads authorized records`);
                } else if(page==='archives') await waitFor(`document.querySelector('.qms-explorer')?._x_dataStack?.[0]?.initialLoading===false`);
                else if(page==='reports') await waitFor(`document.querySelector('.admin-reports')?._x_dataStack?.[0]?.loading===false`);
                assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`),false,`${page} fits ${width}px`);
                if(['users','departments'].includes(page)) {
                    assert.equal(await evaluate(`!!document.querySelector('.user-create-trigger, .department-create-trigger')`),false,'Admin creation buttons are absent');
                    assert.equal(await evaluate(`!!document.querySelector('.user-modal-form, .department-modal-form, td[data-label="Actions"]')`),false,'Admin directories are read-only');
                    assert.equal(await evaluate(`document.querySelector('.admin-resource-page')._x_dataStack[0].perPage`),10);
                    assert.ok(await evaluate(`document.querySelector('.admin-resource-page')._x_dataStack[0].rows.length<=10`),'No more than ten directory records per page');
                    await evaluate(`document.querySelector('.admin-resource-page')._x_dataStack[0].loading=true`);
                    await waitFor(`!!document.querySelector('.admin-directory-loading .sa-skeleton')`);
                    assert.equal(await evaluate(`document.querySelectorAll('.admin-directory-loading tbody tr').length`),10);
                    assert.equal(await evaluate(`getComputedStyle(document.querySelector('.admin-directory-loading .sa-skeleton')).animationName`),'admin-directory-shimmer');
                    await evaluate(`document.querySelector('.admin-resource-page')._x_dataStack[0].loading=false`);
                    if(page==='users' && width===1440) {
                        await evaluate(`document.querySelector('.admin-resource-page')._x_dataStack[0].error='Popup regression message'`);
                        await waitFor(`!!document.querySelector('.page-notification.is-error')`);
                        const notificationStarted=Date.now();
                        assert.equal(await evaluate(`getComputedStyle(document.querySelector('.page-notice-source')).display`),'none');
                        await evaluate(`window.notify('Popup regression message','error')`);
                        assert.equal(await evaluate(`document.querySelectorAll('.page-notification').length`),1,'Duplicate notices do not stack');
                        await waitFor(`!document.querySelector('.page-notification')`);
                        assert.ok(Date.now()-notificationStarted>=2700 && Date.now()-notificationStarted<4200,'Notification dismisses after three seconds');
                        await evaluate(`document.querySelector('.admin-resource-page')._x_dataStack[0].error=null;window.notify('Manual dismissal','success')`);
                        await evaluate(`document.querySelector('.page-notification-close').click()`);
                        assert.equal(await evaluate(`document.querySelectorAll('.page-notification').length`),0,'Manual dismissal works');
                        await evaluate(`(()=>{const dialog=document.createElement('dialog');dialog.id='notice-test';dialog.innerHTML='<p x-page-notice role="alert">Dialog notification</p>';document.body.append(dialog);dialog.showModal()})()`);
                        await waitFor(`!!document.querySelector('#notice-test .page-notification')`);
                        assert.ok(await evaluate(`document.querySelector('#notice-test .page-notification').matches(':popover-open')`),'Popup remains in the modal top layer');
                        await evaluate(`document.querySelector('#notice-test').close();document.querySelector('#notice-test').remove()`);
                        console.log('Shared notices: inline removal, deduplication, three-second dismissal, close button, modal popup passed');
                    }
                }
                if(page==='archives' && width===1440) {
                    await evaluate(`document.querySelector('.qms-explorer')._x_dataStack[0].tree.ownershipReviewRequired=true`);
                    await waitFor(`!!document.querySelector('.page-notification.is-warning')`);
                    assert.ok(await evaluate(`document.querySelector('.page-notification.is-warning').textContent.includes('ownership review')`),'Archive ownership warning uses the shared popup');
                    assert.equal(await evaluate(`getComputedStyle(document.querySelector('[x-show="tree.ownershipReviewRequired"]')).display`),'none');
                }
                if(page==='monitor') {
                    await evaluate(`document.querySelector('[data-car-history]').click()`);
                    await waitFor(`document.querySelector('#car-history-dialog').open && !!document.querySelector('#car-history-dialog #car-history')`);
                    assert.equal(await evaluate(`document.querySelector('[data-car-history-body]').getAttribute('aria-busy')`),'false');
                    assert.ok(await evaluate(`(()=>{const r=document.querySelector('#car-history-dialog').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()`),'CAR history popup fits viewport');
                    assert.ok(await evaluate(`document.querySelector('#car-history-dialog').textContent.includes('Corrective evidence and review history')`));
                    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
                    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
                    await waitFor(`!document.querySelector('#car-history-dialog').open && document.activeElement.matches('[data-car-history]')`);
                    await evaluate(`document.querySelector('[data-car-history]').click()`);
                    await waitFor(`document.querySelector('#car-history-dialog').open`);
                    await evaluate(`document.querySelector('[data-car-history-close]').click()`);
                    assert.equal(await evaluate(`document.querySelector('#car-history-dialog').open`),false,'CAR close button works');
                }
                if(page==='notifications') assert.ok(await evaluate(`document.querySelector('.admin-notification-card').innerText.includes('Unread')||document.querySelector('.admin-notification-card').innerText.includes('Read')`),'Notification read status is meaningful');
                const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(fixtures,`${page}-${width}.png`),Buffer.from(shot.result.data,'base64'));
                console.log(`${page}: ${width}px records/layout passed`);
            }
        }
        assert.equal(posts,4);assert.deepEqual(errors,[],'No browser exceptions');
    } finally {
        if(ws?.readyState===WebSocket.OPEN) ws.send(JSON.stringify({id:999999,method:'Browser.close'}));
        ws?.close();browser.kill();server.close();
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
