const { test } = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const { once } = require('node:events');
const { Server } = require('socket.io');
const { io } = require('socket.io-client');
const { createDevProxy } = require('../src/devProxy');
const { mkdtemp, mkdir, writeFile, rm } = require('node:fs/promises');
const path = require('node:path');
const os = require('node:os');
const { gunzipSync } = require('node:zlib');

async function listen(server) {
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  return `http://127.0.0.1:${server.address().port}`;
}

test('document server proxy strips its prefix and app cookies and preserves the public path', async (t) => {
  const documents = http.createServer((req, res) => {
    res.setHeader('Content-Type', 'application/json');
    res.end(JSON.stringify({ url: req.url, cookie: req.headers.cookie || null,
      host: req.headers['x-forwarded-host'], proto: req.headers['x-forwarded-proto'] }));
  });
  const proxy = createDevProxy({ documentTarget: await listen(documents) });
  t.after(() => { proxy.close(); documents.close(); });
  const base = await listen(proxy);
  const response = await fetch(`${base}/document-server/web-apps/apps/api/documents/api.js?test=1`, {
    headers: { cookie: 'complitrack_session=private', 'x-forwarded-proto': 'https' },
  });
  const data = await response.json();
  assert.equal(data.url, '/web-apps/apps/api/documents/api.js?test=1');
  assert.equal(data.cookie, null);
  assert.equal(data.host, new URL(base).host + '/document-server');
  assert.equal(data.proto, 'https');
});

test('hashed build assets are compressed and cached without caching authenticated responses', async (t) => {
  const root = await mkdtemp(path.join(os.tmpdir(), 'complitrack-assets-'));
  const source = 'export const value = "asset";\n'.repeat(1000);
  await writeFile(path.join(root, 'app-1234abcd.js'), source);
  const app = http.createServer((_req, res) => { res.setHeader('Cache-Control', 'no-cache, private'); res.end('upstream'); });
  const proxy = createDevProxy({ appTarget: await listen(app), staticAssetRoot: root });
  t.after(async () => { proxy.close(); app.close(); await rm(root, { recursive: true, force: true }); });
  const base = await listen(proxy);
  const response = await new Promise((resolve, reject) => {
    http.get(`${base}/build/assets/app-1234abcd.js`, { headers: { 'Accept-Encoding': 'gzip' } }, response => {
      const chunks = []; response.on('data', chunk => chunks.push(chunk));
      response.on('end', () => resolve({ headers: response.headers, body: Buffer.concat(chunks) }));
    }).on('error', reject);
  });
  assert.equal(response.headers['content-encoding'], 'gzip');
  assert.equal(gunzipSync(response.body).toString(), source);
  assert.ok(response.body.length < Buffer.byteLength(source) / 2);
  assert.equal(response.headers['cache-control'], 'public, max-age=31536000, immutable');
  assert.equal((await fetch(`${base}/build/assets/app-1234abcd.js`, { headers: { 'If-None-Match': response.headers.etag } })).status, 304);
  const head = await fetch(`${base}/build/assets/app-1234abcd.js`, { method: 'HEAD', headers: { 'Accept-Encoding': 'gzip;q=0' } });
  assert.equal(head.headers.get('content-encoding'), null);
  assert.equal(head.headers.get('content-length'), String(Buffer.byteLength(source)));
  assert.equal(await head.text(), '');
  const api = await fetch(`${base}/api/v1/users`);
  assert.equal(await api.text(), 'upstream');
  assert.equal(api.headers.get('cache-control'), 'no-cache, private');
  assert.equal(await (await fetch(`${base}/build/assets/missing-1234abcd.js`)).text(), 'upstream');
  assert.equal(await (await fetch(`${base}/build/assets/..%2Fprivate-1234abcd.js`)).text(), 'upstream');
});

