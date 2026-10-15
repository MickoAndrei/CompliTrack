const assert = require('node:assert/strict');
const test = require('node:test');
const { watchBroadcastNotifications } = require('../src/watchers/complianceChecksWatcher');

test('broadcast notification inserts are emitted to the recipient room', async () => {
  const controller = new AbortController();
  const resumeToken = { _data: 'resume-token' };
  const recipientUserId = { toString: () => 'recipient-id' };
  const notificationId = { toString: () => 'notification-id' };
  const senderUserId = { toString: () => 'sender-id' };
  const notification = {
    _id: notificationId,
    recipientUserId,
    senderUserId,
    type: 'admin_broadcast',
    title: 'Broadcast Notification',
    message: 'Service update',
  };
  let watchedPipeline;
  let emitted;
  let savedResumeToken;

  const stream = {
    async close() {},
    async *[Symbol.asyncIterator]() {
      yield { _id: resumeToken, fullDocument: notification };
      controller.abort();
    },
  };
  const stateCollection = {
    async findOne() {
      return null;
    },
    async updateOne(filter, update) {
      savedResumeToken = { filter, update };
    },
  };
  const notifications = {
    watch(pipeline) {
      watchedPipeline = pipeline;
      return stream;
    },
    async updateOne() {
      throw new Error('No FCM delivery should be marked without a recipient token.');
    },
  };
  const users = {
    async findOne() {
      return null;
    },
  };
  const database = {
    collection(name) {
      if (name === 'change_stream_state') return stateCollection;
      if (name === 'notifications') return notifications;
      if (name === 'users') return users;
      throw new Error(`Unexpected collection: ${name}`);
    },
  };
  const io = {
    to(room) {
      assert.equal(room, 'user:recipient-id');
      return {
        emit(event, payload) {
          emitted = { event, payload };
        },
      };
    },
  };

  await watchBroadcastNotifications({
    database,
    io,
    signal: controller.signal,
  });

  assert.deepEqual(watchedPipeline, [{
    $match: {
      operationType: 'insert',
      'fullDocument.type': 'admin_broadcast',
    },
  }]);
  assert.equal(emitted.event, 'notification');
  assert.equal(emitted.payload._id, 'notification-id');
  assert.equal(emitted.payload.recipientUserId, 'recipient-id');
  assert.equal(emitted.payload.senderUserId, 'sender-id');
  assert.equal(savedResumeToken.filter._id, 'admin_broadcast_notifications_watcher');
  assert.equal(savedResumeToken.update.$set.resumeToken, resumeToken);
});
