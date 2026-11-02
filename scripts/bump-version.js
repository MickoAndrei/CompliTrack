import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const packagePath = path.join(root, 'package.json');
const packageLockPath = path.join(root, 'package-lock.json');
const realtimePackagePath = path.join(root, 'realtime', 'package.json');
const realtimeLockPath = path.join(root, 'realtime', 'package-lock.json');
const pubspecPath = path.join(root, 'mobile', 'pubspec.yaml');

function readJson(filePath) {
  const contents = fs.readFileSync(filePath, 'utf8');
  return { contents, value: JSON.parse(contents) };
}

const rootPackage = readJson(packagePath);
const version = rootPackage.value.version;
const match = /^(\d+)\.(\d+)\.(\d+)$/.exec(version ?? '');
if (!match) {
  throw new Error(`Expected package.json version to be semantic version X.Y.Z; received "${version}".`);
}

const [, majorText, minorText, patchText] = match;
const major = Number(majorText);
const minor = Number(minorText);
const patch = Number(patchText);
const commitMessage = process.env.VERSION_COMMIT_MESSAGE ?? '';
const bump = /#major\b/i.test(commitMessage)
  ? 'major'
  : /#minor\b/i.test(commitMessage)
    ? 'minor'
    : 'patch';

let nextVersion;
if (bump === 'major') {
  nextVersion = `${major + 1}.0.0`;
} else if (bump === 'minor') {
  nextVersion = `${major}.${minor + 1}.0`;
} else {
  nextVersion = `${major}.${minor}.${patch + 1}`;
}

const runNumber = Number(process.env.GITHUB_RUN_NUMBER);
if (!Number.isSafeInteger(runNumber) || runNumber < 1) {
  throw new Error('GITHUB_RUN_NUMBER must be a positive integer.');
}

const files = [packagePath, packageLockPath, realtimePackagePath, realtimeLockPath]
  .map((filePath) => ({ filePath, ...readJson(filePath) }));

const pubspec = fs.readFileSync(pubspecPath, 'utf8');
const pubspecMatch = /^version:\s*(\d+\.\d+\.\d+)\+(\d+)\s*$/m.exec(pubspec);
if (!pubspecMatch) {
  throw new Error('Expected mobile/pubspec.yaml to contain a version in X.Y.Z+BUILD format.');
}

const buildNumber = Math.max(runNumber, Number(pubspecMatch[2]) + 1);
const newPubspec = pubspec.replace(
  /^version:\s*\d+\.\d+\.\d+\+\d+\s*$/m,
  `version: ${nextVersion}+${buildNumber}`,
);

files[0].value.version = nextVersion;
files[1].value.version = nextVersion;
files[1].value.packages[''].version = nextVersion;
files[2].value.version = nextVersion;
files[3].value.version = nextVersion;
files[3].value.packages[''].version = nextVersion;

for (const { filePath, contents, value } of files) {
  const indent = contents.match(/\n([ \t]+)"/)?.[1] ?? '  ';
  const newline = contents.includes('\r\n') ? '\r\n' : '\n';
  const formatted = JSON.stringify(value, null, indent).replace(/\n/g, newline);
  fs.writeFileSync(filePath, `${formatted}${newline}`);
}
fs.writeFileSync(pubspecPath, newPubspec);

console.log(`Updated app version to ${nextVersion}+${buildNumber} (${bump}).`);
