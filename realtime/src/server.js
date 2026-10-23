const express = require('express');
const http = require('node:http');
const { loadConfig } = require('./config/env');
const { connectToDatabase } = require('./config/db');
const { initializeFirebase } = require('./config/firebase');
const { createSocketServer } = require('./sockets');
const {
  watchComplianceChecks,
  watchBroadcastNotifications,
} = require('./watchers/complianceChecksWatcher');
const logger = require('./utils/logger');

async function start() {
  const config = loadConfig();
  const firebaseApp = initializeFirebase(config.firebaseCredentials);
  const { client, database } = await connectToDatabase(config);
  const app = express();
  app.get('/health', async (_request, response) => {
    try {
      await database.command({ ping: 1 });
      response.json({
        status: 'ok',
        database: 'ok',
        pushConfigured: firebaseApp !== null,
      });
    } catch (exception) {
      logger.error('Realtime health check failed.', exception);
      response.status(503).json({
        status: 'unavailable',
        database: 'unavailable',
        pushConfigured: firebaseApp !== null,
      });
    }
  });

  const server = http.createServer(app);
  const io = createSocketServer(server, database, config.corsOrigins);
  const abortController = new AbortController();
  const watchers = Promise.all([
    watchComplianceChecks({
      database,
      io,
      signal: abortController.signal,
    }),
    watchBroadcastNotifications({
      database,
      io,
      signal: abortController.signal,
    }),
  ]);

  server.listen(config.port, () => {
    logger.info('Realtime service is listening.', { port: config.port });
  });

  const stop = async () => {
    abortController.abort();
    io.close();
    server.close();
    await watchers;
    await client.close();
    process.exit(0);
  };
  process.once('SIGINT', stop);
  process.once('SIGTERM', stop);
}

start().catch((exception) => {
  logger.error('Realtime service failed to start.', exception);
  process.exitCode = 1;
});
