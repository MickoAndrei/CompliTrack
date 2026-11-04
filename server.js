import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import bcrypt from 'bcryptjs';
import cookieParser from 'cookie-parser';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import { MongoClient, ObjectId } from 'mongodb';
import { createCipheriv, createDecipheriv, createHash, createHmac, randomBytes, timingSafeEqual } from 'crypto';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const app = express();
const PORT = 3000;
const HOST = '0.0.0.0';
app.set('trust proxy', 1);

function getJwtSecret() {
  const secret = process.env.JWT_SECRET;
  return typeof secret === 'string' && Buffer.byteLength(secret) >= 32
    ? secret
    : null;
}

function signAuthToken(user) {
  const secret = getJwtSecret();
  if (!secret) return null;

  const now = Math.floor(Date.now() / 1000);
  const encode = (value) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const unsignedToken = `${encode({ alg: 'HS256', typ: 'JWT' })}.${encode({
    sub: user._id,
    roleId: user.roleId,
    iat: now,
    exp: now + 7 * 24 * 60 * 60,
    iss: 'complitrack',
    aud: 'complitrack-mobile',
  })}`;
  const signature = createHmac('sha256', secret).update(unsignedToken).digest('base64url');
  return `${unsignedToken}.${signature}`;
}

function verifyAuthToken(token) {
  const secret = getJwtSecret();
  if (!secret || typeof token !== 'string') return null;

  const parts = token.split('.');
  if (parts.length !== 3) return null;
  const unsignedToken = `${parts[0]}.${parts[1]}`;
  const expected = createHmac('sha256', secret).update(unsignedToken).digest();
  let actual;
  try {
    actual = Buffer.from(parts[2], 'base64url');
  } catch {
    return null;
  }
  if (actual.length !== expected.length || !timingSafeEqual(actual, expected)) return null;

  try {
    const header = JSON.parse(Buffer.from(parts[0], 'base64url').toString('utf8'));
    const claims = JSON.parse(Buffer.from(parts[1], 'base64url').toString('utf8'));
    const now = Math.floor(Date.now() / 1000);
    if (header.alg !== 'HS256' || header.typ !== 'JWT'
      || claims.iss !== 'complitrack' || claims.aud !== 'complitrack-mobile'
      || typeof claims.sub !== 'string' || !Number.isInteger(claims.exp) || claims.exp <= now) {
      return null;
    }
    const user = users.find((candidate) => candidate._id === claims.sub && candidate.isActive);
    return user && user.roleId === claims.roleId ? user : null;
  } catch {
    return null;
  }
}

// In-memory file upload storage
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 25 * 1024 * 1024 } });

// ==========================================
// MONGODB-BACKED REQUEST STATE
// ==========================================

const departments = [];
const users = [];
const archives = [];
const complianceChecks = [];
const notifications = [];
const auditLogs = [];
let mongoDb = null;
let mongoClient = null;
const stateCollections = {
  users: 'users',
  departments: 'departments',
  complianceChecks: 'compliance_checks',
  archives: 'archive_documents',
  notifications: 'notifications',
  auditLogs: 'audit_logs',
};
const stateArrays = { users, departments, complianceChecks, archives, notifications, auditLogs };

function normalizeMongoValue(value) {
  if (value instanceof ObjectId) return value.toHexString();
  if (Array.isArray(value)) return value.map(normalizeMongoValue);
  if (value && typeof value === 'object' && value.constructor === Object) {
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, normalizeMongoValue(item)]));
  }
  return value;
}

function mongoId(value) {
  return typeof value === 'string' && ObjectId.isValid(value) ? new ObjectId(value) : value;
}

function mongoDocument(value) {
  if (Array.isArray(value)) return value.map(mongoDocument);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.entries(value).map(([key, item]) => {
    if ((key === '_id' || key.endsWith('Id')) && typeof item === 'string') {
      return [key, mongoId(item)];
    }
    if (/(?:Ids)$/.test(key) && Array.isArray(item)) return [key, item.map(mongoId)];
    return [key, mongoDocument(item)];
  }));
}

function archiveEncryptionKey() {
  const configured = process.env.ENCRYPTION_KEY;
  if (typeof configured !== 'string' || configured === '') {
    throw new Error('ENCRYPTION_KEY is required for archive uploads.');
  }
  const encoded = configured.startsWith('base64:') ? configured.slice(7) : configured;
  let key = Buffer.from(encoded, 'base64');
  if (key.length !== 32) key = Buffer.from(configured);
  if (key.length !== 32) throw new Error('ENCRYPTION_KEY must encode exactly 32 bytes.');
  return key;
}

function encryptArchive(plaintext) {
  const iv = randomBytes(12);
  const cipher = createCipheriv('aes-256-gcm', archiveEncryptionKey(), iv);
  const ciphertext = Buffer.concat([cipher.update(plaintext), cipher.final()]);
  return { ciphertext, iv: iv.toString('base64'), tag: cipher.getAuthTag().toString('base64') };
}

