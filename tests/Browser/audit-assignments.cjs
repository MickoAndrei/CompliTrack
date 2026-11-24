// Uses HTML rendered from isolated MongoDB integration fixtures. No live writes.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/assignment-ui');
const publicRoot = path.join(root, 'public');
const planFixtures = path.join(root, 'storage/framework/testing/plan-ui');
let planMode = 'preparer';
const manifest = JSON.parse(fs.readFileSync(path.join(publicRoot, 'build/manifest.json')));
const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    if (req.method !== 'GET') { res.writeHead(405); res.end('Fixture server permits reads only'); return; }
    if (url.pathname === '/api/v1/roles' || url.pathname === '/api/v1/audit-plans') {
        const file = url.pathname.endsWith('/roles') ? 'roles.json' : planMode === 'approver' ? 'approval.json' : 'preparer.json';
        res.setHeader('Content-Type', 'application/json'); res.end(fs.readFileSync(path.join(planFixtures, file))); return;
    }
    if (url.pathname.startsWith('/api/')) {
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify({ data: [], meta: { unread: 0, total: 0, lastPage: 1 }, unreadCount: 0 })); return;
    }
    if (/^\/audits\/[a-f0-9]{24}$/i.test(url.pathname)) {
        res.setHeader('Content-Type', 'text/html'); res.end(fs.readFileSync(path.join(fixtures, 'review.html'))); return;
    }
    if (['/audits', '/assignments.html', '/reviews.html', '/roles.html', '/plans.html', '/approvals.html'].includes(url.pathname)) {
        const isPlanPage = ['/roles.html', '/plans.html', '/approvals.html'].includes(url.pathname);
        if (isPlanPage) planMode = url.pathname === '/approvals.html' ? 'approver' : 'preparer';
        let html = fs.readFileSync(path.join(isPlanPage ? planFixtures : fixtures, url.pathname === '/audits' ? 'assignments.html' : url.pathname.slice(1)), 'utf8');
        html = html.replace(/https?:\/\/[^/"'\s]+/g, '')
            .replace(/\/build\/assets\/app-[^"\s]+\.js/g, '/build/' + manifest['resources/js/app.js'].file)
            .replace(/\/build\/assets\/app-[^"\s]+\.css/g, '/build/' + manifest['resources/css/app.css'].file);
        if (!html.includes('type="module"')) {
            html = html.replace('</head>', `<link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}"><script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></head>`);
        }
        res.setHeader('Content-Type', 'text/html'); res.end(html); return;
    }
    const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(publicRoot + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
    res.setHeader('Content-Type', ({ '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2' })[path.extname(file)] || 'application/octet-stream');
    res.end(fs.readFileSync(file));
});
(async () => {
    await new Promise(resolve => server.listen(9350, '127.0.0.1', resolve));
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--remote-debugging-port=9351', `--user-data-dir=${path.join(fixtures, 'edge')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    try {
        let tabs;
        for (let i = 0; i < 50; i++) { try { tabs = await (await fetch('http://127.0.0.1:9351/json')).json(); break; } catch { await new Promise(r => setTimeout(r, 200)); } }
        assert.ok(tabs, 'Browser starts');
        ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
        let seq = 0;
        const pending = new Map(), errors = [];
        ws.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.id) { pending.get(data.id)?.(data); pending.delete(data.id); }
            if (data.method === 'Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.text + ': ' + data.params.exceptionDetails.exception?.description);
        });
        const send = (method, params = {}) => new Promise(resolve => { const id = ++seq; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params })); });
        const evaluate = async expression => {
            const data = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
            if (data.result?.exceptionDetails) throw Error(data.result.exceptionDetails.exception?.description || 'Browser evaluation failed');
            return data.result?.result?.value;
        };
        const waitFor = async expression => {
            for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await new Promise(r => setTimeout(r, 100)); }
            throw Error('Timed out: ' + expression + '\n' + errors.join('\n'));
        };
        await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable');
        await send('Network.setBlockedURLs', { urls: ['https://*'] });
        for (const width of [1440, 768, 390]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
            if (!process.env.AUDIT_PLANS_ONLY) {
            await send('Page.navigate', { url: 'http://127.0.0.1:9350/assignments.html' });
            await waitFor('!!window.Alpine && !document.documentElement.hidden && !!document.querySelector("[data-new-assignment]")');
            await new Promise(r => setTimeout(r, 300));
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false, `Register fits ${width}px`);
            assert.ok(await evaluate(`(()=>{const pill=document.querySelector('[data-assignment-results] .assignment-status'),original=pill.textContent,cell=pill.closest('td'),next=cell.nextElementSibling;const valid=['Submitted — Awaiting ATL Review','Returned for Revision','Accepted / Completed'].every(label=>{pill.textContent=label;return pill.getBoundingClientRect().right<=cell.getBoundingClientRect().right&&pill.getBoundingClientRect().right<=next.getBoundingClientRect().left;});pill.textContent=original;return valid;})()`), 'Long status labels stay inside their column without overlapping assignment actions');
            if (width > 640) {
                assert.ok(await evaluate(`(()=>{const main=document.querySelector('#main-content'),register=document.querySelector('.assignment-register'),controls=register.querySelector('.assignment-controls'),table=register.querySelector('[data-assignment-results] table'),body=table.tBodies[0],header=table.querySelector('th');const copies=Array.from({length:40},()=>body.rows[0].cloneNode(true));body.append(...copies);main.scrollTop=600;const controlTop=controls.getBoundingClientRect().top,headerTop=header.getBoundingClientRect().top;main.scrollTop=800;const valid=main.scrollTop>=800&&Math.abs(controls.getBoundingClientRect().top-controlTop)<2&&Math.abs(header.getBoundingClientRect().top-headerTop)<2&&header.getBoundingClientRect().top>=controls.getBoundingClientRect().bottom-2&&getComputedStyle(table.parentElement).overflowY==='visible';copies.forEach(copy=>copy.remove());main.scrollTop=0;return valid;})()`), 'Register follows User Management: page scrolls while controls and column headers stay pinned');
            }
            assert.equal(await evaluate('document.querySelectorAll("a[data-atl-review]").length'), 0);
            assert.equal(await evaluate('document.querySelector(".header-page-subtitle").textContent.trim()'), 'Assign auditors to departments and offices based on authorized audit plans.');
            assert.equal(await evaluate('!!document.querySelector(".assignment-register .assignment-filter-actions [data-new-assignment]")'), true, 'New assignment sits below the fields inside the register');
            assert.equal(await evaluate('document.querySelector(".app-pagination-status").textContent.trim()'), 'Page 1 of 1');
            assert.equal(await evaluate('document.querySelectorAll(".app-pagination [aria-disabled=true]").length'), 2, 'Single-page registers keep pagination visible with unavailable directions disabled');
            await evaluate(`(()=>{const form=document.querySelector('[data-assignment-filters]');form.elements.search.value='Workflow';form.elements.search.dispatchEvent(new Event('input',{bubbles:true}));})()`);
            await waitFor('new URL(location.href).searchParams.get("search")==="Workflow" && document.querySelector("[data-assignment-loading]").hidden');
            await evaluate(`(()=>{const form=document.querySelector('[data-assignment-filters]');for(const name of ['departmentId','auditorId'])form.elements[name].selectedIndex=1;form.elements.status.value='draft';form.elements.status.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            await waitFor('(()=>{const query=new URL(location.href).searchParams;return query.get("status")==="draft"&&!!query.get("departmentId")&&!!query.get("auditorId")&&query.get("search")==="Workflow";})()');
            await evaluate('document.querySelector(".assignment-filter-actions a").click()');
            await waitFor('[...document.querySelectorAll("[data-assignment-filters] input[type=search],[data-assignment-filters] select")].every(field=>!field.value) && !new URL(location.href).searchParams.has("search")');
            await evaluate('document.querySelector("[data-view-audit-scope]").click()');
            await waitFor('document.querySelector("#audit-scope-dialog").open');
            assert.equal(await evaluate(`(()=>{const dialog=document.querySelector('#audit-scope-dialog'),header=dialog.querySelector('header'),body=dialog.querySelector('.assignment-modal-body');dialog.querySelector('[data-scope-content]').textContent='Long scope details. '.repeat(300);const box=dialog.getBoundingClientRect(),top=header.getBoundingClientRect().top;body.scrollTop=body.scrollHeight;return box.left>=0&&box.right<=innerWidth&&box.top>=0&&box.bottom<=innerHeight&&getComputedStyle(header).backgroundColor==='rgb(0, 32, 91)'&&getComputedStyle(dialog).overflowY==='hidden'&&body.scrollTop>0&&header.getBoundingClientRect().top===top&&dialog.querySelectorAll('button').length===1;})()`),true,'Scope popup has one close control, a fixed navy header and scrollable content');
            await evaluate('document.querySelector("[data-close-audit-scope]").click()');
            await waitFor('!document.querySelector("#audit-scope-dialog").open && document.activeElement.matches("[data-view-audit-scope]")');
            await evaluate('document.querySelector("[data-new-assignment]").click()');
            await waitFor('document.querySelector("#audit-assignment-dialog").open');
            assert.ok(await evaluate('(()=>{const r=document.querySelector("#audit-assignment-dialog").getBoundingClientRect();return r.left>=0 && r.right<=innerWidth && r.top>=0 && r.bottom<=innerHeight})()'), 'Assignment modal fits');
            assert.equal(await evaluate(`(()=>{const dialog=document.querySelector('#audit-assignment-dialog'),body=dialog.querySelector('.assignment-modal-body'),header=dialog.querySelector('header'),footer=dialog.querySelector('footer');const top=header.getBoundingClientRect().top,bottom=footer.getBoundingClientRect().bottom;body.scrollTop=body.scrollHeight;const valid=getComputedStyle(header).backgroundColor==='rgb(0, 32, 91)'&&getComputedStyle(dialog.querySelector('[data-save-assignment]')).backgroundColor==='rgb(255, 199, 44)'&&getComputedStyle(dialog).overflowY==='hidden'&&getComputedStyle(document.body).overflowY==='hidden'&&body.scrollTop>0&&header.getBoundingClientRect().top===top&&footer.getBoundingClientRect().bottom===bottom;body.scrollTop=0;return valid;})()`),true,'Create User popup styling keeps header/footer fixed and only the body scrolls');
            assert.equal(await evaluate('document.querySelector(".assignment-modal-form").checkValidity()'), false);
            await evaluate('(()=>{const p=document.querySelector("select[name=planId]");p.selectedIndex=1;p.dispatchEvent(new Event("change",{bubbles:true}));const u=document.querySelector("#audit-assignment-dialog select[name=departmentId]");u.value=[...u.options].find(o=>o.value&&!o.disabled).value;u.dispatchEvent(new Event("change",{bubbles:true}))})()');
            assert.equal(await evaluate('document.querySelector("[data-plan-scope]").value'), 'Process');
            assert.equal(await evaluate('[...document.querySelector("#audit-assignment-dialog select[name=auditorId]").options].filter(o=>o.value&&!o.disabled).length'), 1);
            assert.equal(await evaluate('document.querySelector("#audit-assignment-dialog select[name=auditorId]").disabled'), false);
            await evaluate('(()=>{const o=[...document.querySelector("#audit-assignment-dialog select[name=auditorId]").options].find(o=>o.value&&!o.disabled);window.eligibleAuditorOption=o;window.eligibleAuditorUnits=o.dataset.units;o.dataset.units="[]";document.querySelector("#audit-assignment-dialog select[name=departmentId]").dispatchEvent(new Event("change",{bubbles:true}))})()');
            assert.equal(await evaluate('document.querySelector("#audit-assignment-dialog select[name=auditorId]").disabled'), true);
            assert.equal(await evaluate('document.querySelector("[data-save-assignment]").disabled'), true);
            assert.ok(await evaluate('document.querySelector("[data-assignment-auditor-help]").textContent.includes("A Super Admin must authorize")'));
            await evaluate('window.eligibleAuditorOption.dataset.units=window.eligibleAuditorUnits;document.querySelector("#audit-assignment-dialog select[name=departmentId]").dispatchEvent(new Event("change",{bubbles:true}))');
            assert.equal(await evaluate('document.querySelector("#audit-assignment-dialog select[name=auditorId]").disabled'), false);
            assert.equal(await evaluate('document.querySelector("[name=schedule]").min'), '2020-01-01');
            const shot = await send('Page.captureScreenshot', { format: 'png' });
            fs.writeFileSync(path.join(fixtures, `assignment-modal-${width}.png`), Buffer.from(shot.result.data, 'base64'));
            await evaluate('document.querySelector("[data-close-assignment]").click()');
            await waitFor('document.activeElement.matches("[data-new-assignment]")');
            await evaluate(`(()=>{window.beforeEditUrl=location.href;window.editOpener=document.querySelector('[data-edit-assignment]');const search=document.querySelector('[data-assignment-filters] [name=search]');search.value='Pending search';search.dispatchEvent(new Event('input',{bubbles:true}));window.editOpener.click();})()`);
            await new Promise(resolve => setTimeout(resolve, 450));
            assert.equal(await evaluate('location.href===window.beforeEditUrl && window.editOpener.isConnected'),true,'Opening a popup cancels a pending search and preserves its focus target');
            assert.equal(await evaluate('document.querySelector("[name=planId]").disabled && document.querySelector("#audit-assignment-dialog [name=departmentId]").disabled'), true);
            assert.equal(await evaluate('document.querySelector("[name=changeReason]").required'), true);
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await waitFor('!document.querySelector("#audit-assignment-dialog").open');
            assert.equal(await evaluate('document.activeElement===window.editOpener'),true,'Closing the popup returns focus to its assignment');
            await send('Page.navigate', { url: 'http://127.0.0.1:9350/reviews.html' });
            await waitFor('!!document.querySelector("a[data-atl-review]") && !!window.Alpine && !document.documentElement.hidden');
            await new Promise(r => setTimeout(r, 300));
            await evaluate('document.querySelector("a[data-atl-review]").click()');
            await waitFor('!!document.querySelector("[data-atl-review-form]")');
            assert.ok(await evaluate('(()=>{const r=document.querySelector("#atl-review-dialog").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()'), 'Review modal fits');
            assert.equal(await evaluate('!!document.querySelector("#atl-review-dialog [name=result]")'), false);
            console.log(`${width}px: register, header subtitle, creation/amendment modal, plan scope, eligible auditor, focus, Escape and review modal passed`);
            }
            if (fs.existsSync(planFixtures)) {
                await send('Page.navigate', { url: 'http://127.0.0.1:9350/roles.html' });
                await waitFor('document.querySelector("[x-data=auditPlanPermissions]")?._x_dataStack?.[0]?.loading === false');
                assert.equal(await evaluate('document.querySelector("[x-data=auditPlanPermissions]")._x_dataStack[0].enabled'), false, 'Super Admin wildcard does not grant approval');
                await evaluate('(()=>{const c=document.querySelector("[x-data=auditPlanPermissions]")._x_dataStack[0];c.selected=3;c.choose()})()');
                assert.equal(await evaluate('document.querySelector("[x-data=auditPlanPermissions]")._x_dataStack[0].enabled'), true, 'Explicit role grant is shown');
                assert.equal(await evaluate('document.querySelector(".plan-permission-form").checkValidity()'), false, 'Reason and current password are required');
                assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
                await send('Page.navigate', { url: 'http://127.0.0.1:9350/plans.html' });
                await waitFor('document.querySelector("[x-data=auditPlans]")?._x_dataStack?.[0]?.loading === false');
                await evaluate('window.planResultsHeight=document.querySelector(".plan-results").getBoundingClientRect().height; document.querySelector("[x-data=auditPlans]")._x_dataStack[0].loading=true');
                await waitFor('document.querySelector(".plan-skeleton-row").getClientRects().length > 0');
                assert.ok(await evaluate('document.querySelector(".plan-results").getBoundingClientRect().height >= window.planResultsHeight'), 'Loading reserves the results area');
                assert.equal(await evaluate('document.querySelector("[x-data=auditPlans]").getAttribute("aria-busy")'), 'true');
                await evaluate('document.querySelector("[x-data=auditPlans]")._x_dataStack[0].loading=false');
                await evaluate('document.querySelector("[x-data=auditPlans]")._x_dataStack[0].edit()');
                await waitFor('document.querySelector("[x-ref=editor]").open');
                assert.ok(await evaluate('(()=>{const dialog=document.querySelector("[x-ref=editor]"),body=dialog.querySelector(".plan-prepare-body"),header=dialog.querySelector("header"),footer=dialog.querySelector("footer");const top=header.getBoundingClientRect().top,bottom=footer.getBoundingClientRect().bottom;body.scrollTop=body.scrollHeight;const valid=getComputedStyle(dialog).overflowY==="hidden"&&body.scrollTop>0&&header.getBoundingClientRect().top===top&&footer.getBoundingClientRect().bottom===bottom;body.scrollTop=0;return valid;})()'), 'Editor scrollbar stays inside the body with header, footer and rounded shell fixed');
                assert.ok(await evaluate('(()=>{const r=document.querySelector("[x-ref=editor]").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()'), 'Plan editor fits');
                assert.equal(await evaluate('document.querySelector("[x-ref=editor] form").checkValidity()'), false);
                await send('Page.navigate', { url: 'http://127.0.0.1:9350/approvals.html' });
                await waitFor('document.querySelector("[x-data=auditPlans]")?._x_dataStack?.[0]?.loading === false');
                assert.equal(await evaluate('document.querySelector("[x-data=auditPlans]")._x_dataStack[0].data.canApprove'), true);
                await evaluate('document.querySelector(".audit-plans-table tbody button").click()');
                await waitFor('document.querySelector("[x-ref=details]").open');
                assert.equal(await evaluate('getComputedStyle(document.querySelector(".plan-details header")).backgroundColor'), 'rgb(0, 32, 91)', 'Details use the navy popup header');
                assert.equal(await evaluate('document.querySelector(".plan-details .plan-history").tagName'), 'SECTION', 'History is visible without expanding a disclosure');
                assert.ok(await evaluate('[...document.querySelectorAll(".plan-details .plan-history p")].some(entry=>entry.getClientRects().length > 0)'), 'History entries are displayed immediately');
                assert.ok(await evaluate('(()=>{const dialog=document.querySelector(".plan-details"),body=dialog.querySelector(".plan-details-body"),header=dialog.querySelector("header"),top=header.getBoundingClientRect().top; const extra=document.createElement("div"); extra.style.height="2000px";body.append(extra);body.scrollTop=body.scrollHeight;const box=dialog.getBoundingClientRect(),valid=body.scrollTop>0&&header.getBoundingClientRect().top===top&&box.top>=0&&box.bottom<=innerHeight;extra.remove();body.scrollTop=0;return valid;})()'), 'Long details scroll beneath a fixed header inside the viewport');
                assert.equal(await evaluate('document.activeElement.closest("dialog")?.getAttribute("x-ref")'), 'details', 'Details receive focus after content renders');
                assert.ok(await evaluate('[...document.querySelectorAll("button")].some(b=>b.textContent.trim()==="Approve Plan"&&b.getClientRects().length>0)'), 'Authorized approval action is visible');
                assert.equal(await evaluate('document.querySelector(".plan-action-form").checkValidity()'), false, 'Decision remarks required');
                assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
                console.log(`${width}px: explicit role permission controls, plan editor and approval queue passed`);
            }
        }
        assert.deepEqual(errors, [], 'No browser runtime errors');
    } finally {
        if (ws?.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ id: 999999, method: 'Browser.close' }));
        ws?.close(); browser.kill(); server.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
