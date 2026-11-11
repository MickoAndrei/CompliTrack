const fs=require('node:fs');const{spawn}=require('node:child_process');
const label=process.argv[2]||'phase2-browser';const base=process.argv[3]||'http://127.0.0.1:8000';
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=9356',`--user-data-dir=C:/dev/CompliTrack/storage/framework/testing/${label}-edge`,'about:blank'],{windowsHide:true,stdio:'ignore'});
 let ws;const rows=[];
 try{
  let tabs;for(let i=0;i<50;i++){try{tabs=await(await fetch('http://127.0.0.1:9356/json')).json();break;}catch{await sleep(200);}}
  if(!tabs)throw Error('Browser unavailable');
  ws=new WebSocket(tabs.find(t=>t.type==='page'&&t.url==='about:blank').webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
  let seq=0;const pending=new Map();let requests=new Map(),trace=[],errors=[];let tracingDone;
  ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}const p=m.params;
   if(m.method==='Runtime.exceptionThrown')errors.push(p.exceptionDetails.exception?.description||p.exceptionDetails.text);
   if(m.method==='Tracing.dataCollected')trace.push(...p.value);
   if(m.method==='Tracing.tracingComplete')tracingDone?.();
   if(m.method==='Network.requestWillBeSent')requests.set(p.requestId,{path:new URL(p.request.url).host+new URL(p.request.url).pathname,start:p.timestamp,type:p.type});
   if(m.method==='Network.responseReceived'){const r=requests.get(p.requestId);if(r)Object.assign(r,{status:p.response.status,ttfbMs:Math.round((p.timestamp-r.start)*1000),timing:p.response.timing,serverTiming:p.response.headers['Server-Timing']||p.response.headers['server-timing']});}
   if(m.method==='Network.loadingFinished'){const r=requests.get(p.requestId);if(r)Object.assign(r,{ms:Math.round((p.timestamp-r.start)*1000),wireBytes:p.encodedDataLength});}
   if(m.method==='Network.loadingFailed'){const r=requests.get(p.requestId);if(r)r.error=p.errorText;}
  });
  const send=(method,params={})=>new Promise((r,reject)=>{const id=++seq;const timer=setTimeout(()=>reject(Error('CDP timeout '+method)),30000);pending.set(id,m=>{clearTimeout(timer);r(m);});ws.send(JSON.stringify({id,method,params}));});
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.result?.exceptionDetails)throw Error(r.result.exceptionDetails.text);return r.result?.result?.value;};
  await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Performance.enable');
  const diagnosticHeaders=process.argv.includes('--no-profile')?{}:{'X-Performance-Token':fs.readFileSync('storage/framework/testing/phase2-token.txt','utf8')};
  await send('Network.setExtraHTTPHeaders',{headers:{...diagnosticHeaders,'ngrok-skip-browser-warning':'1'}});
  const session=JSON.parse(fs.readFileSync('storage/framework/testing/performance-session.json','utf8'));
  await send('Network.setCookie',{name:session.name,value:session.value,url:base,path:'/',httpOnly:true});
  for(const cold of [true,false]){
   requests=new Map();trace=[];errors=[];await send('Network.setCacheDisabled',{cacheDisabled:cold});
   await send('Tracing.start',{categories:'devtools.timeline,v8,blink.user_timing',transferMode:'ReportEvents'});
   const start=performance.now();await send('Page.navigate',{url:base+'/super-admin/users'});
   let ready=false;for(let i=0;i<450;i++){ready=await evaluate(`(()=>{const e=document.querySelector('[x-data*="resourcePage"]');const s=e&&window.Alpine?.$data(e);return !!s&&!s.loading&&!!s.summary;})()`);if(ready)break;await sleep(50);}
   const readyMs=Math.round(performance.now()-start);await sleep(1500);
   const details=await evaluate(`(()=>{const n=performance.getEntriesByType('navigation')[0];return {path:location.pathname,documentTtfbMs:Math.round(n.responseStart),domContentLoadedMs:Math.round(n.domContentLoadedEventEnd),loadMs:Math.round(n.loadEventEnd),paint:performance.getEntriesByType('paint').map(p=>({name:p.name,ms:Math.round(p.startTime)})),marks:performance.getEntriesByType('mark').filter(p=>p.name.startsWith('complitrack:')).map(p=>({name:p.name,ms:Math.round(p.startTime)})),resources:performance.getEntriesByType('resource').map(p=>({path:new URL(p.name).host+new URL(p.name).pathname,initiator:p.initiatorType,ms:Math.round(p.duration),startMs:Math.round(p.startTime),endMs:Math.round(p.responseEnd),bytes:p.encodedBodySize})),fonts:[...document.fonts].map(f=>({family:f.family,status:f.status})),overflow:document.documentElement.scrollWidth>innerWidth};})()`);
   const metrics=await send('Performance.getMetrics');const cpu=Object.fromEntries(metrics.result.metrics.filter(m=>['ScriptDuration','TaskDuration','LayoutDuration','RecalcStyleDuration'].includes(m.name)).map(m=>[m.name,Math.round(m.value*1000)]));
   const finished=new Promise(r=>tracingDone=r);await send('Tracing.end');await finished;
   const traceSummary={};for(const e of trace){if(['EvaluateScript','FunctionCall','Layout','Paint','UpdateLayoutTree','v8.compile'].includes(e.name)&&e.dur){traceSummary[e.name]=(traceSummary[e.name]||0)+e.dur/1000;}}
   const first=Math.min(...[...requests.values()].map(r=>r.start));
   const row={base,cold,ready,readyMs,...details,cpu,traceSummary,requests:[...requests.values()].map(({start,...r})=>({...r,startMs:Math.round((start-first)*1000)})),errors};
   rows.push(row);fs.writeFileSync(`storage/framework/testing/${label}.json`,JSON.stringify(rows,null,2));fs.writeFileSync(`storage/framework/testing/${label}-${cold?'cold':'warm'}.trace.json`,JSON.stringify({traceEvents:trace}));
   console.log(JSON.stringify({base,cold,ready,readyMs,documentTtfbMs:details.documentTtfbMs,paint:details.paint,cpu,marks:details.marks,requests:row.requests.filter(r=>['XHR','Fetch'].includes(r.type)||r.error),errors}));
   if(!ready||errors.length)throw Error('Browser check did not complete successfully');
  }
  await send('Browser.close');
 }finally{ws?.close();browser.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