function decryptArchive(ciphertext, iv, tag) {
  const decodedIv = Buffer.from(iv || '', 'base64');
  const decodedTag = Buffer.from(tag || '', 'base64');
  if (decodedIv.length !== 12 || decodedTag.length !== 16) throw new Error('Archive encryption metadata is invalid.');
  const decipher = createDecipheriv('aes-256-gcm', archiveEncryptionKey(), decodedIv);
  decipher.setAuthTag(decodedTag);
  return Buffer.concat([decipher.update(ciphertext), decipher.final()]);
}

function archivePathFor(relativePath) {
  if (typeof relativePath !== 'string' || !/^archives\/[A-Za-z0-9._-]+\.enc$/.test(relativePath)) {
    throw new Error('Archive path is invalid.');
  }
  return path.join(__dirname, 'storage', 'app', 'private', relativePath);
}

async function connectMongo() {
  const uri = process.env.MONGODB_URI;
  if (!uri) throw new Error('MONGODB_URI is required; refusing to start with in-memory demo data.');
  mongoClient = new MongoClient(uri, { serverSelectionTimeoutMS: 15000 });
  await mongoClient.connect();
  mongoDb = mongoClient.db(process.env.MONGODB_DATABASE || 'complitrack');
  await mongoDb.command({ ping: 1 });
  console.log(`[CompliTrack] Connected to MongoDB database "${mongoDb.databaseName}".`);
}

async function reloadMongoState() {
  for (const [key, collectionName] of Object.entries(stateCollections)) {
    const rows = await mongoDb.collection(collectionName).find({}).toArray();
    stateArrays[key].length = 0;
    for (const row of rows) stateArrays[key].push(normalizeMongoValue(row));
  }
}

function snapshotMongoState() {
  return Object.fromEntries(Object.entries(stateArrays).map(([key, rows]) => [
    key,
    new Map(rows.map((row) => [String(row._id), JSON.stringify(row)])),
  ]));
}

async function persistMongoChanges(before) {
  for (const [key, collectionName] of Object.entries(stateCollections)) {
    const collection = mongoDb.collection(collectionName);
    const oldRows = before[key];
    const currentRows = new Map(stateArrays[key].map((row) => [String(row._id), row]));
    for (const [id, row] of currentRows) {
      if (oldRows.get(id) === JSON.stringify(row)) continue;
      await collection.replaceOne({ _id: mongoId(id) }, mongoDocument(row), { upsert: true });
    }
    const removedIds = [...oldRows.keys()].filter((id) => !currentRows.has(id)).map(mongoId);
    if (removedIds.length) await collection.deleteMany({ _id: { $in: removedIds } });
  }
}

const mongoReady = connectMongo();

function logActivity(action, user, entityType, entityId) {
  const newLog = {
    _id: new ObjectId().toHexString(),
    action,
    userName: user ? user.fullName : 'System',
    userId: user ? user._id : 'system',
    entityType,
    entityId,
    ipAddress: '127.0.0.1',
    createdAt: new Date().toISOString(),
  };
  auditLogs.unshift(newLog);
}

// ==========================================
// MIDDLEWARES
// ==========================================

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());

// Keep the legacy Express API backed by MongoDB on every request. Routes still
// use arrays internally, so refresh them from MongoDB before handling a request
// and flush changed documents before returning a JSON response.
let stateRequestQueue = Promise.resolve();
app.use(async (req, res, next) => {
  const previousRequest = stateRequestQueue;
  let unlockRequest;
  stateRequestQueue = new Promise((resolve) => { unlockRequest = resolve; });
  await previousRequest;

  let released = false;
  const release = () => {
    if (released) return;
    released = true;
    unlockRequest();
  };
  res.once('finish', release);
  res.once('close', release);

  try {
    await mongoReady;
    if (req.path !== '/up' && req.path !== '/sanctum/csrf-cookie' && req.path !== '/api/v1/version') {
      await reloadMongoState();
      const before = snapshotMongoState();
      const sendJson = res.json.bind(res);
      let persistenceStarted = false;
      res.json = (body) => {
        if (persistenceStarted) return res;
        persistenceStarted = true;
        persistMongoChanges(before)
          .then(() => sendJson(body))
          .catch((error) => {
            release();
            if (!res.headersSent) next(error);
          });
        return res;
      };
    }
    next();
  } catch (error) {
    release();
    next(error);
  }
});

// Session authentication resolver
function getCurrentUser(req) {
  const authorization = req.headers.authorization;
  if (typeof authorization === 'string' && authorization.startsWith('Bearer ')) {
    return verifyAuthToken(authorization.slice('Bearer '.length).trim());
  }
  const userId = req.cookies.complitrack_user_id || req.headers['x-user-id'];
  if (!userId) return null;
  return users.find((u) => u._id === userId && u.isActive) || null;
}

function requireAuth(req, res, next) {
  const user = getCurrentUser(req);
  if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
  req.user = user;
  next();
}

function requireRole(...allowedRoles) {
  return (req, res, next) => {
    const user = req.user || getCurrentUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (!allowedRoles.includes(user.roleId)) {
      return res.status(403).json({ message: 'Forbidden.' });
    }
    req.user = user;
    next();
  };
}

// ==========================================
// API ROUTES
// ==========================================

