// Explicit one-time replacement: node scripts/copy-remote-to-local.cjs --apply
// Stop the application's writers first. Backups use the existing root APP_KEY.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const zlib = require('node:zlib');
const { MongoClient, BSON } = require('../realtime/node_modules/mongodb');
const dotenv = require('../realtime/node_modules/dotenv');

const root = path.resolve(__dirname, '..');
const env = dotenv.parse(fs.readFileSync(path.join(root, '.env')));
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
const json = value => BSON.EJSON.stringify(value, { relaxed: false });
const plain = value => BSON.EJSON.parse(value, { relaxed: true });
let operation = 'preflight';

async function snapshot(client, databaseName) {
    const db = client.db(databaseName);
    const session = client.startSession({ snapshot: true });
    const collections = [];
    try {
        for (const info of (await db.listCollections().toArray()).sort((a, b) => a.name.localeCompare(b.name))) {
            if (info.type !== 'collection' || info.name.startsWith('system.')) {
                throw new Error('Unsupported collection type; nothing was replaced.');
            }
            const collection = db.collection(info.name);
            const documents = [];
            for await (const document of collection.find({}, { session, raw: true }).sort({ _id: 1 })) {
                if (!Buffer.isBuffer(document)) throw new Error('Raw BSON export was not available.');
                documents.push(document.toString('base64'));
            }
            collections.push({ name: info.name, options: json(info.options || {}),
                indexes: json(await collection.listIndexes().toArray()), documents });
        }
        return { format: 'complitrack-mongo-copy-v1', database: databaseName,
            capturedAt: new Date().toISOString(), collections };
    } finally { await session.endSession(); }
}

function saveBackup(file, snapshotData, key) {
    const compressed = zlib.gzipSync(Buffer.from(JSON.stringify(snapshotData)));
    const iv = crypto.randomBytes(12);
    const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
    const ciphertext = Buffer.concat([cipher.update(compressed), cipher.final()]);
    fs.writeFileSync(file, JSON.stringify({ format: 'complitrack-mongo-copy-v1',
        cipher: 'aes-256-gcm', iv: iv.toString('base64'), tag: cipher.getAuthTag().toString('base64'),
        ciphertext: ciphertext.toString('base64'), sha256: hash(compressed) }), { flag: 'wx', mode: 0o600 });
    const saved = loadBackup(file, key);
    if (JSON.stringify(saved) !== JSON.stringify(snapshotData)) throw new Error('Backup verification failed.');
}

function loadBackup(file, key) {
    const saved = JSON.parse(fs.readFileSync(file));
    const decipher = crypto.createDecipheriv('aes-256-gcm', key, Buffer.from(saved.iv, 'base64'));
    decipher.setAuthTag(Buffer.from(saved.tag, 'base64'));
    const compressed = Buffer.concat([decipher.update(Buffer.from(saved.ciphertext, 'base64')), decipher.final()]);
    if (hash(compressed) !== saved.sha256) throw new Error('Backup checksum mismatch.');
    return JSON.parse(zlib.gunzipSync(compressed));
}

function signature(data) {
    return data.collections.map(collection => {
        const indexes = plain(collection.indexes).map(index => {
            const { v, ns, ...specification } = index;
            return Object.fromEntries(Object.entries(specification).sort(([a], [b]) => a.localeCompare(b)));
        }).sort((a, b) => a.name.localeCompare(b.name));
        return { name: collection.name, count: collection.documents.length,
            documentsSha256: hash(collection.documents.join('\n')),
            options: collection.options, indexes: json(indexes) };
    });
}

async function restore(client, databaseName, data) {
    const db = client.db(databaseName);
    for (const info of await db.listCollections().toArray()) {
        if (info.name.startsWith('system.')) throw new Error('Unexpected system collection in destination.');
        await db.collection(info.name).drop();
    }
    for (const info of data.collections) {
        operation = 'creating collection '+info.name;
        await db.createCollection(info.name, plain(info.options));
        const collection = db.collection(info.name);
        for (let offset = 0; offset < info.documents.length; offset += 100) {
            operation = 'inserting documents into '+info.name;
            const batch = info.documents.slice(offset, offset + 100).map(document =>
                BSON.deserialize(Buffer.from(document, 'base64'), { promoteValues: false }));
            await collection.insertMany(batch, { ordered: true, bypassDocumentValidation: true });
        }
        const indexes = plain(info.indexes).filter(index => index.name !== '_id_').map(index => {
            const { v, ns, ...specification } = index;
            return specification;
        });
        operation = 'creating indexes for '+info.name;
        if (indexes.length) await collection.createIndexes(indexes);
    }
    operation = 'verifying restored database';
    const actual = await snapshot(client, databaseName);
    if (JSON.stringify(signature(actual)) !== JSON.stringify(signature(data))) {
        throw new Error('Restored collection contents, options, or indexes did not match the export.');
    }
    return actual;
}

