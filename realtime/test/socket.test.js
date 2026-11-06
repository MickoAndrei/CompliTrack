const test=require('node:test');const assert=require('node:assert/strict');
const http=require('node:http');const jwt=require('jsonwebtoken');const {ObjectId}=require('mongodb');
const {io:client}=require('socket.io-client');const {createSocketServer}=require('../src/sockets');
const {pollNotifications}=require('../src/watchers/complianceChecksWatcher');
test('real Socket.IO transport authenticates JWT, isolates recipient rooms, and rejects invalid tokens',async(t)=>{
  const saved=process.env.JWT_SECRET;process.env.JWT_SECRET='isolated-test-secret-no-external-credentials';
  const user={_id:new ObjectId(),isActive:true};
  const row={_id:new ObjectId(),recipientUserId:user._id,type:'audit_submitted',title:'Submitted',message:'Review',createdAt:new Date()};let delivered=false;
  const database={collection(name){return name==='users'?{async findOne(q){return q._id.equals(user._id)?user:null;}}:{
    async findOneAndUpdate(q,u){if(delivered)return null;Object.assign(row,u.$set);return row;},
    async updateOne(){delivered=true;}
  };}};
  const server=http.createServer();const sockets=createSocketServer(server,database,[]);
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const url=`http://127.0.0.1:${server.address().port}`;
  const token=jwt.sign({sub:user._id.toString()},process.env.JWT_SECRET,{expiresIn:60});
  const good=client(url,{auth:{token},transports:['websocket'],forceNew:true,reconnection:false});
  const bad=client(url,{auth:{token:'invalid'},transports:['websocket'],forceNew:true,reconnection:false});
  t.after(async()=>{good.close();bad.close();await new Promise(resolve=>sockets.close(resolve));server.close();if(saved===undefined)delete process.env.JWT_SECRET;else process.env.JWT_SECRET=saved;});
  await Promise.all([new Promise((resolve,reject)=>{good.once('connect',resolve);good.once('connect_error',reject);}),new Promise(resolve=>bad.once('connect_error',e=>{assert.equal(e.message,'Unauthorized.');resolve();}))]);
  const message=new Promise(resolve=>good.once('notification',resolve));
  await pollNotifications({database,io:sockets});const payload=await message;
  assert.equal(payload._id,row._id.toString());assert.equal(payload.type,'audit_submitted');
  const socket=[...sockets.sockets.sockets.values()][0];assert.ok(socket.rooms.has(`user:${user._id}`));
  assert.equal(sockets.sockets.sockets.size,1);
});
