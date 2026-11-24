// Run after npm run build. Exercises real browser tabs in one cookie profile.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');

const pause = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const entry = JSON.parse(fs.readFileSync(path.join(publicRoot, 'build/manifest.json'), 'utf8'))['resources/js/app.js'].file;
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/echo' || url.pathname === '/api/v1/echo') {
        response.setHeader('Content-Type', 'application/json');
        response.end(JSON.stringify({ tab: url.searchParams.get('_tab'), csrf: request.headers['x-csrf-token'], method: request.method }));
    } else if (url.pathname === '/sanctum/csrf-cookie') {
        response.setHeader('Set-Cookie', 'XSRF-TOKEN=another-tabs-token; Path=/');
        response.end();
    } else if (url.pathname.startsWith('/build/') || url.pathname === '/js/tab-session.js') {
        const file = path.resolve(publicRoot, `.${url.pathname}`);
        if (!file.startsWith(publicRoot + path.sep) || !fs.existsSync(file)) { response.writeHead(404).end(); return; }
        response.setHeader('Content-Type', 'text/javascript');
        response.end(fs.readFileSync(file));
    } else {
        response.setHeader('Content-Type', 'text/html');
        response.end(`<!doctype html><html><head><script src="/js/tab-session.js"></script>
            <meta name="csrf-token" content="csrf-${url.searchParams.get('_tab')}">
            <script type="module" src="/build/${entry}"></script></head><body>
            <a id="next" href="/next?filter=open#details">Next</a>
            <a id="external" href="https://example.org/">External</a>
            <form action="/echo" method="get"><input name="filter" value="open"><button>Submit</button></form>
            </body></html>`);
    }
});

(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = `http://127.0.0.1:${server.address().port}`;
    const profile = fs.mkdtempSync(path.join(root, 'storage/framework/testing/edge-tab-session-'));
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', [
        '--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0',
        '--disable-background-timer-throttling', '--disable-renderer-backgrounding',
        `--user-data-dir=${profile}`, 'about:blank',
    ], { windowsHide: true, stdio: 'ignore' });
    let socket;
    try {
        let version;
        for (let attempt = 0; attempt < 60; attempt++) {
            try {
                const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
                version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break;
            }
            catch { await pause(100); }
        }
        assert.ok(version, 'Edge started');
        socket = new WebSocket(version.webSocketDebuggerUrl);
        await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
        let sequence = 0;
        const pending = new Map();
        socket.addEventListener('message', ({ data }) => {
            const message = JSON.parse(data);
            if (pending.has(message.id)) {
                pending.get(message.id)(message);
                pending.delete(message.id);
            }
        });
        const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
            const id = ++sequence;
            const timer = setTimeout(() => reject(Error(`Timed out: ${method}`)), 10000);
            pending.set(id, message => {
                clearTimeout(timer);
                if (message.error) reject(Error(`${method}: ${JSON.stringify(message.error)}`));
                else resolve(message.result);
            });
            socket.send(JSON.stringify({ id, method, params, sessionId }));
        });
        const evaluate = async (session, expression) => {
            const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, session);
            if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
            return result.result.value;
        };
        const ready = async session => {
            for (let attempt = 0; attempt < 80; attempt++) {
                try {
                    const tab = await evaluate(session, `document.readyState === 'complete' && !document.documentElement.hidden && window.Alpine && sessionStorage.getItem('complitrack.tab')`);
                    if (tab) return tab;
                } catch { /* Redirect may replace the execution context. */ }
                await pause(50);
            }
            throw Error('Tab did not become ready');
        };
        const create = async (url = '/login', inheritedTab) => {
            const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
            const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
            await send('Page.enable', {}, sessionId);
            if (inheritedTab) await send('Page.addScriptToEvaluateOnNewDocument', {
                source: `if (!sessionStorage.getItem('test-seeded')) { sessionStorage.setItem('complitrack.tab', ${JSON.stringify(inheritedTab)}); sessionStorage.setItem('test-seeded', '1'); }`,
            }, sessionId);
            await send('Page.navigate', { url: origin + url }, sessionId);
            return { session: sessionId, tab: await ready(sessionId) };
        };
        const first = await create();
        const second = await create();
        console.log('Browser tabs loaded.');
        assert.notEqual(first.tab, second.tab, 'Separate tabs get separate contexts');
        const duplicate = await create(`/login?_tab=${first.tab}`, first.tab);
        assert.notEqual(duplicate.tab, first.tab, 'Duplicated sessionStorage gets a fresh context');
        console.log('Duplicate-tab isolation passed.');
        assert.equal(await evaluate(first.session, `sessionStorage.getItem('complitrack.tab')`), first.tab);
        const links = await evaluate(first.session, `({ next: document.querySelector('#next').href, external: document.querySelector('#external').href, hidden: document.querySelector('input[name="_tab"]').value })`);
        assert.equal(new URL(links.next).searchParams.get('_tab'), first.tab);
        assert.equal(new URL(links.next).searchParams.get('filter'), 'open');
        assert.equal(new URL(links.next).hash, '#details');
        assert.equal(links.external, 'https://example.org/');
        assert.equal(links.hidden, first.tab);
        const reviewForm = await evaluate(first.session, `new Promise(resolve => {
            const form = document.createElement('form'); form.id = 'review-test';
            form.setAttribute('action', '/audits/000000000000000000000001');
            form.innerHTML = '<textarea name="reviewNotes"></textarea><button name="action" value="accept">Accept</button><button name="action" value="return">Return</button>';
            let mutations = 0;
            const observer = new MutationObserver(records => { mutations += records.length; });
            observer.observe(form, { attributes: true, attributeFilter: ['action'] });
            document.body.append(form);
            setTimeout(() => {
                observer.disconnect();
                resolve({ mutations, action: form.getAttribute('action'), tab: form.querySelector('[name="_tab"]').value, shadowed: typeof form.action !== 'string' });
            }, 150);
        })`);
        assert.equal(reviewForm.shadowed, true, 'Review controls shadow the native form.action property');
        assert.equal(new URL(reviewForm.action).searchParams.get('_tab'), first.tab);
        assert.equal(reviewForm.tab, first.tab);
        assert.equal(reviewForm.mutations, 1, 'Dynamic review form settles after one URL mutation without freezing');
        console.log('Dynamic review form with named action controls stays responsive.');
        const fetched = await evaluate(first.session, `fetch('/echo', { method: 'POST', body: 'payload' }).then(response => response.json())`);
        assert.equal(fetched.tab, first.tab);
        assert.equal(fetched.method, 'POST');
        await evaluate(second.session, `fetch('/sanctum/csrf-cookie')`);
        const axios = await evaluate(first.session, `window.axios.post('/api/v1/echo', {}).then(response => response.data)`);
        assert.equal(axios.tab, first.tab);
        assert.equal(axios.csrf, `csrf-${first.tab}`, 'Axios uses the document token after another tab changes XSRF cookie');
        await evaluate(first.session, `document.querySelector('#next').click()`);
        await pause(200);
        assert.equal(await ready(first.session), first.tab, 'Navigation retains context');
        await send('Page.reload', {}, first.session);
        assert.equal(await ready(first.session), first.tab, 'Refresh retains context');
        console.log('Passed: independent tabs, duplicate detection, navigation, refresh, links, forms, fetch, Axios and CSRF isolation.');
        await send('Browser.close');
    } finally {
        socket?.close();
        browser.kill();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