test('hashed local fonts retain their bytes and immutable cache headers', async (t) => {
  const root = await mkdtemp(path.join(os.tmpdir(), 'complitrack-fonts-'));
  await mkdir(path.join(root, 'inter'));
  const source = Buffer.from('wOF2-isolated-font-fixture');
  await writeFile(path.join(root, 'inter', 'inter-123456abcdef.woff2'), source);
  const app = http.createServer((_req, res) => res.end('upstream'));
  const proxy = createDevProxy({ appTarget: await listen(app), fontAssetRoot: root });
  t.after(async () => { proxy.close(); app.close(); await rm(root, { recursive: true, force: true }); });
  const base = await listen(proxy);
  const url = `${base}/fonts/inter/inter-123456abcdef.woff2`;
  const response = await fetch(url, { headers: { 'Accept-Encoding': 'gzip' } });
  assert.equal(response.headers.get('content-type'), 'font/woff2');
  assert.equal(response.headers.get('content-encoding'), null);
  assert.equal(response.headers.get('cache-control'), 'public, max-age=31536000, immutable');
  assert.deepEqual(Buffer.from(await response.arrayBuffer()), source);
  assert.equal((await fetch(url, { headers: { 'If-None-Match': response.headers.get('etag') } })).status, 304);
  assert.equal(await (await fetch(url, { method: 'HEAD' })).text(), '');
  assert.equal(await (await fetch(`${base}/fonts/inter/OFL.txt`)).text(), 'upstream');
});

test('proxy preserves API bodies, cookies, public host and HTTPS scheme', async (t) => {
  const app = http.createServer(async (req, res) => {
    let body = '';
    for await (const chunk of req) body += chunk;
    res.setHeader('Content-Type', 'application/json');
    res.setHeader('Set-Cookie', 'session=test; HttpOnly');
    res.end(JSON.stringify({ method: req.method, url: req.url, body, host: req.headers.host, proto: req.headers['x-forwarded-proto'], cookie: req.headers.cookie }));
  });
  const proxy = createDevProxy({ appTarget: await listen(app) });
  t.after(() => { proxy.close(); app.close(); });
  const url = await listen(proxy);
  const response = await new Promise((resolve, reject) => {
    const request = http.request(`${url}/api/v1/auth/login?test=1`, {
      method: 'POST',
      headers: { host: 'public.example.test', 'x-forwarded-proto': 'https', cookie: 'session=incoming' },
    }, async incoming => {
      let body = '';
      for await (const chunk of incoming) body += chunk;
      resolve({ status: incoming.statusCode, headers: incoming.headers, body });
    });
    request.on('error', reject);
    request.end('{"email":"test@example.test"}');
  });
  assert.equal(response.status, 200);
  assert.deepEqual(response.headers['set-cookie'], ['session=test; HttpOnly']);
  assert.deepEqual(JSON.parse(response.body), {
    method: 'POST', url: '/api/v1/auth/login?test=1', body: '{"email":"test@example.test"}',
    host: 'public.example.test', proto: 'https', cookie: 'session=incoming',
  });
});

test('realtime health is routed separately and an unavailable app returns 502', async (t) => {
  const realtime = http.createServer((req, res) => res.end(req.url));
  const unused = http.createServer();
  const appTarget = await listen(unused);
  await new Promise(resolve => unused.close(resolve));
  const proxy = createDevProxy({ appTarget, realtimeTarget: await listen(realtime) });
  t.after(() => { proxy.close(); realtime.close(); });
  const url = await listen(proxy);
  assert.equal(await (await fetch(`${url}/realtime/health`)).text(), '/health');
  const response = await fetch(`${url}/login`);
  assert.equal(response.status, 502);
  assert.match((await response.json()).message, /unavailable/);
});

test('Socket.IO websocket upgrades and authenticated events pass through the proxy', async (t) => {
  const realtime = http.createServer();
  const sockets = new Server(realtime);
  sockets.use((socket, next) => next(socket.handshake.auth.token === 'test-token' ? undefined : new Error('Unauthorized.')));
  sockets.on('connection', socket => socket.emit('server_version', 'test-version'));
  const proxy = createDevProxy({ realtimeTarget: await listen(realtime) });
  const url = await listen(proxy);
  const client = io(url, { transports: ['websocket'], auth: { token: 'test-token' }, autoConnect: false, reconnection: false });
  t.after(() => { client.disconnect(); sockets.close(); proxy.close(); realtime.close(); });
  const version = new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('WebSocket proxy timed out')), 5000);
    client.once('server_version', value => { clearTimeout(timer); resolve(value); });
    client.once('connect_error', error => { clearTimeout(timer); reject(error); });
  });
  client.connect();
  assert.equal(await version, 'test-version');
});
