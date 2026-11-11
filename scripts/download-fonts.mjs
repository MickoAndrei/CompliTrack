import fs from 'node:fs/promises';
import crypto from 'node:crypto';

// Existing families/weights only. Google's CSS supplies web subsets of its OFL fonts.
const url = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap';
const response = await fetch(url, { headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36' } });
if (!response.ok) throw Error(`Font CSS request failed: ${response.status}`);
const css = await response.text();
await fs.mkdir('public/fonts', { recursive: true });
const manifest = { source: url, licenses: {}, files: [] };
for (const family of ['inter', 'plusjakartasans', 'poppins']) {
    const source = `https://raw.githubusercontent.com/google/fonts/main/ofl/${family}/OFL.txt`;
    const response = await fetch(source);
    if (!response.ok) throw Error('Font license unavailable');
    const license = await response.text();
    if (!license.includes('SIL OPEN FONT LICENSE Version 1.1')) throw Error('Unexpected font license');
    await fs.mkdir(`public/fonts/${family}`, { recursive: true });
    await fs.writeFile(`public/fonts/${family}/OFL.txt`, license);
    manifest.licenses[family] = source;
}
let localCss = '/* Original Google Fonts families and weights; SIL OFL 1.1 licenses accompany the font files. */\n';
const downloaded = new Map();
for (const match of css.matchAll(/\/\*\s*([^*]+?)\s*\*\/\s*(@font-face\s*\{[^}]+\})/g)) {
    let block = match[2];
    const family = /font-family:\s*'([^']+)'/.exec(block)?.[1];
    const remote = /url\((https:\/\/fonts\.gstatic\.com\/[^)]+\.woff2)\)/.exec(block)?.[1];
    if (!family || !remote) throw Error('Unexpected font declaration');
    const folder = family.toLowerCase().replaceAll(' ', '');
    if (!downloaded.has(remote)) {
        const response = await fetch(remote);
        if (!response.ok) throw Error('Font download failed');
        downloaded.set(remote, Buffer.from(await response.arrayBuffer()));
    }
    const bytes = downloaded.get(remote);
    if (bytes.subarray(0, 4).toString() !== 'wOF2') throw Error('Expected a WOFF2 file');
    const sha256 = crypto.createHash('sha256').update(bytes).digest('hex');
    const filename = `${folder}-${sha256.slice(0, 12)}.woff2`;
    await fs.writeFile(`public/fonts/${folder}/${filename}`, bytes);
    if (!manifest.files.some(f => f.filename === filename)) manifest.files.push({ family, filename, source: remote, bytes: bytes.length, sha256 });
    block = block.replace(remote, `../fonts/${folder}/${filename}`);
    localCss += `/* ${match[1].trim()} */\n${block}\n`;
}
if (manifest.files.length < 3) throw Error('Not all font families were retrieved');
await fs.writeFile('public/css/fonts.css', localCss);
await fs.writeFile('public/fonts/sources.json', JSON.stringify(manifest, null, 2) + '\n');
console.log(JSON.stringify(manifest.files.map(({family,filename,bytes})=>({family,filename,bytes}))));
