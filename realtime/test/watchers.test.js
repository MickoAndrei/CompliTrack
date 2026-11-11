const assert = require('node:assert/strict');
const test = require('node:test');
const { ObjectId } = require('mongodb');
const { pollNotifications, watchBroadcastNotifications } = require('../src/watchers/complianceChecksWatcher');
function fixture(type='audit_submitted', active=true) {
  const user = { _id: new ObjectId(), isActive: active };
  const row = { _id: new ObjectId(), recipientUserId: user._id, type, title:'Audit', message:'Saved', createdAt:new Date() };
  let emitted=[];
  const notifications = {
    async findOneAndUpdate(filter, update) {
      if (row.realtimeDeliveredAt || row.realtimeLeaseUntil > new Date()) return null;
      Object.assign(row, update.$set); return {...row};
    },
    async updateOne(filter, update) { if (filter.realtimeLease === row.realtimeLease) {
      Object.assign(row, update.$set); for (const key of Object.keys(update.$unset)) delete row[key];
    } },
  };
  const database={collection(name) {return name==='notifications' ? notifications : {async findOne(filter) { return active && filter._id.equals(user._id) ? user : null; }};}};
  const io={to(room){assert.equal(room,`user:${user._id}`);return {emit(event,payload){emitted.push({event,payload});}};}};
  return {database,io,row,user,emitted};
}
for (const type of ['admin_broadcast','audit_assigned','audit_submitted','audit_accepted','audit_returned','status_changed']) {
  test(`${type} is polled without change streams and delivered once`, async()=>{
    const f=fixture(type);await pollNotifications(f);await pollNotifications(f);
    assert.equal(f.emitted.length,1);assert.equal(f.emitted[0].payload.type,type);
    assert.equal(f.emitted[0].payload.realtimeLease,undefined);assert.ok(f.row.realtimeDeliveredAt);
  });
}
test('inactive recipients are not sent confidential payloads',async()=>{const f=fixture('audit_submitted',false);await pollNotifications(f);assert.equal(f.emitted.length,0);});
test('worker retries an expired lease after interruption',async()=>{const f=fixture();f.row.realtimeLeaseUntil=new Date(0);await pollNotifications(f);assert.equal(f.emitted.length,1);});
test('polling stops cleanly without requiring watch()',async()=>{const f=fixture();const controller=new AbortController();f.io.to=()=>({emit(){controller.abort();}});await watchBroadcastNotifications({...f,signal:controller.signal});assert.ok(controller.signal.aborted);});
module.exports={fixture};