async function main() {
    if (!process.argv.includes('--apply')) throw new Error('An explicit --apply is required to replace local data.');
    const key = Buffer.from((env.APP_KEY || '').replace(/^base64:/, ''), 'base64');
    if (key.length !== 32) throw new Error('A valid 32-byte APP_KEY is required for encrypted backups.');
    const localName = env.MONGODB_LOCAL_DATABASE;
    const remoteName = env.MONGODB_REMOTE_DATABASE;
    if (!localName || !remoteName || !env.MONGODB_REMOTE_URI
        || !/^mongodb:\/\/(?:[^@/]+@)?(?:127\.0\.0\.1|localhost|\[::1\])(?::\d+)?\//i.test(env.MONGODB_LOCAL_URI || '')
        || ['admin', 'config', 'local'].includes(localName)) {
        throw new Error('Expected configured remote and loopback local application database targets.');
    }
    const local = new MongoClient(env.MONGODB_LOCAL_URI, { serverSelectionTimeoutMS: 10000 });
    const remote = new MongoClient(env.MONGODB_REMOTE_URI, { serverSelectionTimeoutMS: 10000 });
    const runId = new Date().toISOString().replace(/[:.]/g, '-') + '-' + crypto.randomBytes(4).toString('hex');
    const directory = path.join(root, 'storage/app/private/database-sync', runId);
    const stageName = 'complitrack_copy_stage_' + crypto.randomBytes(12).toString('hex');
    let stageCreated = false;
    let localBefore;
    let replacingLocal = false;
    let originalIndexDiskLimit = null;
    fs.mkdirSync(directory, { recursive: true, mode: 0o700 });
    try {
        await local.connect(); await remote.connect();
        const localHello = await local.db(localName).command({ hello: 1 });
        const remoteHello = await remote.db(remoteName).command({ hello: 1 });
        if (localHello.topologyVersion?.processId?.equals(remoteHello.topologyVersion?.processId)
            || (localHello.setName && localHello.setName === remoteHello.setName)) {
            throw new Error('Local and remote resolve to the same replica set; refusing replacement.');
        }
        const source = await snapshot(remote, remoteName);
        localBefore = await snapshot(local, localName);
        const remoteFile = path.join(directory, 'remote-export.enc.json');
        const localFile = path.join(directory, 'local-before-import.enc.json');
        saveBackup(remoteFile, source, key); saveBackup(localFile, localBefore, key);
        console.log('Encrypted remote export and local rollback backup verified.');
        const stats = await local.db(localName).stats();
        const available = stats.fsTotalSize - stats.fsUsedSize;
        const exportedBytes = source.collections.reduce((total, collection) =>
            total + collection.documents.reduce((sum, document) => sum + Buffer.byteLength(document, 'base64'), 0), 0);
        const diskLimit = await local.db('admin').command({ getParameter: 1, indexBuildMinAvailableDiskSpaceMB: 1 });
        if (available < diskLimit.indexBuildMinAvailableDiskSpaceMB * 1024 * 1024) {
            const temporaryLimit = 128;
            if (available < temporaryLimit * 1024 * 1024 + exportedBytes * 8 + 16 * 1024 * 1024) {
                throw new Error('Insufficient disk headroom even for the verified small export.');
            }
            operation = 'temporarily adjusting the local index-build disk threshold';
            await local.db('admin').command({ setParameter: 1, indexBuildMinAvailableDiskSpaceMB: temporaryLimit });
            originalIndexDiskLimit = diskLimit.indexBuildMinAvailableDiskSpaceMB;
            console.log(JSON.stringify({ exportedBytes, temporaryIndexDiskLimitMB: temporaryLimit,
                originalIndexDiskLimitMB: originalIndexDiskLimit }));
        }
        stageCreated = true;
        await restore(local, stageName, loadBackup(remoteFile, key));
        console.log('Temporary local restore matches remote documents, BSON types, collection options, and indexes.');
        replacingLocal = true;
        const copied = await restore(local, localName, loadBackup(remoteFile, key));
        replacingLocal = false;
        const remoteAfter = await snapshot(remote, remoteName);
        const report = { completedAt: new Date().toISOString(), source: 'remote', destination: 'local',
            remoteBackup: remoteFile, localRollbackBackup: localFile,
            exportMatchesLocal: true,
            remoteStillMatchesExport: JSON.stringify(signature(remoteAfter)) === JSON.stringify(signature(source)),
            collections: signature(copied).map(({ name, count, documentsSha256 }) => ({ name, count, documentsSha256 })) };
        fs.writeFileSync(path.join(directory, 'report.json'), JSON.stringify(report, null, 2), { flag: 'wx', mode: 0o600 });
        console.log(JSON.stringify(report));
    } catch (error) {
        if (replacingLocal && localBefore) {
            await restore(local, localName, localBefore);
            console.log('Original local database restored and verified after import failure.');
        }
        throw error;
    } finally {
        try {
            if (stageCreated && /^complitrack_copy_stage_[a-f0-9]{24}$/.test(stageName)) {
                await local.db(stageName).dropDatabase();
            }
        } finally {
            try {
                if (originalIndexDiskLimit !== null) {
                    await local.db('admin').command({ setParameter: 1, indexBuildMinAvailableDiskSpaceMB: originalIndexDiskLimit });
                    console.log('Original MongoDB index-build disk threshold restored.');
                }
            } finally { await local.close(); await remote.close(); }
        }
    }
}

module.exports = { snapshot, saveBackup, loadBackup, signature, restore };
if (require.main === module) main().catch(error => {
    // Driver errors can include credential-bearing URIs or document contents.
    console.error('Database copy failed (' + error.name + ').');
    console.error(JSON.stringify({ operation, code: error.code, codeName: error.codeName }));
    if (!(error instanceof Error) || !/^Mongo/.test(error.name)) console.error(error.message);
    process.exitCode = 1;
});