app.get('/up', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

app.get('/sanctum/csrf-cookie', (req, res) => {
  res.cookie('XSRF-TOKEN', 'mock-csrf-token', { path: '/' });
  res.status(204).end();
});

app.get('/api/v1/version', (req, res) => {
  res.json({ version: '1.0.1' });
});

// Auth
app.post('/api/v1/auth/login', (req, res) => {
  const { email, password } = req.body;
  const normalizedEmail = String(email || '').trim().toLowerCase();
  const user = users.find((u) => {
    const acceptedEmails = [u.email, ...(u.emailAliases || [])];
    return u.isActive && acceptedEmails.some((acceptedEmail) => acceptedEmail.toLowerCase() === normalizedEmail);
  });
  const storedPassword = user?.password;
  const passwordMatches = typeof password === 'string' && typeof storedPassword === 'string'
    && (storedPassword.startsWith('$2')
      ? bcrypt.compareSync(password, storedPassword)
      : password === storedPassword);
  if (!user || !passwordMatches) {
    return res.status(422).json({ message: 'These credentials do not match our records.' });
  }

  const token = signAuthToken(user);
  if (!token) {
    return res.status(503).json({ message: 'JWT_SECRET must be configured with at least 32 characters.' });
  }

  // Set session cookie
  res.cookie('complitrack_user_id', user._id, {
    path: '/',
    httpOnly: true,
    secure: req.secure,
    sameSite: req.secure ? 'none' : 'lax',
    maxAge: 86400000 * 7,
  });

  logActivity('User Signed In', user, 'User', user._id);

  res.json({
    token,
    user: {
      _id: user._id,
      userCode: user.userCode,
      fullName: user.fullName,
      email: user.email,
      roleId: user.roleId,
      roleSlug: user.roleSlug,
      departmentId: user.departmentId,
      officeName: user.officeName,
      avatarPath: user.avatarPath,
      isActive: user.isActive,
    },
  });
});

app.use('/api/v1', (req, res, next) => {
  if (req.path === '/auth/forgot-password') return next();
  return requireAuth(req, res, next);
});

app.get('/api/v1/auth/me', (req, res) => {
  const user = getCurrentUser(req);
  if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
  res.json({ data: user });
});

app.post('/api/v1/auth/logout', (req, res) => {
  const user = getCurrentUser(req);
  if (user) logActivity('User Signed Out', user, 'User', user._id);
  res.clearCookie('complitrack_user_id');
  res.json({ message: 'Successfully signed out.' });
});

app.post('/api/v1/auth/forgot-password', (req, res) => {
  res.json({ message: 'If this institutional email is registered, a password reset link has been sent.' });
});

app.post('/api/v1/auth/avatar', upload.single('avatar'), (req, res) => {
  const user = getCurrentUser(req);
  if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
  user.avatarPath = '/images/ustp-claveria-logo.jpg';
  logActivity('Profile Photo Updated', user, 'User', user._id);
  res.json({ avatarUrl: user.avatarPath });
});

// Directories
app.get('/api/v1/directory/departments', (req, res) => {
  res.json({ data: departments.filter((d) => d.isActive) });
});

app.get('/api/v1/directory/auditees', (req, res) => {
  const auditees = users.filter((u) => u.roleId === 3 && u.isActive);
  res.json({ data: auditees });
});

// Users
app.get('/api/v1/users', (req, res) => {
  const { page = 1, perPage = 10, search = '' } = req.query;
  let filtered = [...users];
  if (search) {
    const q = search.toLowerCase();
    filtered = filtered.filter((u) =>
      u.fullName.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q) ||
      u.userCode.toLowerCase().includes(q)
    );
  }
  const total = filtered.length;
  const p = Math.max(1, parseInt(page, 10) || 1);
  const limit = Math.max(1, parseInt(perPage, 10) || 10);
  const start = (p - 1) * limit;
  const paginated = filtered.slice(start, start + limit);

  res.json({
    data: paginated,
    meta: {
      total,
      currentPage: p,
      lastPage: Math.ceil(total / limit) || 1,
      perPage: limit,
    },
  });
});

app.post('/api/v1/users', (req, res) => {
  const currentUser = getCurrentUser(req);
  const { fullName, email, roleId, departmentId, officeName } = req.body;
  if (!fullName || !email) {
    return res.status(422).json({ message: 'Full name and email are required.' });
  }
  const prefix = { 0: 'SA', 1: 'ADM', 2: 'AUD', 3: 'ATE' }[Number(roleId)] || 'USR';
  const count = users.filter((u) => u.roleId === Number(roleId)).length + 1;
  const userCode = `${prefix}-${String(count).padStart(4, '0')}`;
  const newUser = {
    _id: new ObjectId().toHexString(),
    userCode,
    fullName,
    email,
    password: bcrypt.hashSync(randomBytes(32).toString('hex'), 12),
    roleId: Number(roleId),
    roleSlug: ['super_admin', 'admin', 'auditor', 'auditee'][Number(roleId)] || 'user',
    departmentId: departmentId || '',
    assignedDepartmentIds: departmentId ? [departmentId] : [],
    officeName: officeName || '',
    avatarPath: null,
    isActive: true,
    createdAt: new Date().toISOString(),
  };
  users.unshift(newUser);
  logActivity('User Created', currentUser, 'User', newUser._id);
  res.status(201).json({ data: newUser });
});

