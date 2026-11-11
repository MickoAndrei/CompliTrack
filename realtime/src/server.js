const express = require('express');
const http = require('node:http');
const { loadConfig } = require('./config/env');
const packageInfo = require('../package.json');
const { connectToDatabase } = require('./config/db');
const { createSocketServer } = require('./sockets');
const {
  watchBroadcastNotifications,
} = require('./watchers/complianceChecksWatcher');
const logger = require('./utils/logger');

async function start(config = loadConfig()) {
  const { client, database } = await connectToDatabase(config);
  const app = express();
  app.get('/health', async (_request, response) => {
    try {
      await database.command({ ping: 1 });
      response.json({
        status: 'ok',
        database: 'ok',
        notificationTransport: 'polling',
      });
    } catch (exception) {
      logger.error('Realtime health check failed.', exception);
      response.status(503).json({
        status: 'unavailable',
        database: 'unavailable',
      });
    }
  });

  const server = http.createServer(app);
  const io = createSocketServer(server, database, config.corsOrigins, config.jwtSecret);
  const abortController = new AbortController();
  const watchers = Promise.all([
    watchBroadcastNotifications({
      database,
      io,
      signal: abortController.signal,
    }),
  ]);

  await new Promise(resolve => server.listen(config.port, config.host || '0.0.0.0', () => {
    logger.info('Realtime service is listening.', {
      port: config.port,
      version: packageInfo.version,
    });
    resolve();
  }));

  const stop = async () => {
    abortController.abort();
    await new Promise(resolve => io.close(resolve));
    await watchers;
    await client.close();
    process.removeListener('SIGINT', stop);
    process.removeListener('SIGTERM', stop);
  };
  process.once('SIGINT', stop);
  process.once('SIGTERM', stop);
  return { server, io, stop };
}

if (require.main === module) start().catch((exception) => {
  logger.error('Realtime service failed to start.', exception);
  process.exitCode = 1;
});
module.exports = { start };
