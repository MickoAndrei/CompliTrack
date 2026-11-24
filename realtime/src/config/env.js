const fs = require('node:fs');
const path = require('node:path');
const dotenv = require('dotenv');
function read(file) { return fs.existsSync(file) ? dotenv.parse(fs.readFileSync(file)) : {}; }
function loadConfig() {
  const root = read(path.resolve(__dirname, '../../../.env'));
  const local = read(path.resolve(__dirname, '../../.env'));
  const activeEnvironment = process.env.MONGODB_ACTIVE_ENVIRONMENT || root.MONGODB_ACTIVE_ENVIRONMENT;
  if (activeEnvironment && !['local', 'remote'].includes(activeEnvironment)) {
    throw new Error('MONGODB_ACTIVE_ENVIRONMENT must be local or remote when set.');
  }
  const targetPrefix = activeEnvironment ? `MONGODB_${activeEnvironment.toUpperCase()}_` : null;
  const mongodbUri = targetPrefix
    ? process.env[`${targetPrefix}URI`] || root[`${targetPrefix}URI`]
    : process.env.MONGODB_URI || root.MONGODB_URI || local.MONGODB_URI;
  const mongodbDatabase = targetPrefix
    ? process.env[`${targetPrefix}DATABASE`] || root[`${targetPrefix}DATABASE`]
    : process.env.MONGODB_DATABASE || root.MONGODB_DATABASE || local.MONGODB_DATABASE;
  if (activeEnvironment && (!mongodbUri || !mongodbDatabase)) {
    throw new Error('The active MongoDB target is not fully configured.');
  }
  const jwtSecret = process.env.JWT_SECRET || root.JWT_SECRET || local.JWT_SECRET;
  if (!mongodbUri || !mongodbDatabase || !jwtSecret) throw new Error('MongoDB configuration and the Laravel JWT secret are required.');
  const port = Number.parseInt(process.env.PORT || local.PORT || '4001', 10);
  if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error('PORT must be a valid TCP port.');
  return { mongodbUri, mongodbDatabase, jwtSecret, port,
    corsOrigins: (process.env.SOCKET_CORS_ORIGIN || local.SOCKET_CORS_ORIGIN || '').split(',').map(s=>s.trim()).filter(Boolean) };
}
module.exports = { loadConfig };
