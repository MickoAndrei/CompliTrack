const crypto = require('node:crypto');
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
      const [id, plainText] = token.includes('|') ? token.split('|', 2) : ['', token];
      const tokenDocument = id && ObjectId.isValid(id)
        ? await database.collection('personal_access_tokens').findOne({ _id: new ObjectId(id) })
        : await database.collection('personal_access_tokens').findOne({
          token: crypto.createHash('sha256').update(plainText).digest('hex'),
        });

      const digest = crypto.createHash('sha256').update(plainText).digest('hex');
      if (!tokenDocument || !crypto.timingSafeEqual(
        Buffer.from(tokenDocument.token, 'hex'),
        Buffer.from(digest, 'hex'),
      )) {
        throw new Error('The access token is invalid.');
      }
      if (tokenDocument.expires_at && new Date(tokenDocument.expires_at) <= new Date()) {
        throw new Error('The access token has expired.');
      }

      const user = await database.collection('users').findOne({
        _id: tokenDocument.tokenable_id,
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