app.patch('/api/v1/users/:id', (req, res) => {
  const currentUser = getCurrentUser(req);
  const user = users.find((u) => u._id === req.params.id);
  if (!user) return res.status(404).json({ message: 'User not found.' });

  const { fullName, email, roleId, departmentId, officeName } = req.body;
  if (fullName !== undefined) user.fullName = fullName;
  if (email !== undefined) user.email = email;
  if (roleId !== undefined) user.roleId = Number(roleId);
  if (departmentId !== undefined) {
    user.departmentId = departmentId;
    if (user.roleId === 2) user.assignedDepartmentIds = [departmentId];
  }
  if (officeName !== undefined) user.officeName = officeName;

  logActivity('User Updated', currentUser, 'User', user._id);
  res.json({ data: user });
});

app.delete('/api/v1/users/:id', (req, res) => {
  const currentUser = getCurrentUser(req);
  const user = users.find((u) => u._id === req.params.id);
  if (!user) return res.status(404).json({ message: 'User not found.' });
  user.isActive = false;
  logActivity('User Deactivated', currentUser, 'User', user._id);
  res.json({ message: 'User deactivated.' });
});

app.post('/api/v1/users/:id/reactivate', (req, res) => {
  const currentUser = getCurrentUser(req);
  const user = users.find((u) => u._id === req.params.id);
  if (!user) return res.status(404).json({ message: 'User not found.' });
  user.isActive = true;
  logActivity('User Reactivated', currentUser, 'User', user._id);
  res.json({ message: 'User reactivated.' });
});

app.post('/api/v1/users/:id/reset-password', (req, res) => {
  const user = users.find((u) => u._id === req.params.id);
  if (!user) return res.status(404).json({ message: 'User not found.' });
  res.json({ message: `Password reset link sent to ${user.email}.` });
});

// Departments
app.get('/api/v1/departments', (req, res) => {
  const { page = 1, perPage = 10, search = '' } = req.query;
  let filtered = [...departments];
  if (search) {
    const q = search.toLowerCase();
    filtered = filtered.filter((d) =>
      d.departmentName.toLowerCase().includes(q) ||
      d.departmentCode.toLowerCase().includes(q)
    );
  }
  const total = filtered.length;
  const p = Math.max(1, parseInt(page, 10) || 1);
  const limit = Math.max(1, parseInt(perPage, 10) || 10);
  const start = (p - 1) * limit;
  const paginated = filtered.slice(start, start + limit);

  res.json({
    data: paginated,
    meta: {
      total,
      currentPage: p,
      lastPage: Math.ceil(total / limit) || 1,
      perPage: limit,
    },
  });
});

app.post('/api/v1/departments', (req, res) => {
  const currentUser = getCurrentUser(req);
  const { departmentCode, departmentName, collegeName = 'College of Engineering and Technology', campusName = 'Claveria' } = req.body;
  if (!departmentCode || !departmentName) {
    return res.status(422).json({ message: 'Code and department name are required.' });
  }
  const newDept = {
    _id: new ObjectId().toHexString(),
    departmentCode: departmentCode.toUpperCase(),
    departmentName,
    collegeName,
    campusName,
    headUserId: null,
    isActive: true,
    createdAt: new Date().toISOString(),
  };
  departments.unshift(newDept);
  logActivity('Department Created', currentUser, 'Department', newDept._id);
  res.status(201).json({ data: newDept });
});

app.patch('/api/v1/departments/:id', (req, res) => {
  const currentUser = getCurrentUser(req);
  const dept = departments.find((d) => d._id === req.params.id);
  if (!dept) return res.status(404).json({ message: 'Department not found.' });

  const { departmentName, collegeName, campusName } = req.body;
  if (departmentName) dept.departmentName = departmentName;
  if (collegeName) dept.collegeName = collegeName;
  if (campusName) dept.campusName = campusName;

  logActivity('Department Updated', currentUser, 'Department', dept._id);
  res.json({ data: dept });
});

app.delete('/api/v1/departments/:id', (req, res) => {
  const currentUser = getCurrentUser(req);
  const dept = departments.find((d) => d._id === req.params.id);
  if (!dept) return res.status(404).json({ message: 'Department not found.' });
  dept.isActive = false;
  logActivity('Department Deactivated', currentUser, 'Department', dept._id);
  res.json({ message: 'Department deactivated.' });
});

app.post('/api/v1/departments/:id/reactivate', (req, res) => {
  const currentUser = getCurrentUser(req);
  const dept = departments.find((d) => d._id === req.params.id);
  if (!dept) return res.status(404).json({ message: 'Department not found.' });
  dept.isActive = true;
  logActivity('Department Reactivated', currentUser, 'Department', dept._id);
  res.json({ message: 'Department reactivated.' });
});

