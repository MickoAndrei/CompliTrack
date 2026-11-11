const fs=require('node:fs');const http=require('node:http');const{once}=require('node:events');
const httpProxy=require('../../realtime/node_modules/http-proxy');
const session=JSON.parse(fs.readFileSync('storage/framework/testing/performance-session.json','utf8'));
const token=fs.readFileSync('storage/framework/testing/phase2-token.txt','utf8');
const headers={Cookie:`${session.name}=${encodeURIComponent(session.value)}`,Origin:'http://127.0.0.1:8000',Accept:'application/json','X-Performance-Token':token};
const paths=['/api/v1/notifications?unread=1&perPage=1','/api/v1/users?management=1&perPage=10','/api/v1/users/options','/api/v1/users?management=1&perPage=10&includeOptions=0'];
(async()=>{
 let next=0;const proxy=httpProxy.createProxyServer();
 proxy.on('error',(_e,_req,res)=>{res.writeHead(502);res.end('unavailable');});
 const server=http.createServer((req,res)=>proxy.web(req,res,{target:`http://127.0.0.1:${[8013,8014][next++%2]}`}));
 const rows=[];
 try{
  server.listen(8015,'127.0.0.1');await once(server,'listening');
  for(const port of [8013,8014]){
   const r=await fetch(`http://127.0.0.1:${port}/api/v1/users/options`,{headers});await r.arrayBuffer();if(r.status!==200)throw Error('Warmup authorization failed');
  }
  for(let i=0;i<3;i++)for(const port of [8013,8015]){
   const start=performance.now();
   const requests=await Promise.all(paths.map(async path=>{
    const at=performance.now();const r=await fetch(`http://127.0.0.1:${port}${path}`,{headers,signal:AbortSignal.timeout(30000)});await r.arrayBuffer();
    if(r.status!==200)throw Error(`Unexpected ${r.status}`);
    return {path,ms:Math.round(performance.now()-at),serverTiming:r.headers.get('server-timing')};
   }));
   const row={port,workers:port===8013?1:2,trial:i+1,ms:Math.round(performance.now()-start),requests};rows.push(row);console.log(JSON.stringify(row));
  }
  fs.writeFileSync('storage/framework/testing/phase2-concurrency.json',JSON.stringify(rows,null,2));
 }finally{server.close();proxy.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
