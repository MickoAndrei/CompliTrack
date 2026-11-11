const fs = require('node:fs');
const { realpath, stat } = require('node:fs/promises');
const path = require('node:path');
const { pipeline } = require('node:stream');
const { createGzip } = require('node:zlib');

// Only Vite's content-hashed JS/CSS files are public and immutable here.
async function serveBuildAsset(request, response, root, fontRoot) {
  if (!['GET', 'HEAD'].includes(request.method)) return false;
  const pathname = new URL(request.url, 'http://localhost').pathname;
  const match = /^\/build\/assets\/([a-zA-Z0-9_-]+-[a-zA-Z0-9_-]{8}\.(js|css))$/.exec(pathname);
  const font = fontRoot && /^\/fonts\/(inter|plusjakartasans|poppins)\/([a-z]+-[a-f0-9]{12}\.woff2)$/.exec(pathname);
  if (!match && !font) return false;
  let filename, info;
  try {
    const actualRoot = await realpath(font ? path.join(fontRoot, font[1]) : root);
    filename = await realpath(path.join(actualRoot, font ? font[2] : match[1]));
    if (path.dirname(filename) !== actualRoot) return false;
    info = await stat(filename);
    if (!info.isFile()) return false;
  } catch (error) {
    if (['ENOENT', 'ENOTDIR'].includes(error.code)) return false;
    throw error;
  }
  const etag = `W/"${info.size.toString(16)}-${info.mtimeMs.toString(16)}"`;
  response.setHeader('Content-Type', font ? 'font/woff2' : match[2] === 'js' ? 'text/javascript; charset=utf-8' : 'text/css; charset=utf-8');
  response.setHeader('Cache-Control', 'public, max-age=31536000, immutable');
  response.setHeader('Vary', 'Accept-Encoding');
  response.setHeader('ETag', etag);
  response.setHeader('X-Content-Type-Options', 'nosniff');
  if (request.headers['if-none-match']?.split(/\s*,\s*/).includes(etag)) {
    response.writeHead(304); response.end(); return true;
  }
  const gzip = !font && (request.headers['accept-encoding'] || '').split(',').some(value => {
    const [encoding, ...parameters] = value.trim().split(';');
    return encoding === 'gzip' && !parameters.some(parameter => /^\s*q=0(?:\.0*)?\s*$/.test(parameter));
  });
  if (gzip) response.setHeader('Content-Encoding', 'gzip');
  else response.setHeader('Content-Length', info.size);
  response.writeHead(200);
  if (request.method === 'HEAD') { response.end(); return true; }
  const streams = [fs.createReadStream(filename)];
  if (gzip) streams.push(createGzip());
  streams.push(response);
  pipeline(...streams, error => { if (error) response.destroy(); });
  return true;
}

module.exports = { serveBuildAsset };
