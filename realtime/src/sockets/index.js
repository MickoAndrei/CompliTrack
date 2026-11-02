const { Server } = require('socket.io');
const { createSocketAuth } = require('./auth');
const packageInfo = require('../package.json');

function createSocketServer(httpServer, database, corsOrigins) {
  const io = new Server(httpServer, {
    cors: {
      origin: corsOrigins.length ? corsOrigins : false,
      methods: ['GET', 'POST'],
    },
  });

  io.use(createSocketAuth(database));
  io.on('connection', (socket) => {
    socket.join(`user:${socket.user._id.toString()}`);
    socket.emit('server_version', packageInfo.version);
  });

  return io;
}

module.exports = { createSocketServer };