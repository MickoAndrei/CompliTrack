const { ObjectId } = require('mongodb');
const logger = require('../utils/logger');

async function pollNotifications({ database, io, now = new Date() }) {
  const notifications = database.collection('notifications');
  // Lease prevents competing workers from dispatching the same persisted record.
  for (let i = 0; i < 100; i++) {
    const lease = new ObjectId().toString();
    const notification = await notifications.findOneAndUpdate({ realtimeDeliveredAt: { $exists: false },
      $or: [{ realtimeLeaseUntil: { $exists: false } }, { realtimeLeaseUntil: { $lt: now } }] },
    { $set: { realtimeLease: lease, realtimeLeaseUntil: new Date(now.getTime() + 30000) } },
    { sort: { createdAt: 1, _id: 1 }, returnDocument: 'after' });
    if (!notification) break;
    const recipient = notification.recipientUserId?.toString();
    const user = recipient && ObjectId.isValid(recipient) ? await database.collection('users').findOne({ _id: new ObjectId(recipient), isActive: true }) : null;
    if (user) {
      const { realtimeLease, realtimeLeaseUntil, ...payload } = notification;
      io.to(`user:${recipient}`).emit('notification', { ...payload, _id: notification._id.toString(),
        recipientUserId: recipient, senderUserId: notification.senderUserId?.toString() ?? null,
        relatedCheckId: notification.relatedCheckId?.toString() ?? null });
    }
    await notifications.updateOne({ _id: notification._id, realtimeLease: lease },
      { $set: { realtimeDeliveredAt: new Date() }, $unset: { realtimeLease: '', realtimeLeaseUntil: '' } });
  }
}

async function runPolling(action, context) {
  while (!context.signal.aborted) {
    try { await action(context); } catch (error) { logger.error('Notification polling failed; retrying.', error); }
    if (!context.signal.aborted) await new Promise(resolve => {
      const finish = () => { clearTimeout(timer); context.signal.removeEventListener('abort', finish); resolve(); };
      const timer = setTimeout(finish, 2000); context.signal.addEventListener('abort', finish, { once: true });
    });
  }
}
module.exports = { pollNotifications,
  watchBroadcastNotifications: context => runPolling(pollNotifications, context) };
