import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const sourceRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

for (const testCase of [
  { message: 'release #patch', expected: '1.0.1' },
  { message: 'release #minor', expected: '1.1.0' },
  { message: 'release #major', expected: '2.0.0' },
  { message: 'release without a marker', expected: '1.0.1' },
]) {
  test(`bumps versions for ${testCase.message}`, (context) => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'complitrack-version-'));
    context.after(() => fs.rmSync(root, { recursive: true, force: true }));

    fs.mkdirSync(path.join(root, 'scripts'));
    fs.mkdirSync(path.join(root, 'realtime'));
    fs.mkdirSync(path.join(root, 'mobile'));
    fs.copyFileSync(
      path.join(sourceRoot, 'scripts', 'bump-version.js'),
      path.join(root, 'scripts', 'bump-version.js'),
    );
    fs.writeFileSync(
      path.join(root, 'package.json'),
      JSON.stringify({ type: 'module', version: '1.0.0' }),
    );
    fs.writeFileSync(
      path.join(root, 'package-lock.json'),
      JSON.stringify({ packages: { '': { version: '1.0.0' } } }),
    );
    fs.writeFileSync(
      path.join(root, 'realtime', 'package.json'),
      JSON.stringify({ version: '1.0.0' }),
    );
    fs.writeFileSync(
      path.join(root, 'realtime', 'package-lock.json'),
      JSON.stringify({ packages: { '': { version: '1.0.0' } } }),
    );
    fs.writeFileSync(
      path.join(root, 'mobile', 'pubspec.yaml'),
      'name: complitrack\nversion: 1.0.0+1\n',
    );

    const result = spawnSync(
      process.execPath,
      [path.join(root, 'scripts', 'bump-version.js')],
      {
        encoding: 'utf8',
        env: {
          ...process.env,
          GITHUB_RUN_NUMBER: '12',
          VERSION_COMMIT_MESSAGE: testCase.message,
        },
      },
    );

    assert.equal(result.status, 0, result.stderr);
    for (const file of [
      'package.json',
      'package-lock.json',
      'realtime/package.json',
      'realtime/package-lock.json',
    ]) {
      const contents = JSON.parse(fs.readFileSync(path.join(root, file), 'utf8'));
      assert.equal(contents.version, testCase.expected);
      if (file.endsWith('package-lock.json')) {
        assert.equal(contents.packages[''].version, testCase.expected);
      }
    }
    assert.match(
      fs.readFileSync(path.join(root, 'mobile', 'pubspec.yaml'), 'utf8'),
      new RegExp(`^version: ${testCase.expected}\\+12$`, 'm'),
    );
  });
}
