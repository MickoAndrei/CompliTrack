// Uses HTML rendered from isolated MongoDB tests. All browser decisions are local simulations.
const {spawn}=require('node:child_process');const fs=require('node:fs');const path=require('node:path');const http=require('node:http');const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),directory=path.join(root,'storage/framework/testing/audit-reviews');
const queue=fs.readFileSync(path.join(directory,'queue.html'),'utf8').match(/<main[^>]*>([\s\S]*?)<\/main>/)[1];
const modal=fs.readFileSync(path.join(directory,'modal.html'),'utf8'),review=fs.readFileSync(path.join(directory,'review.html'),'utf8');
const workspace=fs.readFileSync(path.join(root,'resources/js/atl-workspace.js'),'utf8').replace(/^import .*;$/gm,'');
let posts=0,decided=false;
const server=http.createServer(async(req,res)=>{const url=new URL(req.url,'http://localhost');
 if(req.method==='POST'){posts++;decided=true;res.setHeader('Content-Type','application/json');res.end(JSON.stringify({message:'Audit review recorded.'}));return;}
 if(url.pathname==='/audits'){setTimeout(()=>{if(url.searchParams.get('search')==='fail'){res.writeHead(503);res.end('Unavailable');return;}res.setHeader('Content-Type','text/html; charset=utf-8');res.end(queue);},150);return;}
 if(url.pathname.startsWith('/audits/')){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(decided?review.replace(/<form[^>]+data-atl-review-form[\s\S]*?<\/form>/,'<p>Accepted results are read-only.</p>'):review);return;}
 if(url.pathname==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+['admin-portal','admin-ui'].map(n=>'<link rel="stylesheet" href="/css/'+n+'.css">').join('')+'<script>const onDocumentReady=cb=>document.addEventListener("DOMContentLoaded",cb);const closeDocumentEditor=()=>{};const openDocumentEditor=async()=>{};</script><script>'+workspace+'</script><body data-portal-role="1"><main style="padding:16px">'+queue+'</main>'+modal);return;}
 const file=path.resolve(root,'public','.'+url.pathname);if(!file.startsWith(path.join(root,'public')+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type','text/css');res.end(fs.readFileSync(file));
});
const delay = ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{let browser,ws;const timer=setTimeout(()=>{console.error('Review browser check timed out');browser?.kill();server.close();process.exit(1);},60000);
try{
 await new Promise(r=>server.listen(9378,'127.0.0.1',r));
 browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless','--disable-gpu','--remote-debugging-port=9379',`--user-data-dir=${path.join(directory,'edge')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
 let tabs;for(let i=0;i<60;i++){try{tabs=await(await fetch('http://127.0.0.1:9379/json')).json();break;}catch{await delay(200);}}assert.ok(tabs);
 ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let seq=0;const pending=new Map(),errors=[];ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
 const send=(method,params={})=>new Promise(r=>{const id=++seq;pending.set(id,r);ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.result.exceptionDetails)throw Error(r.result.exceptionDetails.text);return r.result.result.value;};
 const wait=async expression=>{for(let i=0;i<80;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out: '+expression);};
 await send('Page.enable');await send('Runtime.enable');
 for(const width of [1440,1024,768,390,320]) {
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});await send('Page.navigate',{url:'http://127.0.0.1:9378/'});
  await wait('!!document.querySelector("[data-atl-review]")');
  assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false,'Queue fits '+width);
  const loading = await evaluate('(()=>{document.querySelector("select[name=status]").value="returned";document.querySelector("select[name=status]").dispatchEvent(new Event("change",{bubbles:true}));return {busy:document.querySelector("[data-review-results]").getAttribute("aria-busy"),rows:document.querySelectorAll(".review-loading-row").length}})()');
  assert.deepEqual(loading,{busy:'true',rows:5},'Skeleton appears while filtering');
  await wait('document.querySelector("[data-review-results]").getAttribute("aria-busy")==="false"');
  assert.equal(await evaluate('new URL(location.href).searchParams.get("status")'),'returned');
  await evaluate('document.querySelector("[data-review-reset]").click()');await wait('document.querySelector("[data-review-results]").getAttribute("aria-busy")==="false"');
  assert.equal(await evaluate('document.querySelector("select[name=status]").value'),'submitted');
  assert.equal(await evaluate('new URL(location.href).searchParams.has("status")'),false);
  await evaluate('document.querySelector("[name=search]").value="fail";document.querySelector("[name=search]").dispatchEvent(new Event("input",{bubbles:true}))');
  await wait('!document.querySelector("[data-review-filter-error]").hidden');
  await evaluate('document.querySelector("[data-review-reset]").click()');await wait('document.querySelector("[data-review-results]").getAttribute("aria-busy")==="false" && document.querySelector("[data-review-filter-error]").hidden');
  await evaluate('window.openerLink=document.querySelector("[data-atl-review]");window.openerLink.click()');
  await wait('!!document.querySelector("[data-atl-review-form]")');
  assert.equal(await evaluate('document.querySelector("[data-atl-review-body]").getAttribute("aria-busy")'),'false');
  assert.ok(await evaluate('(()=>{const r=document.querySelector("#atl-review-dialog").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight})()'));
  if(width===1440) assert.ok(await evaluate('document.querySelector("#atl-review-dialog").getBoundingClientRect().width/innerWidth>=.8'));
  await evaluate('document.querySelector("button[value=return]").click()');assert.equal(await evaluate('document.querySelector("#atl-review-confirm").open'),false);
  await evaluate('document.querySelector("[name=reviewNotes]").value="Clarify the recorded finding";document.querySelector("[name=reviewNotes]").dispatchEvent(new Event("input",{bubbles:true}));document.querySelector("button[value=return]").click()');
  await wait('document.querySelector("#atl-review-confirm").open');
  await evaluate('document.querySelector("#atl-review-confirm button[value=cancel]").click()');await delay(100);assert.equal(posts,0);
  await evaluate('document.querySelector("button[value=accept]").click()');await wait('document.querySelector("#atl-review-confirm").open');
  await evaluate('document.querySelector("#atl-review-confirm button[value=confirm]").click()');
  await wait('document.querySelector("[data-atl-review-feedback]").textContent.includes("recorded")');
  assert.equal(await evaluate('!!document.querySelector("[data-atl-review-form]")'),false);
  // Avoid the production queue reload after a simulated decision so focus can be inspected.
  await evaluate('history.replaceState({},"","/");document.querySelector("[data-atl-review-close]").click()');
  await wait('document.activeElement===window.openerLink');
  await evaluate('window.openerLink.click()');await wait('document.querySelector("#atl-review-dialog").open');
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await wait('!document.querySelector("#atl-review-dialog").open');
  const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(directory,'queue-'+width+'.png'),Buffer.from(shot.result.data,'base64'));
  posts=0;decided=false;console.log(width+'px: automatic filters, skeleton, reset/error, queue, modal, revision feedback, confirmation/cancel, acceptance, read-only state, focus and Escape passed');
 }
 assert.deepEqual(errors,[]);console.log('No browser exceptions');
}finally{clearTimeout(timer);ws?.close();browser?.kill();server.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
