const fs = require('node:fs');
const path = require('node:path');
const admin = require('firebase-admin');
const logger = require('../utils/logger');

function initializeFirebase(credentialsPath) {
  if (!credentialsPath) {
    logger.info('Firebase push delivery is disabled; FIREBASE_CREDENTIALS is not set.');
    return null;
  }

  const resolved = path.resolve(credentialsPath);
  if (!fs.existsSync(resolved)) {
    throw new Error('The configured Firebase credentials file does not exist.');
  }

  const serviceAccount = require(resolved);
  return admin.initializeApp({
    credential: admin.credential.cert(serviceAccount),
  });
}

module.exports = { initializeFirebase };