// Compliance Checks
app.get('/api/v1/compliance-checks', (req, res) => {
  const { page = 1, perPage = 10, search = '', result, correctiveStatus, overdue } = req.query;
  const user = getCurrentUser(req);

  let filtered = [...complianceChecks];

  // Role scoping
  if (user && user.roleId === 3) {
    filtered = filtered.filter((c) => c.auditeeId === user._id || c.departmentId === user.departmentId);
  } else if (user && user.roleId === 2) {
    const assigned = user.assignedDepartmentIds || (user.departmentId ? [user.departmentId] : []);
    if (assigned.length > 0) {
      filtered = filtered.filter((c) => assigned.includes(c.departmentId) || c.auditorId === user._id);
    }
  }

  if (result) {
    filtered = filtered.filter((c) => c.result === result);
  }
  if (correctiveStatus) {
    filtered = filtered.filter((c) => c.correctiveStatus === correctiveStatus);
  }
  if (overdue === 'true' || overdue === true) {
    const now = new Date();
    filtered = filtered.filter((c) => c.dueDate && new Date(c.dueDate) < now && c.correctiveStatus !== 'closed');
  }

  if (search) {
    const q = search.toLowerCase();
    filtered = filtered.filter((c) =>
      c.checkCode.toLowerCase().includes(q) ||
      (c.departmentName || '').toLowerCase().includes(q) ||
      (c.auditeeName || '').toLowerCase().includes(q) ||
      (c.remarks || '').toLowerCase().includes(q)
    );
  }

  const total = filtered.length;
  const p = Math.max(1, parseInt(page, 10) || 1);
  const limit = Math.max(1, parseInt(perPage, 10) || 10);
  const start = (p - 1) * limit;
  const paginated = filtered.slice(start, start + limit);

  res.json({
    data: paginated,
    meta: {
      total,
      currentPage: p,
      lastPage: Math.ceil(total / limit) || 1,
      perPage: limit,
    },
  });
});

app.post('/api/v1/compliance-checks', (req, res) => {
  const currentUser = getCurrentUser(req);
  const { departmentId, auditeeId, referenceStandard, result, remarks } = req.body;

  const dept = departments.find((d) => d._id === departmentId);
  const auditee = users.find((u) => u._id === auditeeId);
  const auditor = currentUser?.roleId === 2
    ? currentUser
    : users.find((u) => Number(u.roleId) === 2 && (u.departmentId === departmentId || (u.assignedDepartmentIds || []).includes(departmentId)));
  if (!dept || !auditee || !auditor) {
    return res.status(422).json({ message: 'Select an existing department, auditee, and auditor.' });
  }

  const deptCode = dept.departmentCode;
  const code = `CHK-${deptCode}-${new Date().getUTCFullYear()}-${String(complianceChecks.length + 1).padStart(3, '0')}`;
  const status = result === 'non_compliant' ? 'open' : null;

  const newCheck = {
    _id: new ObjectId().toHexString(),
    checkCode: code,
    departmentId,
    departmentName: dept.departmentName,
    auditorId: auditor._id,
    auditorName: auditor.fullName,
    auditeeId,
    auditeeName: auditee.fullName,
    referenceStandard: referenceStandard || 'ARTA-3days',
    result: result || 'compliant',
    remarks: remarks || '',
    correctiveStatus: status,
    statusHistory: status ? [
      {
        status: 'open',
        changedByUserId: auditor._id,
        remarks: remarks || 'Initial non-compliance recorded.',
        timestamp: new Date().toISOString(),
      },
    ] : [],
    linkedArchiveIds: [],
    dueDate: status ? new Date(Date.now() + 86400000 * 7).toISOString() : null,
    closedAt: null,
    createdAt: new Date().toISOString(),
  };

  complianceChecks.unshift(newCheck);
  logActivity('Compliance Check Created', currentUser, 'ComplianceCheck', newCheck._id);

  if (auditee) {
    notifications.unshift({
      _id: new ObjectId().toHexString(),
      recipientUserId: auditee._id,
      senderUserId: currentUser?._id || auditor._id,
      type: status ? 'corrective_action_opened' : 'compliance_check_completed',
      title: status ? 'Corrective action required' : 'Compliance check passed',
      message: status
        ? `A non-compliant check (${code}) was opened. Please review the findings.`
        : `Compliance check (${code}) passed successfully.`,
      relatedCheckId: newCheck._id,
      isRead: false,
      createdAt: new Date().toISOString(),
    });
  }

  res.status(201).json({ data: newCheck });
});

app.get('/api/v1/compliance-checks/:id', (req, res) => {
  const check = complianceChecks.find((c) => c._id === req.params.id);
  if (!check) return res.status(404).json({ message: 'Compliance check not found.' });
  res.json({ data: check });
});

