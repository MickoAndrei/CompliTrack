const { Server } = require('socket.io');
const { createSocketAuth } = require('./auth');
const packageInfo = require('../../package.json');

function createSocketServer(httpServer, database, corsOrigins, jwtSecret = process.env.JWT_SECRET) {
  const io = new Server(httpServer, {
    cors: {
      origin: corsOrigins.length ? corsOrigins : false,
      methods: ['GET', 'POST'],
    },
  });

  io.use(createSocketAuth(database, jwtSecret));
  io.on('connection', (socket) => {
    socket.join(`user:${socket.user._id.toString()}`);
    socket.emit('server_version', packageInfo.version);
    // Keep the existing JWT session boundary for long-lived connections.
    if (socket.user.tokenExpiresAt) {
      const timer = setTimeout(() => socket.disconnect(true), Math.max(0, socket.user.tokenExpiresAt - Date.now()));
      timer.unref(); socket.on('disconnect', () => clearTimeout(timer));
    }
  });

  return io;
}

module.exports = { createSocketServer };
