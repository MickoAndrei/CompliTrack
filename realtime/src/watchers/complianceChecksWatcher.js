const logger = require('../utils/logger');
const { notifyForCheckChange } = require('../services/notificationService');
const fcmService = require('../services/fcmService');

const STATE_ID = 'compliance_checks_watcher';
const BROADCAST_STATE_ID = 'admin_broadcast_notifications_watcher';

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function watchComplianceChecks({ database, io, signal }) {
  const stateCollection = database.collection('change_stream_state');
  const checks = database.collection('compliance_checks');

  while (!signal.aborted) {
    let stream;
    try {
      const state = await stateCollection.findOne({ _id: STATE_ID });
      stream = checks.watch([], {
        fullDocument: 'updateLookup',
        ...(state?.resumeToken ? { resumeAfter: state.resumeToken } : {}),
      });
      signal.addEventListener('abort', () => stream.close(), { once: true });

      for await (const change of stream) {
        await notifyForCheckChange({ database, io, change });
        await stateCollection.updateOne(
          { _id: STATE_ID },
          { $set: { resumeToken: change._id, updatedAt: new Date() } },
          { upsert: true },
        );
      }
    } catch (exception) {
      if (!signal.aborted) {
        logger.error('Compliance change-stream interrupted; reconnecting.', exception);
        await wait(2000);
      }
    } finally {
      if (stream) await stream.close();
    }
  }
}

async function watchBroadcastNotifications({ database, io, signal }) {
  const stateCollection = database.collection('change_stream_state');
  const notifications = database.collection('notifications');

  while (!signal.aborted) {
    let stream;
    try {
      const state = await stateCollection.findOne({ _id: BROADCAST_STATE_ID });
      stream = notifications.watch([
        {
          $match: {
            operationType: 'insert',
            'fullDocument.type': 'admin_broadcast',
          },
        },
      ], {
        fullDocument: 'default',
        ...(state?.resumeToken ? { resumeAfter: state.resumeToken } : {}),
      });
      signal.addEventListener('abort', () => stream.close(), { once: true });

      for await (const change of stream) {
        const notification = change.fullDocument;
        const recipientUserId = notification?.recipientUserId;
        if (recipientUserId) {
          const notificationId = notification._id.toString();
          const recipientId = recipientUserId.toString();
          io.to(`user:${recipientId}`).emit('notification', {
            ...notification,
            _id: notificationId,
            recipientUserId: recipientId,
            senderUserId: notification.senderUserId?.toString() ?? null,
          });

          const user = await database.collection('users').findOne(
            { _id: recipientUserId },
            { projection: { fcmToken: 1 } },
          );
          const delivered = await fcmService.sendToToken(
            user?.fcmToken,
            notification,
            {
              notificationId,
              type: notification.type,
            },
          );
          if (delivered) {
            await notifications.updateOne(
              { _id: notification._id },
              { $set: { fcmSent: true } },
            );
          }
        }

        await stateCollection.updateOne(
          { _id: BROADCAST_STATE_ID },
          { $set: { resumeToken: change._id, updatedAt: new Date() } },
          { upsert: true },
        );
      }
    } catch (exception) {
      if (!signal.aborted) {
        logger.error('Broadcast notification change stream interrupted; reconnecting.', exception);
        await wait(2000);
      }
    } finally {
      if (stream) await stream.close();
    }
  }
}

module.exports = { watchComplianceChecks, watchBroadcastNotifications };