app.patch('/api/v1/compliance-checks/:id/status', (req, res) => {
  const currentUser = getCurrentUser(req);
  const check = complianceChecks.find((c) => c._id === req.params.id);
  if (!check) return res.status(404).json({ message: 'Compliance check not found.' });

  const { correctiveStatus, remarks } = req.body;
  check.correctiveStatus = correctiveStatus;
  if (!check.statusHistory) check.statusHistory = [];
  check.statusHistory.push({
    status: correctiveStatus,
    changedByUserId: currentUser ? currentUser._id : 'system',
    remarks: remarks || `Status changed to ${correctiveStatus}.`,
    timestamp: new Date().toISOString(),
  });

  if (correctiveStatus === 'closed') {
    check.closedAt = new Date().toISOString();
  }

  logActivity(`Status Updated to ${correctiveStatus}`, currentUser, 'ComplianceCheck', check._id);

  // Notify auditee
  if (check.auditeeId) {
    notifications.unshift({
      _id: new ObjectId().toHexString(),
      recipientUserId: check.auditeeId,
      senderUserId: currentUser ? currentUser._id : 'system',
      type: `corrective_action_${correctiveStatus}`,
      title: `Corrective action ${correctiveStatus.replace('_', ' ')}`,
      message: `Status of ${check.checkCode} was updated to ${correctiveStatus.replace('_', ' ')}.`,
      relatedCheckId: check._id,
      isRead: false,
      createdAt: new Date().toISOString(),
    });
  }

  res.json({ data: check });
});

// Archives
app.get('/api/v1/archives', (req, res) => {
  const { page = 1, perPage = 10, search = '' } = req.query;
  let filtered = [...archives];
  if (search) {
    const q = search.toLowerCase();
    filtered = filtered.filter((a) =>
      a.originalName.toLowerCase().includes(q) ||
      a.fileCode.toLowerCase().includes(q) ||
      (a.referenceStandard || '').toLowerCase().includes(q)
    );
  }
  const total = filtered.length;
  const p = Math.max(1, parseInt(page, 10) || 1);
  const limit = Math.max(1, parseInt(perPage, 10) || 10);
  const start = (p - 1) * limit;
  const paginated = filtered.slice(start, start + limit);

  res.json({
    data: paginated,
    meta: {
      total,
      currentPage: p,
      lastPage: Math.ceil(total / limit) || 1,
      perPage: limit,
    },
  });
});

app.post('/api/v1/archives/upload', upload.single('file'), (req, res) => {
  const currentUser = getCurrentUser(req);
  const { checkId } = req.body;
  const check = complianceChecks.find((c) => c._id === checkId);
  const file = req.file;
  if (!currentUser) return res.status(401).json({ message: 'Unauthenticated.' });
  if (!file) return res.status(422).json({ message: 'Choose an evidence file to upload.' });
  if (!check) return res.status(404).json({ message: 'Compliance check not found.' });
  const dept = departments.find((d) => d._id === check.departmentId);
  if (!dept) return res.status(409).json({ message: 'The check department no longer exists.' });
  const fileName = path.basename(file.originalname);
  const year = new Date().getUTCFullYear();
  const fileCode = `ARCH-${year}-${String(archives.length + 1).padStart(3, '0')}`;
  const relativePath = `archives/${fileCode}.enc`;
  const absolutePath = archivePathFor(relativePath);
  let encrypted;
  try {
    encrypted = encryptArchive(file.buffer);
    fs.mkdirSync(path.dirname(absolutePath), { recursive: true });
    fs.writeFileSync(absolutePath, encrypted.ciphertext, { flag: 'wx' });
  } catch (error) {
    return res.status(500).json({ message: `Archive could not be stored: ${error.message}` });
  }

  const newArchive = {
    _id: new ObjectId().toHexString(),
    fileCode,
    originalName: fileName,
    encryptedPath: relativePath,
    departmentId: dept._id,
    departmentName: dept.departmentName,
    referenceStandard: check.referenceStandard,
    fileSize: file.size,
    mimeType: file.mimetype,
    uploadedByUserId: currentUser._id,
    uploaderUserId: currentUser._id,
    uploaderName: currentUser.fullName,
    isEncrypted: true,
    encryptionIv: encrypted.iv,
    encryptionTag: encrypted.tag,
    checksum: createHash('sha256').update(file.buffer).digest('hex'),
    createdAt: new Date().toISOString(),
  };

  archives.unshift(newArchive);

  if (check) {
    if (!check.linkedArchiveIds) check.linkedArchiveIds = [];
    check.linkedArchiveIds.push(newArchive._id);
    if (check.correctiveStatus === 'open') {
      check.correctiveStatus = 'in_progress';
      check.statusHistory.push({
        status: 'in_progress',
        changedByUserId: currentUser._id,
        remarks: `Evidence uploaded: ${fileName}`,
        timestamp: new Date().toISOString(),
      });
    }
    // Notify auditor
    if (check.auditorId) {
      notifications.unshift({
        _id: new ObjectId().toHexString(),
        recipientUserId: check.auditorId,
        senderUserId: currentUser._id,
        type: 'evidence_uploaded',
        title: 'Evidence ready for review',
        message: `New evidence was attached to check ${check.checkCode}.`,
        relatedCheckId: check._id,
        isRead: false,
        createdAt: new Date().toISOString(),
      });
    }
  }

  logActivity('Evidence Uploaded', currentUser, 'ArchiveDocument', newArchive._id);
  res.status(201).json({ data: newArchive });
});

