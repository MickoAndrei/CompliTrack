const fs=require('node:fs');
const label=process.argv[2]||'phase2-http';
const ports=(process.argv[3]||'8012,8013').split(',').map(Number);
const session=JSON.parse(fs.readFileSync('storage/framework/testing/performance-session.json','utf8'));
const token=fs.readFileSync('storage/framework/testing/phase2-token.txt','utf8');
const cookie=`${session.name}=${encodeURIComponent(session.value)}`;
const rows=[];
async function request(port,path,group){
 const start=performance.now();
 const r=await fetch(`http://127.0.0.1:${port}${path}`,{headers:{...(path==='/login'?{}:{Cookie:cookie}),Accept:'application/json',Origin:'http://127.0.0.1:8000','X-Performance-Token':token},redirect:'manual',signal:AbortSignal.timeout(30000)});
 const ttfbMs=Math.round(performance.now()-start);const body=await r.arrayBuffer();
 const row={port,path,group,status:r.status,ttfbMs,ms:Math.round(performance.now()-start),bytes:body.byteLength,serverTiming:r.headers.get('server-timing'),counts:r.headers.get('x-performance-counts'),opcache:r.headers.get('x-development-opcache')};
 rows.push(row);console.log(JSON.stringify(row));
 if(r.status!==200)throw Error('Non-successful benchmark response');
}
(async()=>{
 for(const port of ports)await request(port,'/_dev/runtime','runtime');
 for(let i=0;i<3;i++)for(const port of ports){
  await request(port,'/login',i?'warm':'cold');
  await request(port,'/api/v1/users?management=1&perPage=20',i?'warm':'cold');
 }
 for(const port of ports){
  await request(port,'/api/v1/notifications?unread=1&perPage=1','sequential');
  await request(port,'/api/v1/users?management=1&perPage=10','sequential');
  const start=performance.now();
  await Promise.all([request(port,'/api/v1/notifications?unread=1&perPage=1','concurrent'),request(port,'/api/v1/users?management=1&perPage=10','concurrent')]);
  rows.push({port,group:'concurrent-total',ms:Math.round(performance.now()-start)});
 }
 fs.writeFileSync(`storage/framework/testing/${label}.json`,JSON.stringify(rows,null,2));
})().catch(e=>{console.error(e);fs.writeFileSync(`storage/framework/testing/${label}.json`,JSON.stringify(rows,null,2));process.exitCode=1;});
