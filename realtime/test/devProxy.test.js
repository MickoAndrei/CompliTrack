const { test } = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const { once } = require('node:events');
const { Server } = require('socket.io');
const { io } = require('socket.io-client');
const { createDevProxy } = require('../src/devProxy');

async function listen(server) {
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  return `http://127.0.0.1:${server.address().port}`;
}

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
