const http = require('node:http');
const httpProxy = require('http-proxy');
const path = require('node:path');
const { serveBuildAsset } = require('./staticAssets');

// One local upstream for ngrok; the existing services keep their own ports.
function createDevProxy({
  appTarget = 'http://127.0.0.1:8001',
  realtimeTarget = 'http://127.0.0.1:4001',
  staticAssetRoot = path.resolve(__dirname, '../../public/build/assets'),
  fontAssetRoot = path.resolve(__dirname, '../../public/fonts'),
} = {}) {
  const proxy = httpProxy.createProxyServer({ proxyTimeout: 30000 });
  proxy.on('error', (_error, _request, response) => {
    if (typeof response.writeHead === 'function') {
      if (!response.headersSent) {
        response.writeHead(502, { 'Content-Type': 'application/json' });
      }
      response.end(JSON.stringify({ message: 'The local CompliTrack service is unavailable.' }));
    } else {
      response.destroy();
    }
  });

  function prepare(request) {
    // Preserve the public HTTPS scheme supplied by ngrok.
    request.headers['x-forwarded-proto'] ||= request.socket.encrypted ? 'https' : 'http';
    const pathname = new URL(request.url, 'http://localhost').pathname;
    if (pathname === '/realtime/health') {
      request.url = '/health';
      return realtimeTarget;
    }
    return pathname === '/socket.io' || pathname.startsWith('/socket.io/')
      ? realtimeTarget
      : appTarget;
  }

  const server = http.createServer(async (request, response) => {
    try {
      if (await serveBuildAsset(request, response, staticAssetRoot, fontAssetRoot)) return;
      proxy.web(request, response, { target: prepare(request) });
    } catch {
      if (!response.headersSent) response.writeHead(500, { 'Content-Type': 'text/plain' });
      response.end('Static asset unavailable.');
    }
  });
  server.on('upgrade', (request, socket, head) => {
    const pathname = new URL(request.url, 'http://localhost').pathname;
    if (pathname !== '/socket.io' && !pathname.startsWith('/socket.io/')) {
      socket.destroy();
      return;
    }
    proxy.ws(request, socket, head, { target: prepare(request) });
  });
  server.on('close', () => proxy.close());
  return server;
}

if (require.main === module) {
  const port = Number(process.env.COMPLITRACK_PROXY_PORT || 8080);
  const server = createDevProxy({
    appTarget: process.env.COMPLITRACK_APP_UPSTREAM,
    realtimeTarget: process.env.COMPLITRACK_REALTIME_UPSTREAM,
  });
  server.listen(port, '127.0.0.1', () => {
    console.log(`CompliTrack proxy: http://127.0.0.1:${port}`);
  });
  server.on('error', (error) => {
    console.error(`CompliTrack proxy failed: ${error.message}`);
    process.exitCode = 1;
  });
  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.once(signal, () => server.close());
  }
}

module.exports = { createDevProxy };
