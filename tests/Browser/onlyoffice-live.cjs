const {spawn}=require('node:child_process');
const fs=require('node:fs'); const path=require('node:path'); const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'storage/framework/testing/onlyoffice-live.json'),'utf8'));
const extension=process.argv[2]||'docx';assert.ok(['docx','xlsx','pptx'].includes(extension));
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
(async()=>{
 const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless','--disable-gpu','--remote-debugging-port=9351',`--user-data-dir=${path.join(root,'storage/framework/testing/onlyoffice-edge')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
 let ws; const timeout=setTimeout(()=>{console.error('Live ONLYOFFICE browser timed out');ws?.close();browser.kill();process.exitCode=1;},180000);
 try {
  let tabs;for(let i=0;i<80;i++){try{tabs=await(await fetch('http://127.0.0.1:9351/json')).json();break;}catch{await sleep(200);}}
  ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
  let sequence=0;const pending=new Map();const contexts=new Map();
  ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.method==='Runtime.executionContextCreated')contexts.set(m.params.context.id,m.params.context);if(m.method==='Runtime.executionContextDestroyed')contexts.delete(m.params.executionContextId);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}});
  const send=(method,params={})=>new Promise(resolve=>{const id=++sequence;pending.set(id,resolve);ws.send(JSON.stringify({id,method,params}));});
  const evaluate=async(expression,contextId)=>{const r=await send('Runtime.evaluate',{expression,contextId,awaitPromise:true,returnByValue:true});if(r.error||r.result?.exceptionDetails)throw Error(JSON.stringify(r.error||r.result.exceptionDetails));return r.result.result.value;};
  const wait=async(expression,contextId,seconds=60)=>{for(let i=0;i<seconds*4;i++){try{if(await evaluate(expression,contextId))return;}catch{}await sleep(250);}throw Error('Timed out: '+expression);};
  await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  const tab='ab'.repeat(16);
  await send('Page.addScriptToEvaluateOnNewDocument',{source:`if(location.port==='8017')sessionStorage.setItem('complitrack.tab','${tab}');`});
  await send('Network.setExtraHTTPHeaders',{headers:{'X-Fixture-Secret':fixture.secret}});
  await send('Page.navigate',{url:`http://127.0.0.1:8017/__onlyoffice_fixture_login?_tab=${tab}`});await sleep(1500);
  await send('Network.setExtraHTTPHeaders',{headers:{}});
  await send('Page.navigate',{url:`http://127.0.0.1:8017/auditee/documents?_tab=${tab}`});
  await wait('window.Alpine && document.querySelector(".qms-explorer") && typeof Alpine.$data(document.querySelector(".qms-explorer")).openInEditor === "function" && !Alpine.$data(document.querySelector(".qms-explorer")).initialLoading');
  const app='Alpine.$data(document.querySelector(".qms-explorer"))';
  await evaluate('window.axios.interceptors.response.use(r=>{if(r.config.url.includes("/editor"))window.fixturePrimaryKey=r.data.config?.document?.key;return r;})');
  const doc=await evaluate(`${app}.documents.filter(d=>d.originalName.endsWith('.${extension}')).sort((a,b)=>b.versionNumber-a.versionNumber)[0]._id`);
  const viewOnly=process.argv.includes('--view-only');
  await evaluate(`${app}.openInEditor({_id:'${doc}'},'${process.argv.includes('--view-only')?'view':'edit'}')`);
  if(await evaluate(`${app}.error`))console.log('Config diagnostics:',await evaluate(`window.axios.post('/api/v1/documents/${doc}/editor',{mode:'edit'}).then(()=>({status:200})).catch(e=>({status:e.response?.status,message:e.response?.data?.message,errors:e.response?.data?.errors,code:e.code}))`));
  assert.equal(await evaluate(`${app}.error`),'','Editor configuration request must succeed');
  await wait('document.querySelector("[x-ref=editor] iframe")');
  let editorContext;
  for(let i=0;i<160;i++){for(const context of contexts.values()){if(context.origin.includes(':8082')&&context.auxData?.isDefault){try{if(await evaluate('!!window.Asc?.editor',context.id)){editorContext=context.id;break;}}catch{}}}if(editorContext)break;await sleep(250);}
  assert.ok(editorContext,'Real ONLYOFFICE iframe must initialize');
  await sleep(8000);
  if(viewOnly){await wait(`${app}.editorMessage.startsWith('Read-only document ready')`);console.log('PASS existing saved DOCX opened read-only through the running Docker service');await send('Browser.close');return;}
  if(extension==='docx'){
   await evaluate(`(async()=>{const tab='cd'.repeat(16);const nativeFrame=document.createElement('iframe');nativeFrame.hidden=true;document.body.append(nativeFrame);const nativeFetch=nativeFrame.contentWindow.fetch.bind(nativeFrame.contentWindow);const login=await nativeFetch('/__onlyoffice_fixture_login?actor=secretary&_tab='+tab,{headers:{'X-Fixture-Secret':${JSON.stringify(fixture.secret)}}});if(!login.ok)throw Error('Collaborator login failed '+login.status);const {csrf}=await login.json();const r=await nativeFetch('/api/v1/documents/${doc}/editor?_tab='+tab,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({mode:'edit'})});if(!r.ok)throw Error('Collaborator config failed '+r.status);const data=await r.json();if(data.config.document.key!==window.fixturePrimaryKey)throw Error('Collaborators received different keys');const slot=document.createElement('div');slot.id='isolated-collaborator';slot.style='position:fixed;bottom:0;right:0;width:600px;height:400px;z-index:99999';document.body.append(slot);window.fixtureCollaborator=new DocsAPI.DocEditor(slot.id,{...data.config,events:{onDocumentReady:()=>window.fixtureCollaboratorReady=true,onError:e=>window.fixtureCollaboratorError=e.data.errorCode}});})()`);
   await wait('window.fixtureCollaboratorReady || window.fixtureCollaboratorError');
   assert.equal(await evaluate('window.fixtureCollaboratorError'),undefined);
   console.log('PASS simultaneous chair/secretary editors initialized against the same collaborative document key');
   await evaluate('window.fixtureCollaborator.destroyEditor();document.querySelector("#isolated-collaborator")?.remove()');
  }
  console.log('Editor DOM:',await evaluate('JSON.stringify({title:document.title,inputs:[...document.querySelectorAll("textarea,input")].map(x=>({id:x.id,type:x.type})).slice(0,12),body:document.body.innerText.slice(-500)})',editorContext));
  const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(root,'storage/framework/testing/onlyoffice-live.png'),Buffer.from(shot.result.data,'base64'));
  await evaluate('document.querySelector("#area_id")?.focus()',editorContext);
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'End',code:'End',modifiers:2});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'End',code:'End',modifiers:2});
  const marker='CompliTrack isolated '+extension+' save '+Date.now();
  if(extension==='xlsx')await evaluate('if(document.querySelector("#ce-cell-content"))document.querySelector("#ce-cell-content").focus();',editorContext);
  if(extension==='pptx') {
   await evaluate('document.querySelector("#id-toolbar-btn-add-slide button").click()',editorContext);
  } else {await send('Input.insertText',{text:marker});}
  if(extension==='xlsx'){await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter'});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter'});}
  await sleep(1000);await send('Input.dispatchKeyEvent',{type:'keyDown',key:'s',code:'KeyS',modifiers:2});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'s',code:'KeyS',modifiers:2});
  await evaluate('document.querySelector("#id-toolbar-btn-save").click()',editorContext);
  try {await wait(`${app}.editorSavedCount>0`,undefined,25);}
  catch {console.log('Awaiting final-save callback after closing the editing session');await evaluate('document.querySelector("[data-editor-back]").click()');await wait(`${app}.editorSavedCount>0`,undefined,45);}
  console.log('PASS real '+extension+' edit and callback-confirmed version:',await evaluate(`${app}.editorMessage`));
  fs.writeFileSync(path.join(root,'storage/framework/testing/onlyoffice-live-result-'+extension+'.json'),JSON.stringify({marker,currentId:await evaluate(`${app}.detail._id`),sourceId:doc}));
  await evaluate('if(document.querySelector("[x-ref=editor]").open)document.querySelector("[data-editor-back]").click()');
  await sleep(12000);
  if(extension==='docx'){
   await evaluate(`${app}.openInEditor({_id:${app}.detail._id},'view')`);
   await wait('document.querySelector("[x-ref=editor] iframe")');
   await wait(`${app}.editorMessage.startsWith('Read-only document ready')`);
   assert.equal(await evaluate(`${app}.editorMode`),'view');
   console.log('PASS real DOCX saved version reopened in read-only mode');
   await evaluate('document.querySelector("[data-editor-back]").click()');
  }
  await send('Browser.close');
 } finally {clearTimeout(timeout);ws?.close();browser.kill();}
})().catch(error=>{console.error(error);process.exitCode=1;});