app.get('/api/v1/archives/:id/download', (req, res) => {
  const archive = archives.find((a) => a._id === req.params.id);
  if (!archive) return res.status(404).json({ message: 'Archive record not found.' });
  let absolutePath;
  try {
    absolutePath = archivePathFor(archive.encryptedPath);
  } catch {
    return res.status(404).json({ message: 'The encrypted archive file is missing.' });
  }
  if (!fs.existsSync(absolutePath)) {
    return res.status(404).json({ message: 'The encrypted archive file is missing.' });
  }
  try {
    const contents = decryptArchive(fs.readFileSync(absolutePath), archive.encryptionIv, archive.encryptionTag);
    const checksum = createHash('sha256').update(contents).digest('hex');
    if (checksum !== archive.checksum) return res.status(500).json({ message: 'Archive checksum verification failed.' });
    res.setHeader('Content-Disposition', `attachment; filename="${path.basename(archive.originalName)}"`);
    res.setHeader('Content-Type', archive.mimeType || 'application/octet-stream');
    return res.send(contents);
  } catch (error) {
    return res.status(500).json({ message: `Archive could not be decrypted: ${error.message}` });
  }
});

app.get('/api/v1/archives/:id/verify-checksum', (req, res) => {
  const archive = archives.find((a) => a._id === req.params.id);
  if (!archive) return res.status(404).json({ message: 'Archive record not found.' });
  let absolutePath;
  try {
    absolutePath = archivePathFor(archive.encryptedPath);
  } catch {
    return res.status(404).json({ message: 'The encrypted archive file is missing.' });
  }
  if (!fs.existsSync(absolutePath)) {
    return res.status(404).json({ message: 'The encrypted archive file is missing.' });
  }
  let verified = false;
  try {
    const contents = decryptArchive(fs.readFileSync(absolutePath), archive.encryptionIv, archive.encryptionTag);
    verified = createHash('sha256').update(contents).digest('hex') === archive.checksum;
  } catch {
    verified = false;
  }
  res.json({
    verified,
    fileCode: archive.fileCode,
    checksum: archive.checksum,
    algorithm: 'SHA-256',
    encryption: 'AES-256-GCM',
  });
});

// Notifications
app.get('/api/v1/notifications', (req, res) => {
  const user = getCurrentUser(req);
  const { perPage = 10 } = req.query;
  const userNotifs = user ? notifications.filter((n) => n.recipientUserId === user._id) : notifications;
  const limit = Math.max(1, parseInt(perPage, 10) || 10);
  res.json({
    data: userNotifs.slice(0, limit),
    meta: { total: userNotifs.length },
  });
});

app.patch('/api/v1/notifications/read-all', (req, res) => {
  const user = getCurrentUser(req);
  if (user) {
    notifications.forEach((n) => {
      if (n.recipientUserId === user._id) n.isRead = true;
    });
  }
  res.json({ message: 'All notifications marked as read.' });
});

app.patch('/api/v1/notifications/:id/read', (req, res) => {
  const notif = notifications.find((n) => n._id === req.params.id);
  if (notif) notif.isRead = true;
  res.json({ message: 'Notification marked as read.' });
});

app.post('/api/v1/notifications/broadcast', (req, res) => {
  const currentUser = getCurrentUser(req);
  const { departmentId, message } = req.body;
  const dept = departments.find((d) => d._id === departmentId);
  const targetAuditees = users.filter((u) => u.roleId === 3 && u.departmentId === departmentId && u.isActive);

  targetAuditees.forEach((auditee) => {
    notifications.unshift({
      _id: new ObjectId().toHexString(),
      recipientUserId: auditee._id,
      senderUserId: currentUser ? currentUser._id : 'admin',
      type: 'broadcast_announcement',
      title: 'CET Department Announcement',
      message: message || 'Notice from College Administration.',
      relatedCheckId: null,
      isRead: false,
      createdAt: new Date().toISOString(),
    });
  });

  logActivity('Broadcast Notification Sent', currentUser, 'Notification', departmentId);
  res.json({
    recipientCount: targetAuditees.length,
    departmentName: dept ? dept.departmentName : 'Selected Department',
  });
});

// Logs (Super Admin only)
app.get('/api/v1/logs', (req, res) => {
  const { page = 1, perPage = 10, search = '', sortBy = 'createdAt', sortDirection = 'desc' } = req.query;
  let filtered = [...auditLogs];
  if (search) {
    const q = search.toLowerCase();
    filtered = filtered.filter((l) =>
      l.action.toLowerCase().includes(q) ||
      l.userName.toLowerCase().includes(q) ||
      l.entityType.toLowerCase().includes(q)
    );
  }

  filtered.sort((a, b) => {
    const va = a[sortBy] || '';
    const vb = b[sortBy] || '';
    if (sortDirection === 'asc') return va > vb ? 1 : -1;
    return va < vb ? 1 : -1;
  });

  const total = filtered.length;
  const p = Math.max(1, parseInt(page, 10) || 1);
  const limit = Math.max(1, parseInt(perPage, 10) || 10);
  const start = (p - 1) * limit;
  const paginated = filtered.slice(start, start + limit);

  res.json({
    data: paginated,
    meta: {
      total,
      currentPage: p,
      lastPage: Math.ceil(total / limit) || 1,
      perPage: limit,
    },
  });
});

