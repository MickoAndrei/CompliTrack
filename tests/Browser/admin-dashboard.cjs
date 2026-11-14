// Isolated dashboard layout and pagination checks; no application records are written.
const { execFileSync, spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/admin-dashboard');
fs.mkdirSync(directory, { recursive: true });
const fragment = execFileSync('php', ['-r', `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo view('admin.atl-dashboard')->render();`], { cwd: root, encoding: 'utf8', windowsHide: true });
const workspace = fs.readFileSync(path.join(root, 'resources/js/atl-workspace.js'), 'utf8').replace("import { onDocumentReady } from './dom-ready';", 'const onDocumentReady = callback => document.addEventListener("DOMContentLoaded", callback);');
const fixture = `
window.calls=[];window.empty=false;window.fail=false;window.hold=true;window.pending=[];
const assignments=Array.from({length:12},(_,i)=>({id:String(i+1).padStart(24,'0'),reference:'AUDIT-'+(i+1),title:'Assigned process audit',department:'Department of Computer Engineering',auditor:'Assigned Auditor',status:'submitted',progress:{evaluated:3,total:5,percent:60}}));
window.axios={get:async(url,{params})=>{
 window.calls.push(params);if(window.hold)await new Promise(resolve=>window.pending.push(resolve));if(window.fail)throw Error('Fixture outage');
 const rows=window.empty?[]:assignments,last=Math.max(1,Math.ceil(rows.length/params.perPage)),page=Math.min(params.page,last);
 return {data:{data:{stats:{activeAudits:12,pendingReviews:8,openCars:3,overdueCars:2},people:{auditors:5,auditees:20},
 attention:window.empty?[]:assignments.slice(0,8).map(a=>({...a,kind:'audit',reason:'Pending audit review'})),
 assignments:rows.slice((page-1)*params.perPage,page*params.perPage),assignmentPage:page,assignmentPages:last,assignmentTotal:rows.length,
 departmentCompliance:window.empty?[]:Array.from({length:5},(_,i)=>({id:i,name:['Computer Engineering','Civil Engineering','Electrical Engineering','Information Technology','Mechanical Engineering'][i],percentage:i===0?null:75,completedAudits:3,counts:{C:3,N:1,O:1}}))}}};
}};`;
const styles = ['fonts','admin-portal','admin-ui','user-management','pagination'].map(name=>`<link rel="stylesheet" href="/css/${name}.css">`).join('');
const server = http.createServer((req,res)=>{
 const url = new URL(req.url, 'http://localhost');
 if(url.pathname==='/') {res.setHeader('Content-Type','text/html; charset=utf-8');res.end(`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${styles}<style>[x-cloak]{display:none!important}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}</style><script>${fixture}</script><script>${workspace}</script><script defer src="/alpine.js"></script><body data-portal-role="1"><main id="main-content" style="padding:24px">${fragment}</main>`);return;}
 const file=url.pathname==='/alpine.js'?path.join(root,'node_modules/alpinejs/dist/cdn.min.js'):path.resolve(root,'public','.'+url.pathname);
 if(!file.startsWith(root+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
 res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(file));
});
const delay = ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{let browser,ws;const timer=setTimeout(()=>{console.error('Dashboard browser check timed out');browser?.kill();server.close();process.exit(1);},60000);
try{
 await new Promise(r=>server.listen(9368,'127.0.0.1',r));
 browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless','--disable-gpu','--remote-debugging-port=9369',`--user-data-dir=${path.join(directory,'edge')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
 let tabs;for(let i=0;i<60;i++){try{tabs=await(await fetch('http://127.0.0.1:9369/json')).json();break;}catch{await delay(200);}}assert.ok(tabs);
 ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let seq=0;const pending=new Map(),errors=[];ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
 const send=(method,params={})=>new Promise(r=>{const id=++seq;pending.set(id,r);ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.result.exceptionDetails)throw Error(r.result.exceptionDetails.text);return r.result.result.value;};
 const wait=async expression=>{for(let i=0;i<80;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out: '+expression);};
 const checkHeights=async()=>{
  const state=await evaluate(`(()=>{const cards=[...document.querySelectorAll('.atl-overview-grid > .atl-card')];return {heights:cards.map(el=>Math.round(el.getBoundingClientRect().height)),expected:innerWidth<=600?840:600,scrollable:[...cards,...document.querySelectorAll('.atl-panel-content')].some(el=>['auto','scroll'].includes(getComputedStyle(el).overflowY)),clipped:cards.some(el=>el.scrollHeight>el.clientHeight+1)}})()`);
  assert.deepEqual(state.heights,[state.expected,state.expected],'Both panels keep their height');
  assert.equal(state.scrollable,false,'Panels have no internal scrolling');
  assert.equal(state.clipped,false,'All panel content fits');
  const dividers=await evaluate(`[...document.querySelectorAll('.atl-panel-header')].map(el=>el.getBoundingClientRect().bottom-el.closest('.atl-card').getBoundingClientRect().top)`);
  assert.ok(Math.abs(dividers[0]-dividers[1])<1,'Header dividers align in both cards');
 };
 const checkSkeletons=async()=>{
  await wait(`window.pending?.length===1`);
  await wait(`[...document.querySelectorAll('.atl-panel-skeleton')].filter(el=>el.getClientRects().length).length===2`);
  assert.equal(await evaluate(`[...document.querySelectorAll('.atl-panel-skeleton')].filter(el=>el.getClientRects().length).length`),2);
  assert.equal(await evaluate(`document.querySelectorAll('.atl-panel-skeleton .atl-skeleton-row').length`),10);
  assert.equal(await evaluate(`[...document.querySelectorAll('.atl-panel-content')].some(el=>el.getClientRects().length)`),false,'Live content stays hidden while loading');
  await checkHeights();
 };
 const release=()=>evaluate(`window.hold=false;window.pending.splice(0).forEach(resolve=>resolve())`);
 await send('Page.enable');await send('Runtime.enable');
 for(const width of [1440,1024,768,390,320]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});await send('Page.navigate',{url:'http://127.0.0.1:9368/'});
  await checkSkeletons();await release();
  await wait(`document.querySelectorAll('.um-table tbody tr').length===5`);
  await wait(`[...document.querySelectorAll('.atl-panel-skeleton')].every(el=>!el.getClientRects().length)`);await checkHeights();
  assert.equal(await evaluate(`document.querySelectorAll('.atl-attention-row').length`),5);
  assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`),false,`Fits ${width}px`);
  assert.equal(await evaluate(`(()=>{const a=document.querySelector('.atl-attention-card').getBoundingClientRect(),b=document.querySelector('.atl-compliance-card').getBoundingClientRect();return Math.abs(a.top-b.top)<2})()`),width>1000);
  await evaluate(`document.querySelector('.atl-attention-pagination button:last-child').click()`);await wait(`document.querySelectorAll('.atl-attention-row').length===3`);
  await checkHeights();
  await evaluate(`document.querySelector('.atl-attention-pagination button:first-child').click()`);await wait(`document.querySelectorAll('.atl-attention-row').length===5`);
  await evaluate(`window.hold=true;document.querySelector('.admin-dashboard > .atl-section-heading button').click()`);await checkSkeletons();await release();
  await wait(`[...document.querySelectorAll('.atl-panel-content')].every(el=>el.getClientRects().length)`);await checkHeights();
  await evaluate(`document.querySelector('.atl-progress-footer .app-pagination button:last-child').click()`);await wait(`document.querySelector('.atl-progress-footer .app-pagination-status').textContent==='Page 2 of 3'`);
  await evaluate(`document.querySelector('.atl-progress-footer select').value='10';document.querySelector('.atl-progress-footer select').dispatchEvent(new Event('change',{bubbles:true}))`);await wait(`document.querySelectorAll('.um-table tbody tr').length===10`);
  assert.equal(await evaluate(`document.querySelector('.atl-progress-footer .app-pagination-status').textContent`),'Page 1 of 2');
  await evaluate(`document.querySelector('.atl-progress-footer .app-pagination button:last-child').click()`);await wait(`document.querySelectorAll('.um-table tbody tr').length===2`);
  assert.equal(await evaluate(`document.querySelector('.atl-progress-footer .app-pagination button:last-child').disabled`),true);
  await evaluate(`document.querySelector('.atl-progress-footer select').value='5';document.querySelector('.atl-progress-footer select').dispatchEvent(new Event('change',{bubbles:true}))`);await wait(`document.querySelectorAll('.um-table tbody tr').length===5`);
  const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(directory,`dashboard-${width}.png`),Buffer.from(shot.result.data,'base64'));
  console.log(`${width}px: equal stable heights, initial/refresh skeletons, attention and assignment paging passed`);
 }
 await evaluate(`window.empty=true;document.querySelector('.admin-dashboard > .atl-section-heading button').click()`);await wait(`document.querySelectorAll('.um-table tbody tr').length===0`);
 assert.equal(await evaluate(`document.querySelector('.atl-progress-footer .app-pagination-status').textContent`),'Page 1 of 1');
 await checkHeights();
 await evaluate(`window.fail=true;document.querySelector('.admin-dashboard > .atl-section-heading button').click()`);await wait(`document.querySelector('.atl-error').textContent.includes('Unable to load')`);
 await checkHeights();
 assert.deepEqual(errors,[]);console.log('Empty and error states passed');
}finally{clearTimeout(timer);ws?.close();browser?.kill();server.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
