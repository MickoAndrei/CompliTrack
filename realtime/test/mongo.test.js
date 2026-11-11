const test=require('node:test');const assert=require('node:assert/strict');const {randomBytes}=require('node:crypto');
const {MongoClient,ObjectId}=require('mongodb');const jwt=require('jsonwebtoken');const {io}=require('socket.io-client');
const {loadConfig}=require('../src/config/env');const {start}=require('../src/server');
test('isolated MongoDB service starts without Firebase and delivers persisted polling events',{skip:process.env.RUN_MONGO_NOTIFICATION_TESTS!=='1',timeout:30000},async(t)=>{
  const config=loadConfig();
  // Read credentials without changing either environment file; all writes use a random test DB.
  const root=require('dotenv').parse(require('node:fs').readFileSync(require('node:path').resolve(__dirname,'../../.env')));
  config.mongodbUri=process.env.NOTIFICATION_TEST_MONGODB_URI || root.MONGODB_URI;
  const name='ct_notify_test_'+randomBytes(4).toString('hex');
  const client=new MongoClient(config.mongodbUri);await client.connect();const db=client.db(name);
  let service,socket;const old=process.env.JWT_SECRET;process.env.JWT_SECRET='isolated-notification-integration-secret';
  t.after(async()=>{socket?.close();await service?.stop();if(db.databaseName===name&&name.startsWith('ct_notify_test_'))await db.dropDatabase();await client.close();if(old===undefined)delete process.env.JWT_SECRET;else process.env.JWT_SECRET=old;});
  const id=new ObjectId();await db.collection('users').insertOne({_id:id,isActive:true,roleId:2});
  await db.collection('notifications').createIndex({dedupeKey:1},{unique:true,sparse:true});
  service=await start({...config,jwtSecret:process.env.JWT_SECRET,mongodbDatabase:name,port:0,host:'127.0.0.1',corsOrigins:[]});
  const url=`http://127.0.0.1:${service.server.address().port}`;
  const health=await (await fetch(url+'/health')).json();assert.equal(health.notificationTransport,'polling');
  socket=io(url,{auth:{token:jwt.sign({sub:id.toString()},process.env.JWT_SECRET,{expiresIn:60})},transports:['websocket'],reconnection:false});
  await new Promise((resolve,reject)=>{socket.once('connect',resolve);socket.once('connect_error',reject);});
  const message=new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('Notification delivery timeout')),10000);socket.once('notification',payload=>{clearTimeout(timer);resolve(payload);});});
  const row={recipientUserId:id,type:'audit_submitted',title:'Checklist and audit submitted',message:'Test only',isRead:false,dedupeKey:'test-event',createdAt:new Date()};
  await db.collection('notifications').updateOne({dedupeKey:'test-event'},{$setOnInsert:row},{upsert:true});
  await db.collection('notifications').updateOne({dedupeKey:'test-event'},{$setOnInsert:row},{upsert:true});
  const payload=await message;assert.equal(payload.type,'audit_submitted');assert.equal(await db.collection('notifications').countDocuments(),1);
});