// Reports & Analytics
app.get('/api/v1/reports', async (req, res) => {
  const mongoStats = await mongoDb.command({ dbStats: 1 });
  const totalChecks = complianceChecks.length;
  const compliantChecks = complianceChecks.filter((c) => c.result === 'compliant').length;
  const openCars = complianceChecks.filter((c) => c.correctiveStatus === 'open').length;
  const inProgressCars = complianceChecks.filter((c) => c.correctiveStatus === 'in_progress').length;
  const closedCars = complianceChecks.filter((c) => c.correctiveStatus === 'closed').length;
  const complianceRate = totalChecks ? Math.round((compliantChecks / totalChecks) * 100) : 0;

  const totalUsers = users.length;
  const activeUsers = users.filter((user) => user.isActive).length;
  const totalAuditors = users.filter((u) => u.roleId === 2).length;
  const totalAuditees = users.filter((u) => u.roleId === 3).length;
  const totalDepts = departments.filter((d) => d.isActive).length;

  const deptCompliance = departments.map((d) => {
    const deptChecks = complianceChecks.filter((c) => c.departmentId === d._id);
    const pass = deptChecks.filter((c) => c.result === 'compliant').length;
    const pct = deptChecks.length ? Math.round((pass / deptChecks.length) * 100) : 100;
    return {
      departmentCode: d.departmentCode,
      departmentName: d.departmentName,
      totalChecks: deptChecks.length,
      compliancePercentage: pct,
    };
  });

  const monthStart = new Date();
  monthStart.setUTCDate(1);
  monthStart.setUTCHours(0, 0, 0, 0);
  monthStart.setUTCMonth(monthStart.getUTCMonth() - 5);
  const monthlyComplianceTrend = Array.from({ length: 6 }, (_, index) => {
    const start = new Date(monthStart);
    start.setUTCMonth(start.getUTCMonth() + index);
    const end = new Date(start);
    end.setUTCMonth(end.getUTCMonth() + 1);
    const monthChecks = complianceChecks.filter((check) => {
      const createdAt = new Date(check.createdAt);
      return !Number.isNaN(createdAt.getTime()) && createdAt >= start && createdAt < end;
    });
    const compliant = monthChecks.filter((check) => check.result === 'compliant').length;
    return {
      month: start.toLocaleString('en', { month: 'short', timeZone: 'UTC' }),
      compliancePercentage: monthChecks.length ? Math.round((compliant / monthChecks.length) * 100) : 0,
    };
  });
  const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
  const openOverSevenDays = complianceChecks.filter((check) => (
    check.correctiveStatus === 'open'
    && new Date(check.createdAt).getTime() < sevenDaysAgo
  )).length;

  res.json({
    data: {
      totalUsers,
      totalAuditors,
      totalAuditees,
      totalDepts,
      storageUsedGb: Number((((mongoStats.storageSize || 0) + (mongoStats.indexSize || 0)) / (1024 ** 3)).toFixed(2)),
      archiveFileSizeGb: Number((archives.reduce((total, archive) => total + (Number(archive.fileSize) || 0), 0) / (1024 ** 3)).toFixed(2)),
      databaseStats: {
        database: mongoDb.databaseName,
        dataSizeBytes: mongoStats.dataSize || 0,
        storageSizeBytes: mongoStats.storageSize || 0,
        indexSizeBytes: mongoStats.indexSize || 0,
        collections: mongoStats.collections || 0,
        objects: mongoStats.objects || 0,
      },
      activeUsers,
      activeAudits: openCars + inProgressCars,
      totalChecks,
      compliantChecks,
      openCars,
      inProgressCars,
      closedCars,
      complianceRate,
      openOverSevenDays,
      deptCompliance,
      monthlyComplianceTrend,
    },
  });
});

app.get('/api/v1/system/database-stats', requireRole(0), async (req, res) => {
  const [databaseStats, usersStats] = await Promise.all([
    mongoDb.command({ dbStats: 1 }),
    mongoDb.command({ collStats: 'users' }),
  ]);
  res.json({
    data: {
      database: mongoDb.databaseName,
      databaseStats: {
        collections: databaseStats.collections || 0,
        objects: databaseStats.objects || 0,
        dataSizeBytes: databaseStats.dataSize || 0,
        storageSizeBytes: databaseStats.storageSize || 0,
        indexSizeBytes: databaseStats.indexSize || 0,
      },
      users: {
        total: users.length,
        active: users.filter((user) => user.isActive).length,
        dataSizeBytes: usersStats.size || 0,
        storageSizeBytes: usersStats.storageSize || 0,
        indexSizeBytes: usersStats.totalIndexSize || 0,
      },
    },
  });
});

app.use((req, res) => res.status(404).json({ message: 'API route not found.' }));

// ==========================================
// START SERVER
// ==========================================

mongoReady
  .then(() => {
    app.listen(PORT, HOST, () => {
      console.log(`[CompliTrack] Server running on http://${HOST}:${PORT}`);
    });
  })
  .catch(async (error) => {
    console.error(`[CompliTrack] MongoDB is required; server startup aborted: ${error.message}`);
    if (mongoClient) await mongoClient.close().catch(() => {});
    process.exitCode = 1;
  });
