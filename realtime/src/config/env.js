const path = require('node:path');
require('dotenv').config({ path: path.resolve(__dirname, '../../.env') });

function loadConfig() {
  const mongodbUri = process.env.MONGODB_URI;
  const mongodbDatabase = process.env.MONGODB_DATABASE;
  if (!mongodbUri || !mongodbDatabase) {
    throw new Error('MONGODB_URI and MONGODB_DATABASE must be configured.');
  }

  const port = Number.parseInt(process.env.PORT || '4000', 10);
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error('PORT must be a valid TCP port.');
  }

  return {
    mongodbUri,
    mongodbDatabase,
    port,
    corsOrigins: (process.env.SOCKET_CORS_ORIGIN || '')
      .split(',')
      .map((origin) => origin.trim())
      .filter(Boolean),
    firebaseCredentials: process.env.FIREBASE_CREDENTIALS || '',
  };
}

module.exports = { loadConfig };