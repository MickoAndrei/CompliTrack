const fcmService = require('./fcmService');
const { makeNotification } = require('../models/notification');

async function notifyForCheckChange({ database, io, change }) {
  const check = change.fullDocument;
  if (!check || !check.auditeeId) return;

  const updated = change.updateDescription?.updatedFields || {};
  let type;
  let title;
  let message;

  if (change.operationType === 'insert') {
    if (check.result === 'compliant') {
      type = 'compliant_confirmed';
      title = 'Compliance confirmed';
      message = `${check.checkCode} has been recorded as compliant.`;
    } else if (check.result === 'non_compliant') {
      type = 'corrective_action_created';
      title = 'Corrective action created';
      message = `A corrective action was created for ${check.checkCode}.`;
    }
  } else if (Object.hasOwn(updated, 'result') && check.result === 'compliant') {
    type = 'compliant_confirmed';
    title = 'Compliance confirmed';
    message = `${check.checkCode} has been recorded as compliant.`;
  } else if (Object.hasOwn(updated, 'correctiveStatus')) {
    type = 'status_changed';
    title = 'Corrective action updated';
    message = `${check.checkCode} status changed to ${check.correctiveStatus}.`;
  }

  if (!type) return;

  const dedupeKey = ['compliant_confirmed', 'corrective_action_created'].includes(type)
    ? `check:${check._id.toString()}:${type}`
    : `check:${check._id.toString()}:${type}:${change.clusterTime?.toString() || change._id._data}`;
  const notification = makeNotification({
    recipientUserId: check.auditeeId,
    type,
    title,
    message,
    relatedCheckId: check._id,
    dedupeKey,
  });

  let inserted;
  try {
    inserted = await database.collection('notifications').insertOne(notification);
  } catch (exception) {
    if (exception.code === 11000) return;
    throw exception;
  }

  io.to(`user:${check.auditeeId.toString()}`).emit('notification', {
    ...notification,
    _id: inserted.insertedId.toString(),
    recipientUserId: check.auditeeId.toString(),
    relatedCheckId: check._id.toString(),
  });

  const user = await database.collection('users').findOne({ _id: check.auditeeId }, {
    projection: { fcmToken: 1 },
  });
  const delivered = await fcmService.sendToToken(user?.fcmToken, notification, {
    notificationId: inserted.insertedId.toString(),
    checkId: check._id.toString(),
  });
  if (delivered) {
    await database.collection('notifications').updateOne(
      { _id: inserted.insertedId },
      { $set: { fcmSent: true } },
    );
  }
}

module.exports = { notifyForCheckChange };