const admin = require('firebase-admin');
const logger = require('../utils/logger');

async function sendToToken(token, notification, data) {
  if (!token || admin.apps.length === 0) return false;

  try {
    await admin.messaging().send({
      token,
      notification: {
        title: notification.title,
        body: notification.message,
      },
      data,
    });
    return true;
  } catch (exception) {
    logger.error('Unable to send Firebase notification.', exception);
    return false;
  }
}

module.exports = { sendToToken };