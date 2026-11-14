const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn, execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/attention-browser');
const pause = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
execFileSync('php', ['tests/Fixtures/attention.php'], { cwd: root, windowsHide: true });
const notices = [
    { _id: '111111111111111111111111', title: 'Evidence uploaded', message: 'Engineering Auditee uploaded evidence for CHK-IN-PROGRESS.', category: 'Evidence', type: 'evidence_submitted', isRead: false, createdAt: '2026-11-08T15:12:00Z', destination: '/api/v1/notifications/111111111111111111111111/open' },
    { _id: '222222222222222222222222', title: 'Evidence ready for review', message: 'Corrective evidence has been submitted for your review.', category: 'Evidence', type: 'evidence_submitted', isRead: false, createdAt: '2026-11-04T05:42:00Z', destination: null },
    { _id: '333333333333333333333333', title: 'Audit accepted', message: 'Your submitted audit was accepted.', category: 'Audit', type: 'audit_accepted', isRead: true, createdAt: '2026-11-03T05:42:00Z', destination: null },
];
const logs = [{ _id: '444444444444444444444444', action: 'Document uploaded', userName: 'Department Secretary', userId: '555555555555555555555555', entityType: 'archive_document', entityId: '666666666666666666666666', category: 'documents', outcome: 'success', createdAt: '2026-11-08T15:12:00Z', ipAddress: '127.0.0.1', method: 'POST', route: 'api/v1/archives/upload' }];
const departmentRows = [{ _id: '777777777777777777777777', departmentName: 'Engineering Department', departmentCode: 'CET-ENG', collegeName: 'CET', campusName: 'Claveria', isActive: true }, { _id: '888888888888888888888888', departmentName: 'Technology Department', departmentCode: 'CET-TECH', collegeName: 'CET', campusName: 'Claveria', isActive: false }];
const userRows = [{ _id: '999999999999999999999999', fullName: 'Test Auditor', email: 'auditor@example.test', userCode: 'AUD-001', roleId: 2, isActive: true, assignedDepartmentIds: [], organizationalUnits: [], organizationalColleges: [] }, { _id: 'aaaaaaaaaaaaaaaaaaaaaaaa', fullName: 'Test Auditee', email: 'auditee@example.test', userCode: 'ATE-001', roleId: 3, isActive: false, assignedDepartmentIds: [], organizationalUnits: [], organizationalColleges: [] }];
const requests = [];
let failNotifications = false;
let notificationDelay = 0;
let origin;
const json = (response, value, status = 200) => { response.writeHead(status, { 'Content-Type': 'application/json' }); response.end(JSON.stringify(value)); };
const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname.startsWith('/api/')) requests.push({ path: url.pathname, method: request.method, params: Object.fromEntries(url.searchParams) });
    if (url.pathname === '/api/v1/notifications' && request.method === 'GET') {
        if (notificationDelay) await pause(notificationDelay);
        if (failNotifications) return json(response, { message: 'Notifications are temporarily unavailable.' }, 503);
        const status = url.searchParams.get('status');
        const unread = url.searchParams.get('unread') === 'true' || status === 'unread';
        const search = (url.searchParams.get('search') || '').toLowerCase();
        const rows = notices.filter(row => (!unread || !row.isRead) && (status !== 'read' || row.isRead)
            && (!search || `${row.title} ${row.message}`.toLowerCase().includes(search)));
        return json(response, { data: rows, meta: { total: rows.length, currentPage: 1, lastPage: 1, unreadCount: notices.filter(row => !row.isRead).length } });
    }
    if (request.method === 'PATCH' && url.pathname.startsWith('/api/v1/notifications/')) {
        if (url.pathname.endsWith('/read-all')) notices.forEach(row => { row.isRead = true; });
        else notices.find(row => url.pathname.includes(row._id)).isRead = true;
        return json(response, { message: 'Notifications marked as read.' });
    }
    if (/^\/api\/v1\/notifications\/[a-f0-9]{24}$/.test(url.pathname)) return json(response, { data: notices.find(row => url.pathname.endsWith(row._id)) });
    if (url.pathname === '/api/v1/logs') return json(response, { data: logs, meta: { total: 1, currentPage: 1, lastPage: 1 }, summary: { activities: 1, security: 0, denied: 0 } });
    if (/^\/api\/v1\/logs\/[a-f0-9]{24}$/.test(url.pathname)) return json(response, { data: logs[0] });
    if (url.pathname === '/api/v1/departments') return json(response, { data: departmentRows, meta: { total: 2, currentPage: 1, lastPage: 1 }, summary: { total: 2, active: 1, inactive: 1 } });
    if (url.pathname === '/api/v1/users') {
        if (url.searchParams.has('includeSummary') && !['0', '1'].includes(url.searchParams.get('includeSummary'))) {
            return json(response, { message: 'The include summary field must be true or false.' }, 422);
        }
        return json(response, { data: userRows, meta: { total: 2, currentPage: 1, lastPage: 1 }, summary: { total: 2, active: 1, inactive: 1, auditors: 1, auditees: 1 }, options: { departments: [], colleges: [], responsibilities: [] } });
    }
    if (url.pathname === '/api/v1/documents/tree') return json(response, { units: [], colleges: [] });
    if (url.pathname === '/api/v1/directory/organization') return json(response, { users: [] });
    const fixture = /^\/notifications-([0-3])$/.exec(url.pathname);
    const directoryFixture = /^\/directory-(users|departments)-([01])$/.exec(url.pathname);
    if (fixture || directoryFixture || url.pathname === '/logs') {
        response.writeHead(200, { 'Content-Type': 'text/html' });
        return response.end(fs.readFileSync(path.join(directory, fixture ? `notifications-${fixture[1]}.html` : directoryFixture ? `directory-${directoryFixture[1]}-${directoryFixture[2]}.html` : 'logs.html'), 'utf8').replaceAll('http://fixture.local', origin).replaceAll('https://fixture.local', origin));
    }
    const file = path.resolve(root, 'public', `.${decodeURIComponent(url.pathname)}`);
    if (!file.startsWith(path.join(root, 'public') + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) return response.writeHead(404).end();
    const extension = path.extname(file);
    response.setHeader('Content-Type', ({ '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.jpg': 'image/jpeg' })[extension] || 'application/octet-stream');
    response.end(fs.readFileSync(file));
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    origin = `http://127.0.0.1:${server.address().port}`;
    const profile = fs.mkdtempSync(path.join(directory, 'edge-'));
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let socket;
    try {
        let version;
        for (let attempt = 0; attempt < 60; attempt++) {
            try { const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0]; version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); break; } catch { await pause(100); }
        }
        assert.ok(version, 'Edge started');
        socket = new WebSocket(version.webSocketDebuggerUrl);
        await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
        let sequence = 0; const pending = new Map(); const exceptions = [];
        socket.addEventListener('message', ({ data }) => {
            const message = JSON.parse(data);
            if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails);
            if (pending.has(message.id)) { pending.get(message.id)(message); pending.delete(message.id); }
        });
        const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
            const id = ++sequence; const timer = setTimeout(() => reject(Error(`Timed out: ${method}`)), 15000);
            pending.set(id, message => { clearTimeout(timer); message.error ? reject(Error(`${method}: ${JSON.stringify(message.error)}`)) : resolve(message.result); });
            socket.send(JSON.stringify({ id, method, params, sessionId }));
        });
        const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        await send('Page.enable', {}, sessionId); await send('Runtime.enable', {}, sessionId);
        const evaluate = async expression => {
            const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, sessionId);
            if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
            return result.result.value;
        };
        const waitFor = async expression => {
            for (let attempt = 0; attempt < 100; attempt++) { try { if (await evaluate(expression)) return; } catch {} await pause(50); }
            throw Error(`Timed out: ${expression}`);
        };
        const headerBaseline = new Map();
        for (const role of [0, 1, 2, 3]) {
            for (const width of [1440, 390, 320]) {
                await send('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 600 }, sessionId);
                await send('Page.navigate', { url: `${origin}/notifications-${role}` }, sessionId);
                await waitFor(`window.Alpine && document.querySelector('.notification-item') && !document.documentElement.hidden`);
                const state = await evaluate(`({ count: document.querySelectorAll('.notification-item').length, overflow: document.documentElement.scrollWidth > innerWidth, text: document.querySelector('.notification-inbox').textContent, table: !!document.querySelector('.notification-inbox table') })`);
                assert.equal(state.count, 3); assert.equal(state.overflow, false, `Inbox role ${role}, ${width}px fits`); assert.equal(state.table, false);
                assert.ok(state.text.includes('Evidence uploaded'));
                const header = await evaluate(`(() => {
                    const styles = (selector, properties) => {
                        const element = document.querySelector(selector), css = getComputedStyle(element);
                        return Object.fromEntries(properties.map(property => [property, css[property]]));
                    };
                    return {
                        shell: styles('.app-header', ['padding', 'minHeight', 'fontFamily', 'borderBottomColor']),
                        title: styles('.app-header h1', ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'color']),
                        subtitle: styles('.app-header .header-page-subtitle', ['display', 'fontSize', 'lineHeight', 'color']),
                        bell: styles('.app-header .notification-bell-btn', ['width', 'height', 'backgroundColor']),
                        avatar: styles('.app-header .header-avatar-initials', ['width', 'height', 'border', 'fontSize']),
                        account: styles('.app-header .header-user-info', ['display', 'gap', 'lineHeight']),
                        menu: styles('.app-header .menu-toggle', ['display', 'width', 'height']),
                    };
                })()`);
                if (role === 0) headerBaseline.set(width, header);
                else assert.deepEqual(header, headerBaseline.get(width), `Header role ${role}, ${width}px matches Super Admin`);
                if (role === 0 && width === 1440 || role === 3 && width === 390) {
                    const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
                    fs.writeFileSync(path.join(directory, `inbox-role-${role}-${width}.png`), Buffer.from(data, 'base64'));
                }
            }
        }
        const feed = `Alpine.$data(document.querySelector('.notification-inbox'))`;
        await evaluate(`${feed}.notificationStatus='unread'; ${feed}.load(1)`);
        await waitFor(`document.querySelectorAll('.notification-item').length === 2 && !${feed}.loading`);
        await evaluate(`${feed}.search='no matching text'; ${feed}.load(1)`);
        await waitFor(`!${feed}.loading && ${feed}.rows.length === 0`);
        await waitFor(`getComputedStyle(document.querySelector('.attention-empty')).display !== 'none'`);
        await evaluate(`${feed}.search=''; ${feed}.notificationStatus='all'; ${feed}.load(1)`);
        await waitFor(`${feed}.rows.length === 3 && !${feed}.loading`);
        await evaluate(`${feed}.openRecordDetails(${feed}.rows[0])`);
        await waitFor(`document.querySelector('dialog').open && !${feed}.detailLoading && !!${feed}.details`);
        assert.equal(await evaluate(`document.querySelector('dialog').textContent.includes('Evidence uploaded')`), true);
        await evaluate(`document.querySelector('dialog').close()`);
        await evaluate(`${feed}.markRead(${feed}.rows.find(row => !row.isRead))`);
        await waitFor(`${feed}.notificationUnreadCount === 1`);
        await evaluate(`${feed}.markAllNotificationsRead()`);
        await waitFor(`${feed}.notificationUnreadCount === 0`);
        assert.ok(requests.filter(row => row.method === 'PATCH').every(row => row.path.startsWith('/api/v1/notifications/')));
        assert.equal(requests.filter(row => row.path.startsWith('/api/v1/logs')).length, 0, 'Inboxes and bells do not request logs');
        notificationDelay = 500; failNotifications = true;
        await evaluate(`${feed}.search='temporary failure'`);
        const loading = evaluate(`${feed}.load(1)`);
        await waitFor(`${feed}.loading`);
        await loading;
        assert.equal(await evaluate(`${feed}.error`), 'Notifications are temporarily unavailable.');
        failNotifications = false; notificationDelay = 0;
        await evaluate(`${feed}.search=''; ${feed}.load(1)`);
        for (const width of [1440, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 600 }, sessionId);
            await send('Page.navigate', { url: `${origin}/logs` }, sessionId);
            await waitFor(`window.Alpine && document.querySelector('.attention-log-table tbody tr') && !document.documentElement.hidden`);
            assert.equal(await evaluate(`document.documentElement.scrollWidth > innerWidth`), false, `Logs ${width}px fit`);
            const pageShot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
            fs.writeFileSync(path.join(directory, `logs-${width}.png`), Buffer.from(pageShot.data, 'base64'));
            const activity = `Alpine.$data(document.querySelector('.activity-log-page'))`;
            await evaluate(`${activity}.openRecordDetails(${activity}.rows[0])`);
            await waitFor(`document.querySelector('dialog').open && !!${activity}.details`);
            assert.equal(await evaluate(`document.querySelector('dialog').querySelectorAll('input, textarea, select').length`), 0, 'Log details are read only');
            assert.equal(await evaluate(`document.querySelector('dialog').getBoundingClientRect().width <= innerWidth`), true);
            const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
            fs.writeFileSync(path.join(directory, `log-details-${width}.png`), Buffer.from(data, 'base64'));
            await evaluate(`document.querySelector('dialog').close()`);
            await evaluate(`${activity}.categoryFilter='security'; ${activity}.actorFilter='Secretary'; ${activity}.outcomeFilter='denied'; ${activity}.load(1)`);
            const last = requests.filter(row => row.path === '/api/v1/logs').at(-1);
            assert.equal(last.params.category, 'security'); assert.equal(last.params.actor, 'Secretary'); assert.equal(last.params.outcome, 'denied');
        }
        for (const role of [0, 1]) for (const section of ['users', 'departments']) for (const width of [1440, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 600 }, sessionId);
            await send('Page.navigate', { url: `${origin}/directory-${section}-${role}` }, sessionId);
            await waitFor(`window.Alpine && document.querySelector('.directory-page tbody tr') && !Alpine.$data(document.querySelector('.directory-page')).loading && !document.documentElement.hidden`);
            assert.equal(await evaluate(`document.documentElement.scrollWidth > innerWidth`), false, `${section} role ${role} ${width}px fits`);
            const cards = await evaluate(`document.querySelectorAll('.directory-page .metric-stat-card').length`);
            assert.equal(cards, section === 'users' && role === 0 ? 4 : 3);
            assert.equal(await evaluate(`document.querySelector('.directory-page .metric-number').textContent`), '2');
            assert.equal(await evaluate(`document.querySelectorAll('.directory-page .metric-icon-box svg').length`), cards);
            if (role === 1) assert.equal(await evaluate(`document.querySelectorAll('.user-create-trigger,.department-create-trigger,.um-menu-trigger,[x-ref=userEditor]').length`), 0, 'Admin directories remain read only');
            if (width === 1440 || width === 390) {
                const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
                fs.writeFileSync(path.join(directory, `${section}-role-${role}-${width}.png`), Buffer.from(shot.data, 'base64'));
            }
        }
        assert.deepEqual(exceptions, [], 'No browser JavaScript errors');
        console.log('Passed: notification inboxes, Activity Logs and both role Users/Departments pages at 1440/390/320px; live cards, responsive tables, read-only Admin directories, inbox actions and log details.');
        await send('Browser.close');
    } finally { socket?.close(); browser.kill(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
