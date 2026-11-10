// Local SMTP sink: no external email, application records, or credentials.
const net = require('node:net');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
let mime = '', sender = '', recipient = '';
const server = net.createServer(socket => {
    socket.write('220 localhost test SMTP\r\n');
    let buffer = '', inData = false;
    socket.on('data', chunk => {
        buffer += chunk.toString();
        let index;
        while ((index = buffer.indexOf('\r\n')) >= 0) {
            const line = buffer.slice(0, index); buffer = buffer.slice(index + 2);
            if (inData) {
                if (line === '.') { inData = false; socket.write('250 accepted\r\n'); }
                else mime += line + '\r\n';
            } else if (/^EHLO/.test(line)) socket.write('250 localhost\r\n');
            else if (/^MAIL FROM:/.test(line)) { sender = line; socket.write('250 sender accepted\r\n'); }
            else if (/^RCPT TO:/.test(line)) { recipient = line; socket.write('250 recipient accepted\r\n'); }
            else if (line === 'DATA') { inData = true; socket.write('354 end with dot\r\n'); }
            else if (line === 'QUIT') socket.end('221 bye\r\n');
            else socket.write('250 OK\r\n');
        }
    });
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        const child = spawn('php', ['tests/Fixtures/password-reset-smtp.php', String(server.address().port)], { windowsHide: true });
        let output = ''; child.stdout.on('data', data => output += data); child.stderr.on('data', data => output += data);
        const timeout = setTimeout(() => child.kill(), 25000);
        const code = await new Promise((resolve, reject) => { child.once('exit', resolve); child.once('error', reject); });
        clearTimeout(timeout);
        assert.equal(code, 0, 'PHP SMTP client exits successfully');
        assert.ok(output.includes('SMTP delivery completed without mail-body logging.'), 'Mail does not use log transport');
        assert.ok(sender.includes('sender@example.test')); assert.ok(recipient.includes('recipient@example.test'));
        assert.ok(mime.includes('CompliTrack password reset')); assert.ok(mime.includes('isolated-smtp-fixture'));
        assert.ok(mime.includes('text/html') && mime.includes('text/plain'));
        console.log('PASS: PHPMailer SMTP delivery, recipient, reset link, HTML/plain-text MIME, and no log fallback');
    } finally { server.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
