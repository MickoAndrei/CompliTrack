const jwt = require('jsonwebtoken');
const { ObjectId } = require('mongodb');

function extractToken(socket) {
  const token = socket.handshake.auth?.token
    || socket.handshake.headers.authorization?.replace(/^Bearer\s+/i, '');
  if (typeof token !== 'string' || token.length < 40) {
    throw new Error('A valid bearer token is required.');
  }
  return token;
}

function createSocketAuth(database) {
  return async (socket, next) => {
    try {
      const token = extractToken(socket);

      // Verifies the signature and expiry (exp) in one call.
      const payload = jwt.verify(token, process.env.JWT_SECRET, { algorithms: ['HS256'] });
      if (!ObjectId.isValid(payload.sub)) {
        throw new Error('The token subject is invalid.');
      }

      const user = await database.collection('users').findOne({
        _id: new ObjectId(payload.sub),
        isActive: true,
      });
      if (!user) throw new Error('The account is inactive or no longer exists.');

      socket.user = user;
      next();
    } catch (exception) {
      next(new Error('Unauthorized.'));
    }
  };
}

module.exports = { createSocketAuth };