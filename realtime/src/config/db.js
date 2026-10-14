const { MongoClient } = require('mongodb');

async function connectToDatabase(config) {
  const client = new MongoClient(config.mongodbUri, {
    serverSelectionTimeoutMS: 10000,
  });
  await client.connect();
  const database = client.db(config.mongodbDatabase);
  const hello = await database.admin().command({ hello: 1 });

  if (hello.setName !== 'rs0') {
    await client.close();
    throw new Error('MongoDB must be configured as replica set rs0 for change streams.');
  }

  return { client, database };
}

module.exports = { connectToDatabase };