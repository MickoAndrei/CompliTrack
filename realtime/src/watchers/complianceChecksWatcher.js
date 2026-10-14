const logger = require('../utils/logger');
const { notifyForCheckChange } = require('../services/notificationService');

const STATE_ID = 'compliance_checks_watcher';

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function watchComplianceChecks({ database, io, signal }) {
  const stateCollection = database.collection('change_stream_state');
  const checks = database.collection('compliance_checks');

  while (!signal.aborted) {
    let stream;
    try {
      const state = await stateCollection.findOne({ _id: STATE_ID });
      stream = checks.watch([], {
        fullDocument: 'updateLookup',
        ...(state?.resumeToken ? { resumeAfter: state.resumeToken } : {}),
      });
      signal.addEventListener('abort', () => stream.close(), { once: true });

      for await (const change of stream) {
        await notifyForCheckChange({ database, io, change });
        await stateCollection.updateOne(
          { _id: STATE_ID },
          { $set: { resumeToken: change._id, updatedAt: new Date() } },
          { upsert: true },
        );
      }
    } catch (exception) {
      if (!signal.aborted) {
        logger.error('Compliance change-stream interrupted; reconnecting.', exception);
        await wait(2000);
      }
    } finally {
      if (stream) await stream.close();
    }
  }
}

module.exports = { watchComplianceChecks };