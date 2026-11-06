const { MongoClient } = require('mongodb');

async function connectToDatabase(config) {
  const client = new MongoClient(config.mongodbUri, {
    serverSelectionTimeoutMS: 10000,
  });
  await client.connect();
  const database = client.db(config.mongodbDatabase);
  return { client, database };
}

module.exports = { connectToDatabase };