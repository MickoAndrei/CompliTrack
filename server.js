import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import bcrypt from 'bcryptjs';
import cookieParser from 'cookie-parser';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import { MongoClient } from 'mongodb';
import { createHmac, timingSafeEqual } from 'crypto';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

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

function viteAssetTag(entry) {
  try {
    const manifestPath = path.join(__dirname, 'public', 'build', 'manifest.json');
    const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    const asset = manifest[entry];
    if (!asset?.file) return '';

    const files = [...(asset.css || []), asset.file];
    return files.map((file) => file.endsWith('.css')
      ? `<link rel="stylesheet" href="/build/${file}">`
      : `<script type="module" src="/build/${file}"></script>`
    ).join('\n  ');
  } catch (error) {
    console.warn('[CompliTrack] Vite manifest unavailable. Run the production asset build.');
    return '';
  }
}

// In-memory file upload storage
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 25 * 1024 * 1024 } });

// ==========================================
// IN-MEMORY DATA STORE
// ==========================================

const departments = [
  {
    _id: 'dept-1',
    departmentCode: 'IT',
    departmentName: 'Information Technology',
    collegeName: 'College of Engineering and Technology',
    campusName: 'Claveria',
    headUserId: 'user-3',
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 30).toISOString(),
  },
  {
    _id: 'dept-2',
    departmentCode: 'ENG',
    departmentName: 'Civil & Mechanical Engineering',
    collegeName: 'College of Engineering and Technology',
    campusName: 'Claveria',
    headUserId: 'user-5',
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 30).toISOString(),
  },
  {
    _id: 'dept-3',
    departmentCode: 'ECE',
    departmentName: 'Electronics Engineering',
    collegeName: 'College of Engineering and Technology',
    campusName: 'Claveria',
    headUserId: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 25).toISOString(),
  },
  {
    _id: 'dept-4',
    departmentCode: 'ARCH',
    departmentName: 'Architecture & Environmental Design',
    collegeName: 'College of Engineering and Technology',
    campusName: 'Claveria',
    headUserId: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 20).toISOString(),
  },
];

const users = [
  {
    _id: 'user-0',
    userCode: 'SA-0001',
    fullName: 'Super Administrator',
    email: 'admin@example.edu',
    password: 'password123',
    roleId: 0,
    roleSlug: 'super_admin',
    departmentId: '',
    officeName: 'Central Administration',
    avatarPath: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 60).toISOString(),
  },
  {
    _id: 'user-1',
    userCode: 'ADM-0001',
    fullName: 'Engr. Maria Santos (CET Admin)',
    email: 'demo.admin@example.test',
    password: 'password123',
    roleId: 1,
    roleSlug: 'admin',
    departmentId: 'dept-1',
    officeName: 'CET Dean Office',
    avatarPath: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 45).toISOString(),
  },
  {
    _id: 'user-2',
    userCode: 'AUD-0001',
    fullName: 'Prof. Alex Reyes (IT Auditor)',
    email: 'demo.auditor.it@example.test',
    password: 'password123',
    roleId: 2,
    roleSlug: 'auditor',
    departmentId: 'dept-1',
    assignedDepartmentIds: ['dept-1'],
    officeName: 'Quality Assurance Office',
    avatarPath: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 40).toISOString(),
  },
  {
    _id: 'user-3',
    userCode: 'ATE-0001',
    fullName: 'Dr. Roberto Cruz (IT Dept Head)',
    email: 'demo.auditee.it@example.test',
    password: 'password123',
    roleId: 3,
    roleSlug: 'auditee',
    departmentId: 'dept-1',
    assignedDepartmentIds: [],
    officeName: 'IT Department Office',
    avatarPath: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 35).toISOString(),
  },
  {
    _id: 'user-4',
    userCode: 'AUD-0002',
    fullName: 'Engr. Carlos Dalisay (Eng Auditor)',
    email: 'demo.auditor.eng@example.test',
    password: 'password123',
    roleId: 2,
    roleSlug: 'auditor',
    departmentId: 'dept-2',
    assignedDepartmentIds: ['dept-2'],
    officeName: 'Quality Assurance Office',
    avatarPath: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 30).toISOString(),
  },
  {
    _id: 'user-5',
    userCode: 'ATE-0002',
    fullName: 'Engr. Elena Bautista (Eng Dept Head)',
    email: 'demo.auditee.eng@example.test',
    password: 'password123',
    roleId: 3,
    roleSlug: 'auditee',
    departmentId: 'dept-2',
    assignedDepartmentIds: [],
    officeName: 'Engineering Department Office',
    avatarPath: null,
    isActive: true,
    createdAt: new Date(Date.now() - 86400000 * 25).toISOString(),
  },
];

const superAdmin = users.find((user) => user.roleId === 0);
if (superAdmin) {
  const defaultEmail = superAdmin.email;
  superAdmin.email = process.env.SUPER_ADMIN_EMAIL || defaultEmail;
  superAdmin.emailAliases = [...new Set([...(superAdmin.emailAliases || []), defaultEmail])];
  superAdmin.password = process.env.SUPER_ADMIN_PASSWORD || superAdmin.password;
}

const archives = [
  {
    _id: 'arch-1',
    fileCode: 'ARCH-2026-001',
    originalName: 'IT_StudentClearance_Turnaround_2026.pdf',
    departmentId: 'dept-1',
    departmentName: 'Information Technology',
    referenceStandard: 'ARTA-3days',
    fileSize: 1048576,
    mimeType: 'application/pdf',
    encrypted: true,
    encryptionIv: '0123456789abcdef01234567',
    checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    uploaderUserId: 'user-3',
    uploaderName: 'Dr. Roberto Cruz',
    createdAt: new Date(Date.now() - 86400000 * 5).toISOString(),
  },
  {
    _id: 'arch-2',
    fileCode: 'ARCH-2026-002',
    originalName: 'ENG_FacultyConsultation_Log_Q1.docx',
    departmentId: 'dept-2',
    departmentName: 'Civil & Mechanical Engineering',
    referenceStandard: 'ARTA-7days',
    fileSize: 524288,
    mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    encrypted: true,
    encryptionIv: 'abcdef0123456789abcdef01',
    checksum: 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e',
    uploaderUserId: 'user-5',
    uploaderName: 'Engr. Elena Bautista',
    createdAt: new Date(Date.now() - 86400000 * 2).toISOString(),
  },
  {
    _id: 'arch-3',
    fileCode: 'ARCH-2026-003',
    originalName: 'ENG_CurriculumEvaluation_ISO9001.pdf',
    departmentId: 'dept-2',
    departmentName: 'Civil & Mechanical Engineering',
    referenceStandard: 'ISO9001-9.2',
    fileSize: 2097152,
    mimeType: 'application/pdf',
    encrypted: true,
    encryptionIv: '123456789abcdef012345678',
    checksum: '2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824',
    uploaderUserId: 'user-5',
    uploaderName: 'Engr. Elena Bautista',
    createdAt: new Date(Date.now() - 86400000 * 10).toISOString(),
  },
];

const complianceChecks = [
  {
    _id: 'chk-1',
    checkCode: 'CHK-IT-2026-001',
    departmentId: 'dept-1',
    departmentName: 'Information Technology',
    auditorId: 'user-2',
    auditorName: 'Prof. Alex Reyes',
    auditeeId: 'user-3',
    auditeeName: 'Dr. Roberto Cruz',
    referenceStandard: 'ARTA-3days',
    result: 'compliant',
    remarks: 'ARTA 3-day turnaround compliance for student clearance processing successfully verified.',
    correctiveStatus: null,
    statusHistory: [],
    linkedArchiveIds: ['arch-1'],
    dueDate: null,
    closedAt: null,
    createdAt: new Date(Date.now() - 86400000 * 8).toISOString(),
  },
  {
    _id: 'chk-2',
    checkCode: 'CHK-IT-2026-002',
    departmentId: 'dept-1',
    departmentName: 'Information Technology',
    auditorId: 'user-2',
    auditorName: 'Prof. Alex Reyes',
    auditeeId: 'user-3',
    auditeeName: 'Dr. Roberto Cruz',
    referenceStandard: 'ISO9001-9.2',
    result: 'non_compliant',
    remarks: 'Updated laboratory equipment maintenance logs and calibration procedures for AY 2025-2026 missing.',
    correctiveStatus: 'open',
    statusHistory: [
      {
        status: 'open',
        changedByUserId: 'user-2',
        remarks: 'Non-compliance recorded. Awaiting evidence upload from IT Department.',
        timestamp: new Date(Date.now() - 86400000 * 4).toISOString(),
      },
    ],
    linkedArchiveIds: [],
    dueDate: new Date(Date.now() + 86400000 * 5).toISOString(),
    closedAt: null,
    createdAt: new Date(Date.now() - 86400000 * 4).toISOString(),
  },
  {
    _id: 'chk-3',
    checkCode: 'CHK-ENG-2026-003',
    departmentId: 'dept-2',
    departmentName: 'Civil & Mechanical Engineering',
    auditorId: 'user-4',
    auditorName: 'Engr. Carlos Dalisay',
    auditeeId: 'user-5',
    auditeeName: 'Engr. Elena Bautista',
    referenceStandard: 'ARTA-7days',
    result: 'non_compliant',
    remarks: 'Syllabus review records and faculty consultation logs exceeded the 7-day charter timeframe.',
    correctiveStatus: 'in_progress',
    statusHistory: [
      {
        status: 'open',
        changedByUserId: 'user-4',
        remarks: 'Action opened.',
        timestamp: new Date(Date.now() - 86400000 * 6).toISOString(),
      },
      {
        status: 'in_progress',
        changedByUserId: 'user-5',
        remarks: 'Submitted preliminary consultation logs for review.',
        timestamp: new Date(Date.now() - 86400000 * 2).toISOString(),
      },
    ],
    linkedArchiveIds: ['arch-2'],
    dueDate: new Date(Date.now() + 86400000 * 3).toISOString(),
    closedAt: null,
    createdAt: new Date(Date.now() - 86400000 * 6).toISOString(),
  },
  {
    _id: 'chk-4',
    checkCode: 'CHK-ENG-2026-004',
    departmentId: 'dept-2',
    departmentName: 'Civil & Mechanical Engineering',
    auditorId: 'user-4',
    auditorName: 'Engr. Carlos Dalisay',
    auditeeId: 'user-5',
    auditeeName: 'Engr. Elena Bautista',
    referenceStandard: 'ISO9001-9.2',
    result: 'non_compliant',
    remarks: 'Curriculum committee minutes and stakeholder evaluation reports completed and accepted.',
    correctiveStatus: 'closed',
    statusHistory: [
      {
        status: 'open',
        changedByUserId: 'user-4',
        remarks: 'CAR created.',
        timestamp: new Date(Date.now() - 86400000 * 14).toISOString(),
      },
      {
        status: 'in_progress',
        changedByUserId: 'user-5',
        remarks: 'Action plan submitted.',
        timestamp: new Date(Date.now() - 86400000 * 10).toISOString(),
      },
      {
        status: 'closed',
        changedByUserId: 'user-4',
        remarks: 'Reviewed and closed.',
        timestamp: new Date(Date.now() - 86400000 * 2).toISOString(),
      },
    ],
    linkedArchiveIds: ['arch-3'],
    dueDate: new Date(Date.now() - 86400000 * 7).toISOString(),
    closedAt: new Date(Date.now() - 86400000 * 2).toISOString(),
    createdAt: new Date(Date.now() - 86400000 * 14).toISOString(),
  },
];

const notifications = [
  {
    _id: 'notif-1',
    recipientUserId: 'user-3',
    senderUserId: 'user-2',
    type: 'corrective_action_opened',
    title: 'Corrective action required',
    message: 'Please upload the approved procedure for open finding CHK-IT-2026-002.',
    relatedCheckId: 'chk-2',
    isRead: false,
    createdAt: new Date(Date.now() - 86400000 * 4).toISOString(),
  },
  {
    _id: 'notif-2',
    recipientUserId: 'user-4',
    senderUserId: 'user-5',
    type: 'evidence_uploaded',
    title: 'Evidence ready for review',
    message: 'Corrective evidence has been submitted for in-progress finding CHK-ENG-2026-003.',
    relatedCheckId: 'chk-3',
    isRead: false,
    createdAt: new Date(Date.now() - 86400000 * 2).toISOString(),
  },
  {
    _id: 'notif-3',
    recipientUserId: 'user-5',
    senderUserId: 'user-4',
    type: 'corrective_action_closed',
    title: 'Corrective action closed',
    message: 'Your corrective evidence was reviewed and action CHK-ENG-2026-004 is closed.',
    relatedCheckId: 'chk-4',
    isRead: true,
    createdAt: new Date(Date.now() - 86400000 * 1).toISOString(),
  },
];

const auditLogs = [
  {
    _id: 'log-1',
    action: 'Compliance Check Created',
    userName: 'Prof. Alex Reyes',
    userId: 'user-2',
    entityType: 'ComplianceCheck',
    entityId: 'chk-2',
    ipAddress: '127.0.0.1',
    createdAt: new Date(Date.now() - 86400000 * 4).toISOString(),
  },
  {
    _id: 'log-2',
    action: 'Evidence Uploaded',
    userName: 'Engr. Elena Bautista',
    userId: 'user-5',
    entityType: 'ArchiveDocument',
    entityId: 'arch-2',
    ipAddress: '127.0.0.1',
    createdAt: new Date(Date.now() - 86400000 * 2).toISOString(),
  },
  {
    _id: 'log-3',
    action: 'Corrective Action Closed',
    userName: 'Engr. Carlos Dalisay',
    userId: 'user-4',
    entityType: 'ComplianceCheck',
    entityId: 'chk-4',
    ipAddress: '127.0.0.1',
    createdAt: new Date(Date.now() - 86400000 * 1).toISOString(),
  },
];

let mongoDb = null;
async function initMongoSync() {
  try {
    const MONGODB_URI = process.env.MONGODB_URI;
    if (!MONGODB_URI) {
      console.warn('[CompliTrack] MONGODB_URI is not configured. Using in-memory data only.');
      return;
    }
    const mongoHost = new URL(MONGODB_URI).hostname.toLowerCase();
    if (mongoHost === 'localhost' || mongoHost === '127.0.0.1' || mongoHost === '::1') {
      console.warn('[CompliTrack] MONGODB_URI points to this app container. Configure an Atlas URI to enable database sync; using in-memory data for now.');
      return;
    }
    const DB_NAME = process.env.MONGODB_DATABASE || 'complitrack';
    const client = new MongoClient(MONGODB_URI, {
      serverSelectionTimeoutMS: 15000,
    });
    await client.connect();
    mongoDb = client.db(DB_NAME);
    console.log('[CompliTrack] Connected to MongoDB Atlas. Syncing collections...');

    const dbUsers = await mongoDb.collection('users').find({}).toArray();
    if (dbUsers.length > 0) {
      users.length = 0;
      dbUsers.forEach(u => {
        users.push({
          ...u,
          _id: u._id.toString(),
        });
      });
      console.log(`[CompliTrack] Loaded ${users.length} users from MongoDB Atlas.`);
    }

    const dbDepts = await mongoDb.collection('departments').find({}).toArray();
    if (dbDepts.length > 0) {
      departments.length = 0;
      dbDepts.forEach(d => {
        departments.push({
          ...d,
          _id: d._id.toString(),
        });
      });
      console.log(`[CompliTrack] Loaded ${departments.length} departments from MongoDB Atlas.`);
    }

    const dbChecks = await mongoDb.collection('compliance_checks').find({}).toArray();
    if (dbChecks.length > 0) {
      complianceChecks.length = 0;
      dbChecks.forEach(c => {
        complianceChecks.push({
          ...c,
          _id: c._id.toString(),
        });
      });
      console.log(`[CompliTrack] Loaded ${complianceChecks.length} compliance checks from MongoDB Atlas.`);
    }

    const dbArchives = await mongoDb.collection('archive_documents').find({}).toArray();
    if (dbArchives.length > 0) {
      archives.length = 0;
      dbArchives.forEach(a => {
        archives.push({
          ...a,
          _id: a._id.toString(),
        });
      });
      console.log(`[CompliTrack] Loaded ${archives.length} archives from MongoDB Atlas.`);
    }

    const dbNotifs = await mongoDb.collection('notifications').find({}).toArray();
    if (dbNotifs.length > 0) {
      notifications.length = 0;
      dbNotifs.forEach(n => {
        notifications.push({
          ...n,
          _id: n._id.toString(),
        });
      });
      console.log(`[CompliTrack] Loaded ${notifications.length} notifications from MongoDB Atlas.`);
    }

    const dbLogs = await mongoDb.collection('audit_logs').find({}).toArray();
    if (dbLogs.length > 0) {
      auditLogs.length = 0;
      dbLogs.forEach(l => {
        auditLogs.push({
          ...l,
          _id: l._id.toString(),
        });
      });
      console.log(`[CompliTrack] Loaded ${auditLogs.length} audit logs from MongoDB Atlas.`);
    }
  } catch (err) {
    console.error('[CompliTrack] MongoDB Atlas sync error:', err.message);
  }
}
initMongoSync();

function logActivity(action, user, entityType, entityId) {
  const newLog = {
    _id: `log-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
    action,
    userName: user ? user.fullName : 'System',
    userId: user ? user._id : 'system',
    entityType,
    entityId,
    ipAddress: '127.0.0.1',
    createdAt: new Date().toISOString(),
  };
  auditLogs.unshift(newLog);
  if (mongoDb) {
    mongoDb.collection('audit_logs').insertOne(newLog).catch(() => {});
  }
}

// ==========================================
// MIDDLEWARES
// ==========================================

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(express.static(path.join(__dirname, 'public')));

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
  if (!user) {
    if (req.path.startsWith('/api/')) {
      return res.status(401).json({ message: 'Unauthenticated.' });
    }
    return res.redirect('/login');
  }
  req.user = user;
  next();
}

function requireRole(...allowedRoles) {
  return (req, res, next) => {
    const user = req.user || getCurrentUser(req);
    if (!user) {
      if (req.path.startsWith('/api/')) return res.status(401).json({ message: 'Unauthenticated.' });
      return res.redirect('/login');
    }
    if (!allowedRoles.includes(user.roleId)) {
      if (req.path.startsWith('/api/')) return res.status(403).json({ message: 'Forbidden.' });
      return res.status(403).send('<h1>403 Forbidden</h1><p>You do not have access to this area.</p><a href="/login">Return to Dashboard</a>');
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
    _id: `user-${Date.now()}`,
    userCode,
    fullName,
    email,
    password: 'password123',
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
    _id: `dept-${Date.now()}`,
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
  const auditor = currentUser || users.find((u) => u.roleId === 2);

  const deptCode = dept ? dept.departmentCode : 'CET';
  const code = `CHK-${deptCode}-2026-${String(complianceChecks.length + 1).padStart(3, '0')}`;
  const status = result === 'non_compliant' ? 'open' : null;

  const newCheck = {
    _id: `chk-${Date.now()}`,
    checkCode: code,
    departmentId,
    departmentName: dept ? dept.departmentName : 'CET Department',
    auditorId: auditor ? auditor._id : 'user-2',
    auditorName: auditor ? auditor.fullName : 'Auditor',
    auditeeId,
    auditeeName: auditee ? auditee.fullName : 'Auditee',
    referenceStandard: referenceStandard || 'ARTA-3days',
    result: result || 'compliant',
    remarks: remarks || '',
    correctiveStatus: status,
    statusHistory: status ? [
      {
        status: 'open',
        changedByUserId: auditor ? auditor._id : 'user-2',
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
  logActivity('Compliance Check Created', auditor, 'ComplianceCheck', newCheck._id);

  if (auditee) {
    notifications.unshift({
      _id: `notif-${Date.now()}`,
      recipientUserId: auditee._id,
      senderUserId: auditor ? auditor._id : 'user-2',
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
      _id: `notif-${Date.now()}`,
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

  const fileName = file ? file.originalname : 'Evidence_Submission.pdf';
  const dept = check ? departments.find((d) => d._id === check.departmentId) : (currentUser ? departments.find((d) => d._id === currentUser.departmentId) : null);

  const newArchive = {
    _id: `arch-${Date.now()}`,
    fileCode: `ARCH-2026-${String(archives.length + 1).padStart(3, '0')}`,
    originalName: fileName,
    departmentId: dept ? dept._id : 'dept-1',
    departmentName: dept ? dept.departmentName : 'Information Technology',
    referenceStandard: check ? check.referenceStandard : 'ARTA-3days',
    fileSize: file ? file.size : 1024 * 1024,
    mimeType: file ? file.mimetype : 'application/pdf',
    encrypted: true,
    encryptionIv: '0123456789abcdef01234567',
    checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    uploaderUserId: currentUser ? currentUser._id : 'user-3',
    uploaderName: currentUser ? currentUser.fullName : 'Dr. Roberto Cruz',
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
        changedByUserId: currentUser ? currentUser._id : 'user-3',
        remarks: `Evidence uploaded: ${fileName}`,
        timestamp: new Date().toISOString(),
      });
    }
    // Notify auditor
    if (check.auditorId) {
      notifications.unshift({
        _id: `notif-${Date.now()}`,
        recipientUserId: check.auditorId,
        senderUserId: currentUser ? currentUser._id : 'user-3',
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
  const name = archive ? archive.originalName : 'evidence-document.pdf';
  res.setHeader('Content-Disposition', `attachment; filename="${name}"`);
  res.setHeader('Content-Type', archive ? archive.mimeType : 'application/pdf');
  res.send(Buffer.from(`%PDF-1.4\n% CompliTrack Encrypted Archive File\n% File: ${name}\n% Verified: SHA-256 Validated\n%%EOF`));
});

app.get('/api/v1/archives/:id/verify-checksum', (req, res) => {
  const archive = archives.find((a) => a._id === req.params.id);
  if (!archive) return res.status(404).json({ message: 'Archive record not found.' });
  res.json({
    verified: true,
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
      _id: `notif-${Date.now()}-${auditee._id}`,
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
app.get('/api/v1/reports', (req, res) => {
  const totalChecks = complianceChecks.length;
  const compliantChecks = complianceChecks.filter((c) => c.result === 'compliant').length;
  const openCars = complianceChecks.filter((c) => c.correctiveStatus === 'open').length;
  const inProgressCars = complianceChecks.filter((c) => c.correctiveStatus === 'in_progress').length;
  const closedCars = complianceChecks.filter((c) => c.correctiveStatus === 'closed').length;
  const complianceRate = totalChecks ? Math.round((compliantChecks / totalChecks) * 100) : 0;

  const totalUsers = users.length;
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

  const monthlyComplianceTrend = [
    { month: 'Jun', compliancePercentage: 78 },
    { month: 'Jul', compliancePercentage: 82 },
    { month: 'Aug', compliancePercentage: 85 },
    { month: 'Sep', compliancePercentage: 89 },
    { month: 'Oct', compliancePercentage: 92 },
    { month: 'Nov', compliancePercentage: 94 },
  ];

  res.json({
    data: {
      totalUsers,
      totalAuditors,
      totalAuditees,
      totalDepts,
      storageUsedGb: '3.42',
      activeAudits: totalChecks,
      totalChecks,
      compliantChecks,
      openCars,
      inProgressCars,
      closedCars,
      complianceRate,
      openOverSevenDays: 1,
      deptCompliance,
      monthlyComplianceTrend,
    },
  });
});

// ==========================================
// HTML PAGE GENERATION HELPERS
// ==========================================

const ROLE_NAMES = ['Super Admin', 'Admin', 'Auditor', 'Auditee'];
const HOME_PATHS = ['/super-admin/dashboard', '/admin/dashboard', '/auditor/dashboard', '/auditee/dashboard'];

function renderLayout({ title, heading, user, currentPath, bodyHtml }) {
  const roleId = user ? user.roleId : null;
  const portalStyles = {
    0: '/css/super-admin-portal.css',
    1: '/css/admin-portal.css',
    2: '/css/auditor-portal.css',
    3: '/css/auditee-portal.css',
  };

  const menus = {
    0: [
      ['Dashboard', '/super-admin/dashboard', 'layout-dashboard'],
      ['Users', '/super-admin/users', 'users'],
      ['Roles', '/super-admin/roles', 'shield-check'],
      ['Departments', '/super-admin/departments', 'building-2'],
      ['Archive Repository', '/super-admin/archives', 'archive'],
      ['Logs', '/super-admin/logs', 'scroll-text'],
      ['Settings', '/super-admin/settings', 'settings-2'],
    ],
    1: [
      ['Dashboard', '/admin/dashboard', 'layout-dashboard'],
      ['Departments', '/admin/departments', 'building-2'],
      ['Users', '/admin/users', 'users'],
      ['Compliance Records', '/admin/compliance-records', 'clipboard-check'],
      ['Archives', '/admin/archives', 'archive'],
      ['Reports', '/admin/reports', 'chart-no-axes-combined'],
      ['Notifications', '/admin/notifications', 'bell'],
    ],
    2: [
      ['Dashboard', '/auditor/dashboard', 'layout-dashboard'],
      ['Checks', '/auditor/checks', 'clipboard-check'],
      ['Archives', '/auditor/archives', 'archive'],
      ['Corrective Actions', '/auditor/corrective-actions', 'kanban'],
      ['Notifications', '/auditor/notifications', 'bell'],
    ],
    3: [
      ['Dashboard', '/auditee/dashboard', 'layout-dashboard'],
      ['My Findings', '/auditee/my-findings', 'clipboard-list'],
      ['Upload Evidence', '/auditee/upload', 'upload-cloud'],
      ['Archives', '/auditee/archives', 'archive'],
      ['Notifications', '/auditee/notifications', 'bell'],
      ['Help', '/auditee/help', 'circle-help'],
      ['Profile', '/profile', 'circle-user-round'],
    ],
  };

  const userInitials = user
    ? user.fullName
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('')
        .toUpperCase()
    : 'U';

  const userMenuItems = user ? menus[roleId] || [] : [];

  let sidebarHtml = '';
  if (user) {
    const navLinksHtml = userMenuItems
      .map(([label, href, icon]) => {
        const isActive = currentPath === href || currentPath.startsWith(href + '/');
        const activeClass = isActive ? 'active' : '';
        const itemClass = roleId === 0 ? 'nav-link-item' : 'nav-item';
        return `
          <a href="${href}" class="${itemClass} ${activeClass}" ${isActive ? 'aria-current="page"' : ''}>
            <i data-lucide="${icon}" class="size-5 shrink-0 ${roleId === 0 || roleId === 2 ? 'nav-icon' : ''}"></i>
            <span>${label}</span>
          </a>
        `;
      })
      .join('\n');

    sidebarHtml = `
      <aside x-cloak :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
             class="sidebar fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-ustp-navy text-white transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0">
        ${roleId === 0 ? '<div class="sidebar-primary">' : ''}
          <a href="${HOME_PATHS[roleId]}" class="${roleId === 2 ? 'brand-header' : (roleId === 0 ? 'sidebar-header' : 'sidebar-brand')}">
            <span class="${roleId === 2 ? 'brand-crest' : (roleId === 0 ? 'brand-emblem-svg' : (roleId === 3 ? 'logo-slot logo-slot--sm logo-slot--dark' : 'brand-logo'))}">
              <img src="/images/ustp-claveria-logo.jpg" alt="USTP Claveria" class="${roleId === 3 ? 'logo-slot-img' : 'brand-logo-img'}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
            </span>
            <span>
              <span class="${roleId === 0 ? 'brand-title' : (roleId === 3 ? 'brand-title brand-white' : 'brand-title')}">Compli<span class="${roleId === 0 ? 'track-highlight' : (roleId === 2 ? 'gold-text' : 'brand-gold')}">Track</span></span>
              <span class="brand-subtitle block text-[10px] tracking-wider text-slate-300">CLAVERIA CAMPUS</span>
            </span>
          </a>
          <nav class="${roleId === 2 ? 'nav-menu' : 'sidebar-nav'} mt-4 flex-1 space-y-1 px-3" aria-label="Main navigation">
            ${navLinksHtml}
          </nav>
          <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3">
              <span class="flex size-9 items-center justify-center rounded-full bg-ustp-gold text-xs font-bold text-ustp-navy">${userInitials}</span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-white">${user.fullName}</p>
                <p class="truncate text-[11px] text-slate-300">${ROLE_NAMES[roleId]}</p>
              </div>
              <a href="/logout" title="Sign out" class="rounded p-1 text-slate-300 hover:text-white"><i data-lucide="log-out" class="size-4"></i></a>
            </div>
          </div>
        ${roleId === 0 ? '</div>' : ''}
      </aside>
    `;
  }

  const navbarHtml = user
    ? `
      <header class="${roleId === 0 ? 'top-header-bar' : (roleId === 1 ? 'topbar' : 'top-header')} flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
        <div class="flex items-center gap-3">
          <button type="button" class="rounded-lg p-2 text-ustp-dark hover:bg-slate-100 lg:hidden" aria-label="Open navigation" @click="sidebarOpen = true">
            <i data-lucide="menu" class="size-5"></i>
          </button>
          <div>
            <h1 class="text-xl font-bold font-heading text-ustp-navy">${heading || 'CompliTrack'}</h1>
            <p class="text-xs text-ustp-muted">USTP Claveria · Compliance Management</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          ${
            roleId === 0
              ? `
            <div class="relative" x-data="activityNotifications()" @click.outside="close()" @keydown.escape.window="close()">
              <button type="button" @click="toggle()" class="relative rounded-full p-2 text-ustp-muted hover:bg-slate-100 hover:text-ustp-navy" aria-label="Recent activity">
                <i data-lucide="bell" class="size-5"></i>
                <span x-show="unreadCount > 0" x-cloak class="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white" x-text="unreadCount"></span>
              </button>
            </div>
          `
              : `
            <a href="${roleId === 1 ? '/admin/notifications' : (roleId === 2 ? '/auditor/notifications' : '/auditee/notifications')}" class="relative rounded-full p-2 text-ustp-muted hover:bg-slate-100 hover:text-ustp-navy" aria-label="Notifications">
              <i data-lucide="bell" class="size-5"></i>
            </a>
          `
          }
          <a href="/mobile" target="_blank" class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-bold text-ustp-navy hover:bg-amber-100 transition shadow-xs">
            <i data-lucide="smartphone" class="size-4 text-amber-600"></i>
            <span class="hidden sm:inline">Mobile App</span>
          </a>
          <div class="h-6 w-px bg-slate-200"></div>
          <a href="/profile" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-slate-100">
            <span class="flex size-8 items-center justify-center rounded-full bg-ustp-gold text-xs font-bold text-ustp-navy">${userInitials}</span>
            <span class="hidden sm:inline text-xs font-semibold text-ustp-dark">${user.fullName}</span>
          </a>
        </div>
      </header>
    `
    : '';

  const portalLayout = roleId === 3 ? 'app-layout' : 'app-container';
  const mainLayout = roleId === 2 ? 'main-content' : 'main-wrapper';
  const contentLayout = {
    0: 'view-content-container',
    1: 'content-area',
    2: 'content-body',
    3: 'content-viewport',
  }[roleId] || 'p-6';

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${title || 'CompliTrack'} | USTP Claveria</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  ${viteAssetTag('resources/css/app.css')}
  ${!user ? '<link rel="stylesheet" href="/css/auth-login.css">' : ''}
  ${user && portalStyles[roleId] ? `<link rel="stylesheet" href="${portalStyles[roleId]}">` : ''}
</head>
<body class="min-h-screen bg-ustp-light font-sans text-ustp-dark antialiased" data-user-id="${user ? user._id : ''}">
  ${
    user
      ? `
    <div id="server-offline-banner" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-950/95 p-6 text-center text-white" role="alert" aria-live="assertive">
      <div class="max-w-lg rounded-xl border border-white/15 bg-slate-900 p-8 shadow-2xl">
        <h2 class="mb-3 text-2xl font-bold">Server connection lost</h2>
        <p class="text-base text-slate-200">CompliTrack is offline. Reconnecting…</p>
      </div>
    </div>
    <div class="${portalLayout} flex min-h-screen" x-data="{ sidebarOpen: false }">
      <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false"></div>
      ${sidebarHtml}
      <div class="${mainLayout} flex flex-1 flex-col min-w-0">
        ${navbarHtml}
        <main id="main-content" class="${contentLayout} flex-1 p-6">
          ${bodyHtml}
        </main>
      </div>
    </div>
  `
      : bodyHtml
  }
  ${viteAssetTag('resources/js/app.js')}
</body>
</html>`;
}

function renderLoginPage() {
  const brandPanelHtml = `
    <aside class="sidebar-panel" aria-label="CompliTrack Branding and Features">
      <div class="bg-shape circle-top"></div><div class="bg-shape circle-bottom"></div>
      <header class="sidebar-header">
        <a href="/login" class="brand-logo" title="USTP Claveria CompliTrack Home">
          <div class="logo-badge-wrapper"><img src="/images/ustp-claveria-logo.jpg" alt="USTP Claveria Logo" class="brand-logo-img"></div>
          <div class="brand-text"><div class="logo-title"><span class="text-white">Compli</span><span class="text-gold">Track</span></div><div class="logo-subtitle">CLAVERIA CAMPUS / CET</div></div>
        </a>
      </header>
      <div class="sidebar-content">
        <div class="sidebar-message">
          <h1 class="sidebar-headline">Regulatory Compliance &amp; Accountability, in one place.</h1>
          <p class="sidebar-description">CompliTrack keeps every department's evidence, checks, and corrective actions auditable and up to date.</p>
        </div>
      </div>
      <div class="sidebar-footer">
        <div class="features-list" aria-label="Key Platform Features">
          <div class="feature-item">
            <div class="feature-item-header">
              <div class="feature-icon-badge"><i data-lucide="lock-keyhole" class="icon-gold"></i></div>
              <span class="feature-text">Encrypted archive of compliance evidence</span>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-item-header">
              <div class="feature-icon-badge"><i data-lucide="clipboard-check" class="icon-gold"></i></div>
              <span class="feature-text">Track compliance checks per department</span>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-item-header">
              <div class="feature-icon-badge"><i data-lucide="bell" class="icon-gold"></i></div>
              <span class="feature-text">Real-time corrective action notifications</span>
            </div>
          </div>
        </div>
      </div>
    </aside>
  `;

  const formPanelHtml = `
    <main class="main-panel">
      <section class="form-wrapper" x-data="loginPage()">
        <template x-if="error"><div class="status-toast" role="alert" x-text="error"></div></template>
        <div class="form-header">
          <div class="campus-pill">USTP CLAVERIA PORTAL</div>
          <h2 class="form-title">Welcome back</h2>
          <p class="form-subtitle">Sign in to your CompliTrack account</p>
        </div>
        <form class="auth-form" @submit.prevent="submit()">
          <div class="form-group">
            <label for="loginEmail" class="form-label">Institutional Email</label>
            <input x-model="email" type="email" id="loginEmail" class="form-input" placeholder="admin@example.edu" autocomplete="username" required autofocus>
          </div>
          <div class="form-group">
            <label for="loginPassword" class="form-label">Password</label>
            <div class="password-input-wrapper">
              <input x-model="password" type="password" id="loginPassword" class="form-input password-input" placeholder="Enter your password" autocomplete="current-password" required>
              <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility" @click="$el.previousElementSibling.type = $el.previousElementSibling.type === 'password' ? 'text' : 'password'">
                <i data-lucide="eye" class="eye-icon eye-show"></i>
              </button>
            </div>
          </div>
          <div class="form-row-utility">
            <label class="checkbox-container"><input x-model="remember" type="checkbox"><span class="checkbox-label">Remember Me</span></label>
            <a href="/forgot-password" class="link-forgot">Forgot password?</a>
          </div>
          <button :disabled="loading" type="submit" class="btn btn-primary">
            <span class="btn-text" x-text="loading ? 'Signing in…' : 'Log In'"></span>
            <span class="btn-arrow">&rarr;</span>
          </button>
        </form>
        <div class="form-disclaimer"><p>Accounts are provisioned by your Department Admin. Contact your Super Admin if you need access.</p></div>
      </section>
    </main>
  `;

  return renderLayout({
    title: 'Sign in',
    user: null,
    currentPath: '/login',
    bodyHtml: `<div class="auth-container">${brandPanelHtml}${formPanelHtml}</div>`,
  });
}

function renderForgotPasswordPage() {
  return renderLayout({
    title: 'Forgot Password',
    user: null,
    currentPath: '/forgot-password',
    bodyHtml: `
      <div class="auth-container">
        <main class="main-panel max-w-lg mx-auto py-12 px-6">
          <section class="form-wrapper" x-data="forgotPasswordPage()">
            <a href="/login" class="inline-flex items-center gap-2 text-sm font-semibold text-ustp-navy mb-6"><i data-lucide="arrow-left" class="size-4"></i> Back to sign in</a>
            <div class="form-header">
              <h2 class="form-title">Reset your password</h2>
              <p class="form-subtitle">Enter your institutional email to receive a password reset link.</p>
            </div>
            <template x-if="submitted">
              <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
                A password reset link has been dispatched to your email address.
              </div>
            </template>
            <template x-if="!submitted">
              <form class="auth-form space-y-4" @submit.prevent="submit()">
                <div class="form-group">
                  <label for="forgotEmail" class="form-label">Institutional Email</label>
                  <input x-model="email" type="email" id="forgotEmail" class="form-input" placeholder="Enter your email" required autofocus>
                </div>
                <button :disabled="loading" type="submit" class="btn btn-primary w-full">
                  <span x-text="loading ? 'Sending link…' : 'Send Reset Link'"></span>
                </button>
              </form>
            </template>
          </section>
        </main>
      </div>
    `,
  });
}

function renderDashboardComponent(roleId, heading, subheading) {
  return `
    <x-dashboard-page role-id="${roleId}"></x-dashboard-page>
    <section class="app-view-panel active" x-data="dashboardPage({ roleId: ${roleId}, heading: '${heading}', subheading: '${subheading}' })" x-init="load()">
      <template x-if="error">
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <span x-text="error"></span>
          <button type="button" @click="load()" class="mt-2 block rounded-lg bg-ustp-navy px-3 py-1.5 text-xs font-semibold text-white">Retry</button>
        </div>
      </template>

      <!-- KPI Stat Cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in cards" :key="card.key">
          <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-ustp-muted" x-text="card.label"></span>
              <span class="flex size-9 items-center justify-center rounded-xl bg-slate-100 text-ustp-navy"><i :data-lucide="card.icon" class="size-5"></i></span>
            </div>
            <div class="mt-3 font-heading text-3xl font-bold text-ustp-navy" x-text="loading ? '—' : (stats[card.key] ?? 0)"></div>
          </article>
        </template>
      </div>

      <!-- Quick Actions & Recent Activity -->
      <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="mb-4 flex items-center justify-between">
            <div>
              <h3 class="font-heading text-lg font-semibold text-ustp-dark">Recent compliance checks</h3>
              <p class="text-xs text-ustp-muted">Latest activity available to your role</p>
            </div>
            <a :href="checksUrl" class="text-xs font-semibold text-ustp-navy hover:underline">View all</a>
          </div>
          <template x-if="loading"><div class="h-28 animate-pulse rounded-xl bg-slate-100"></div></template>
          <template x-if="!loading && recentChecks.length === 0"><p class="rounded-xl bg-slate-50 p-6 text-center text-sm text-ustp-muted">No records yet.</p></template>
          <div x-show="!loading && recentChecks.length" class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
              <thead class="border-b border-slate-100 text-xs uppercase tracking-wider text-ustp-muted">
                <tr><th class="py-2.5 pr-4 font-semibold">Check</th><th class="py-2.5 pr-4 font-semibold">Department</th><th class="py-2.5 pr-4 font-semibold">Result</th><th class="py-2.5 font-semibold">Status</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <template x-for="check in recentChecks" :key="check._id">
                  <tr>
                    <td class="py-3 pr-4 font-semibold text-ustp-navy" x-text="check.checkCode"></td>
                    <td class="py-3 pr-4 text-ustp-dark" x-text="check.departmentName || '—'"></td>
                    <td class="py-3 pr-4"><span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="check.result === 'compliant' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'" x-text="(check.result || '').replace('_', ' ')"></span></td>
                    <td class="py-3 text-xs capitalize text-ustp-muted" x-text="(check.correctiveStatus || '—').replace('_', ' ')"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </article>

        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h3 class="font-heading text-lg font-semibold text-ustp-dark">Quick actions</h3>
          <p class="mt-1 text-xs text-ustp-muted">Direct navigation to frequent workflows.</p>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <template x-for="action in actions" :key="action.href">
              <a :href="action.href" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-ustp-navy hover:bg-slate-50">
                <span class="flex size-9 items-center justify-center rounded-lg bg-ustp-navy/5 text-ustp-navy"><i :data-lucide="action.icon" class="size-5"></i></span>
                <span class="text-sm font-semibold text-ustp-dark" x-text="action.label"></span>
              </a>
            </template>
          </div>
        </article>
      </div>

      <!-- Compliance Charts (Admin & Super Admin) -->
      <div x-show="reportData && [0, 1].includes(roleId)" class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h3 class="font-heading text-lg font-semibold text-ustp-dark">Compliance by department</h3>
          <template x-if="reportData?.deptCompliance?.length">
            <div class="mt-4 space-y-3.5">
              <template x-for="item in reportData.deptCompliance" :key="item.departmentCode">
                <div>
                  <div class="mb-1 flex justify-between text-xs font-semibold">
                    <span class="text-ustp-dark" x-text="item.departmentName"></span>
                    <span class="text-ustp-navy" x-text="item.compliancePercentage + '%'"></span>
                  </div>
                  <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-ustp-navy transition-all" :style="'width: ' + Math.min(100, item.compliancePercentage) + '%'"></div>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </article>

        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h3 class="font-heading text-lg font-semibold text-ustp-dark">Monthly compliance trend</h3>
          <template x-if="reportData?.monthlyComplianceTrend?.length">
            <div class="mt-6 flex h-40 items-end justify-between gap-3 border-b border-slate-100 pb-2">
              <template x-for="m in reportData.monthlyComplianceTrend" :key="m.month">
                <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                  <span class="text-[10px] font-bold text-ustp-navy" x-text="m.compliancePercentage + '%'"></span>
                  <div class="w-full max-w-8 rounded-t-lg bg-ustp-gold transition-all" :style="'height: ' + m.compliancePercentage + '%'"></div>
                  <span class="text-xs text-ustp-muted" x-text="m.month"></span>
                </div>
              </template>
            </div>
          </template>
        </article>
      </div>
    </section>
  `;
}

function renderResourcePageComponent(config) {
  const jsonConfig = JSON.stringify(config);
  return `
    <section class="app-view-panel active" x-data='resourcePage(${jsonConfig})' x-init="load()">
      <div x-cloak x-show="successPopupVisible" x-transition class="fixed right-6 top-6 z-[100] flex min-h-[52px] w-[calc(100%-3rem)] max-w-sm items-center rounded-lg border-l-4 bg-ustp-navy px-5 py-4 text-xs font-medium text-white shadow-xl" :class="successPopupType === 'error' ? 'border-red-500' : 'border-ustp-gold'">
        <span class="min-w-0 flex-1" x-text="successPopupMessage"></span>
      </div>

      <!-- Header & Search Toolbar -->
      <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="font-heading text-2xl font-bold text-ustp-navy">${config.title || 'Records'}</h2>
          <p class="mt-1 text-xs text-ustp-muted">${config.description || ''}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <div class="relative">
            <i data-lucide="search" class="absolute left-3 top-2.5 size-4 text-slate-400"></i>
            <input type="search" x-model="search" @input.debounce.300ms="load(1)" placeholder="Search records..." class="h-9 rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-xs font-medium text-ustp-dark focus:border-ustp-navy focus:outline-none">
          </div>
          <button x-show="allowUserCreate" type="button" @click="startUserCreate()" class="flex h-9 items-center gap-1.5 rounded-xl bg-ustp-gold px-3 text-xs font-bold text-ustp-navy hover:bg-amber-400">
            <i data-lucide="user-round-plus" class="size-4"></i><span>Create User</span>
          </button>
          <button x-show="allowDepartmentCreate" type="button" @click="startDepartmentCreate()" class="flex h-9 items-center gap-1.5 rounded-xl bg-ustp-gold px-3 text-xs font-bold text-ustp-navy hover:bg-amber-400">
            <i data-lucide="building-2" class="size-4"></i><span>Add Department</span>
          </button>
          <button x-show="allowBroadcast" type="button" @click="startBroadcast()" class="flex h-9 items-center gap-1.5 rounded-xl bg-ustp-navy px-3 text-xs font-bold text-white hover:bg-ustp-gold hover:text-ustp-navy">
            <i data-lucide="send" class="size-4"></i><span>Broadcast</span>
          </button>
        </div>
      </div>

      <!-- Table Section -->
      <template x-if="loading">
        <div class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
          <div class="h-10 animate-pulse rounded-lg bg-slate-100"></div>
          <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
          <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
        </div>
      </template>

      <template x-if="!loading && rows.length === 0">
        <div class="rounded-2xl bg-white p-12 text-center shadow-sm">
          <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-ustp-navy"><i data-lucide="inbox" class="size-6"></i></span>
          <p class="mt-3 font-semibold text-ustp-dark">No records found</p>
          <p class="text-xs text-ustp-muted">Records will appear here when added or assigned.</p>
        </div>
      </template>

      <div x-show="!loading && rows.length > 0" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-ustp-muted">
              <tr>
                <template x-for="column in columns" :key="column.key">
                  <th scope="col" class="whitespace-nowrap px-5 py-3" x-text="column.label"></th>
                </template>
                <th x-show="allowArchiveActions || allowNotificationActions || allowUserActions || allowDepartmentActions || allowStatusUpdates" scope="col" class="px-5 py-3">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template x-for="row in rows" :key="row._id">
                <tr class="hover:bg-slate-50/70">
                  <template x-for="column in columns" :key="column.key">
                    <td class="whitespace-nowrap px-5 py-4 text-ustp-dark">
                      <template x-if="column.key === 'checkCode' && detailBase">
                        <a :href="'/' + detailBase + '/' + encodeURIComponent(row._id)" class="font-semibold text-ustp-navy hover:underline" x-text="formatValue(row[column.key])"></a>
                      </template>
                      <template x-if="column.key === 'roleId'">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="roleClass(row.roleId)" x-text="roleName(row.roleId)"></span>
                      </template>
                      <template x-if="['status', 'result', 'correctiveStatus'].includes(column.key) && !(column.key === 'correctiveStatus' && allowStatusUpdates && row.correctiveStatus)">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusClass(row[column.key])" x-text="formatValue(row[column.key])"></span>
                      </template>
                      <template x-if="column.key === 'correctiveStatus' && allowStatusUpdates && row.correctiveStatus">
                        <select :value="row.correctiveStatus" @change="updateStatus(row, $event.target)" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold">
                          <template x-for="opt in statusOptions(row)" :key="opt.value"><option :value="opt.value" :disabled="opt.disabled" x-text="opt.label"></option></template>
                        </select>
                      </template>
                      <template x-if="column.key !== 'checkCode' && column.key !== 'roleId' && !['status', 'result', 'correctiveStatus'].includes(column.key)">
                        <span x-text="formatValue(row[column.key])"></span>
                      </template>
                    </td>
                  </template>
                  <td x-show="allowArchiveActions || allowNotificationActions || allowUserActions || allowDepartmentActions" class="whitespace-nowrap px-5 py-4 text-xs font-semibold">
                    <template x-if="allowArchiveActions">
                      <div class="flex items-center gap-3">
                        <button type="button" @click="verifyArchive(row)" class="text-ustp-navy hover:underline">Verify</button>
                        <button type="button" @click="downloadArchive(row)" class="text-ustp-navy hover:underline">Download</button>
                      </div>
                    </template>
                    <template x-if="allowNotificationActions">
                      <div>
                        <button x-show="!row.isRead" type="button" @click="markRead(row)" class="text-ustp-navy hover:underline">Mark read</button>
                        <span x-show="row.isRead" class="text-slate-400">Read</span>
                      </div>
                    </template>
                    <template x-if="allowUserActions">
                      <div class="flex items-center gap-3">
                        <button type="button" @click="startUserEdit(row)" class="text-ustp-navy hover:underline">Edit</button>
                        <button x-show="isRowActive(row)" type="button" @click="sendReset(row)" class="text-ustp-navy hover:underline">Reset</button>
                        <button x-show="isRowActive(row) && Number(row.roleId) !== 0" type="button" @click="deactivateUser(row)" class="text-red-600 hover:underline">Deactivate</button>
                        <button x-show="!isRowActive(row)" type="button" @click="reactivateUser(row)" class="text-emerald-700 hover:underline">Reactivate</button>
                      </div>
                    </template>
                    <template x-if="allowDepartmentActions">
                      <div class="flex items-center gap-3">
                        <button type="button" @click="startDepartmentEdit(row)" class="text-ustp-navy hover:underline">Edit</button>
                        <button x-show="isRowActive(row)" type="button" @click="deactivateDepartment(row)" class="text-red-600 hover:underline">Deactivate</button>
                        <button x-show="!isRowActive(row)" type="button" @click="reactivateDepartment(row)" class="text-emerald-700 hover:underline">Reactivate</button>
                      </div>
                    </template>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-ustp-muted">
          <p><span x-text="meta.total || 0"></span> total records</p>
          <div class="flex items-center gap-2">
            <button type="button" @click="load(page - 1)" :disabled="page <= 1" class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40">Previous</button>
            <span>Page <span x-text="page"></span> of <span x-text="meta.lastPage || 1"></span></span>
            <button type="button" @click="load(page + 1)" :disabled="page >= (meta.lastPage || 1)" class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40">Next</button>
          </div>
        </div>
      </div>

      <!-- User Create/Edit Modal -->
      <div x-cloak x-show="userModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <form @submit.prevent="saveUser()" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
          <h3 class="font-heading text-lg font-bold text-ustp-navy" x-text="userForm._id ? 'Edit User' : 'Create User'"></h3>
          <template x-if="error"><div class="rounded-lg bg-red-50 p-3 text-xs text-red-700" x-text="error"></div></template>
          <div><label class="block text-xs font-semibold text-ustp-muted">Full Name</label><input x-model="userForm.fullName" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
          <div><label class="block text-xs font-semibold text-ustp-muted">Email</label><input x-model="userForm.email" type="email" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
          <div>
            <label class="block text-xs font-semibold text-ustp-muted">Role</label>
            <select x-model.number="userForm.roleId" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <template x-if="actorRoleId === 0"><option value="0">Super Admin</option></template>
              <template x-if="actorRoleId === 0 || actorRoleId === 1"><option value="1">Admin</option></template>
              <option value="2">Auditor</option>
              <option value="3">Auditee</option>
            </select>
          </div>
          <div x-show="userForm.roleId !== 0">
            <label class="block text-xs font-semibold text-ustp-muted">Department</label>
            <select x-model="userForm.departmentId" :required="userForm.roleId !== 0" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="">Choose department</option>
              <template x-for="d in departments" :key="d._id"><option :value="d._id" x-text="d.departmentName"></option></template>
            </select>
          </div>
          <div x-show="userForm.roleId === 3">
            <label class="block text-xs font-semibold text-ustp-muted">Office Name</label>
            <input x-model="userForm.officeName" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="userModalOpen = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold">Cancel</button>
            <button type="submit" :disabled="saving" class="rounded-xl bg-ustp-navy px-4 py-2 text-xs font-semibold text-white" x-text="saving ? 'Saving…' : 'Save'"></button>
          </div>
        </form>
      </div>

      <!-- Department Create/Edit Modal -->
      <div x-cloak x-show="departmentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <form @submit.prevent="saveDepartment()" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
          <h3 class="font-heading text-lg font-bold text-ustp-navy" x-text="departmentForm._id ? 'Edit Department' : 'Add Department'"></h3>
          <div><label class="block text-xs font-semibold text-ustp-muted">Department Code</label><input x-model="departmentForm.departmentCode" :readonly="departmentForm._id !== null" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
          <div><label class="block text-xs font-semibold text-ustp-muted">Department Name</label><input x-model="departmentForm.departmentName" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
          <div><label class="block text-xs font-semibold text-ustp-muted">College</label><input x-model="departmentForm.collegeName" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
          <div><label class="block text-xs font-semibold text-ustp-muted">Campus</label><input x-model="departmentForm.campusName" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="departmentModalOpen = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold">Cancel</button>
            <button type="submit" :disabled="saving" class="rounded-xl bg-ustp-navy px-4 py-2 text-xs font-semibold text-white" x-text="saving ? 'Saving…' : 'Save'"></button>
          </div>
        </form>
      </div>

      <!-- Broadcast Modal -->
      <div x-cloak x-show="broadcastModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <form @submit.prevent="sendBroadcast()" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
          <h3 class="font-heading text-lg font-bold text-ustp-navy">Broadcast Notification</h3>
          <div>
            <label class="block text-xs font-semibold text-ustp-muted">Target Department</label>
            <select x-model="broadcastForm.departmentId" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="">Choose department</option>
              <template x-for="d in departments" :key="d._id"><option :value="d._id" x-text="d.departmentName"></option></template>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-ustp-muted">Message</label>
            <textarea x-model="broadcastForm.message" required rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="broadcastModalOpen = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold">Cancel</button>
            <button type="submit" :disabled="saving" class="rounded-xl bg-ustp-navy px-4 py-2 text-xs font-semibold text-white" x-text="saving ? 'Sending…' : 'Send'"></button>
          </div>
        </form>
      </div>
    </section>
  `;
}

// ==========================================
// PAGE ROUTING
// ==========================================

app.get('/', (req, res) => {
  const user = getCurrentUser(req);
  if (user) return res.redirect(HOME_PATHS[user.roleId]);
  res.send(renderLoginPage());
});

app.get('/login', (req, res) => {
  const user = getCurrentUser(req);
  if (user) return res.redirect(HOME_PATHS[user.roleId]);
  res.send(renderLoginPage());
});

app.get('/logout', (req, res) => {
  res.clearCookie('complitrack_user_id');
  res.redirect('/login');
});

app.get('/forgot-password', (req, res) => {
  res.send(renderForgotPasswordPage());
});

app.get('/profile', requireAuth, (req, res) => {
  const user = req.user;
  const userInitials = user.fullName.split(' ').slice(0, 2).map((n) => n[0]).join('').toUpperCase();
  const bodyHtml = `
    <section class="max-w-4xl mx-auto py-6" x-data="profilePage({ avatarUrl: '${user.avatarPath || ''}' })">
      <header class="mb-6">
        <p class="text-xs font-bold uppercase tracking-wider text-ustp-muted">Your Account</p>
        <h2 class="font-heading text-3xl font-bold text-ustp-navy">Account Details</h2>
        <p class="mt-1 text-sm text-ustp-muted">Manage your profile photo and review your CompliTrack permissions.</p>
      </header>
      <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="flex flex-col gap-5 border-b border-slate-100 p-6 sm:flex-row sm:items-center sm:p-8">
          <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-ustp-gold text-2xl font-bold text-ustp-navy ring-4 ring-white shadow-sm">
            <span x-text="'${userInitials}'"></span>
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="font-heading text-xl font-bold text-ustp-dark">${user.fullName}</h3>
            <p class="text-sm text-ustp-muted">${user.email}</p>
          </div>
          <a href="/logout" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="log-out" class="size-4"></i> Sign Out
          </a>
        </div>
        <dl class="grid gap-4 p-6 sm:grid-cols-2 sm:p-8">
          <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
            <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">User Code</dt>
            <dd class="mt-1 text-sm font-bold text-ustp-dark">${user.userCode}</dd>
          </div>
          <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
            <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">Role</dt>
            <dd class="mt-1 text-sm font-bold text-ustp-navy">${ROLE_NAMES[user.roleId]}</dd>
          </div>
          <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
            <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">Office</dt>
            <dd class="mt-1 text-sm font-medium text-ustp-dark">${user.officeName || '—'}</dd>
          </div>
          <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
            <dt class="text-xs font-semibold uppercase tracking-wider text-ustp-muted">Account Status</dt>
            <dd class="mt-1 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">
              <span class="size-2 rounded-full bg-emerald-600"></span> Active
            </dd>
          </div>
        </dl>
      </div>
    </section>
  `;
  res.send(renderLayout({ title: 'My Profile', heading: 'My Profile', user, currentPath: '/profile', bodyHtml }));
});

// SUPER ADMIN (Role 0)
app.get('/super-admin/dashboard', requireRole(0), (req, res) => {
  const bodyHtml = renderDashboardComponent(0, 'System Overview', 'System-wide compliance, departments, and storage activity.');
  res.send(renderLayout({ title: 'System Overview', heading: 'System Overview', user: req.user, currentPath: '/super-admin/dashboard', bodyHtml }));
});

app.get('/super-admin/users', requireRole(0), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'User Management',
    description: 'System-wide user administration and role assignment.',
    endpoint: 'users',
    allowUserCreate: true,
    allowUserActions: true,
    actorRoleId: 0,
    columns: [
      { key: 'userCode', label: 'User Code' },
      { key: 'fullName', label: 'Full Name' },
      { key: 'email', label: 'Email' },
      { key: 'roleId', label: 'Role' },
      { key: 'isActive', label: 'Status' },
    ],
  });
  res.send(renderLayout({ title: 'Users', heading: 'User Management', user: req.user, currentPath: '/super-admin/users', bodyHtml }));
});

app.get('/super-admin/roles', requireRole(0), (req, res) => {
  const bodyHtml = `
    <section class="max-w-5xl">
      <header class="mb-6">
        <p class="text-xs font-bold uppercase tracking-wider text-ustp-muted">Access Control</p>
        <h2 class="font-heading text-2xl font-bold text-ustp-navy">Role Overview</h2>
        <p class="mt-1 text-sm text-ustp-muted">Review permissions and capabilities granted across all 4 system roles.</p>
      </header>
      <div class="grid gap-4 sm:grid-cols-2">
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex items-center justify-between">
            <span class="rounded-lg bg-ustp-gold/20 p-2 text-ustp-navy"><i data-lucide="shield-check" class="size-5"></i></span>
            <span class="rounded-full bg-ustp-gold px-2.5 py-0.5 text-xs font-bold text-ustp-navy">Super Admin (0)</span>
          </div>
          <h3 class="mt-4 font-heading text-lg font-bold text-ustp-dark">System Administrator</h3>
          <p class="mt-1 text-sm text-ustp-muted">Full access to manage all users, departments, roles, backups, and audit logs.</p>
          <div class="mt-4 border-t border-slate-100 pt-3 text-xs font-semibold text-ustp-navy">SCOPE: System-wide</div>
        </article>
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex items-center justify-between">
            <span class="rounded-lg bg-sky-100 p-2 text-sky-800"><i data-lucide="settings-2" class="size-5"></i></span>
            <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-bold text-sky-800">CET Admin (1)</span>
          </div>
          <h3 class="mt-4 font-heading text-lg font-bold text-ustp-dark">College Administrator</h3>
          <p class="mt-1 text-sm text-ustp-muted">Manage CET departments, auditor/auditee accounts, read-only archives, view reports.</p>
          <div class="mt-4 border-t border-slate-100 pt-3 text-xs font-semibold text-ustp-navy">SCOPE: CET College</div>
        </article>
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex items-center justify-between">
            <span class="rounded-lg bg-purple-100 p-2 text-purple-800"><i data-lucide="clipboard-check" class="size-5"></i></span>
            <span class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-bold text-purple-800">Auditor (2)</span>
          </div>
          <h3 class="mt-4 font-heading text-lg font-bold text-ustp-dark">Compliance Auditor</h3>
          <p class="mt-1 text-sm text-ustp-muted">Perform checks, open and update CARs, review and verify uploaded evidence.</p>
          <div class="mt-4 border-t border-slate-100 pt-3 text-xs font-semibold text-ustp-navy">SCOPE: Assigned Departments</div>
        </article>
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex items-center justify-between">
            <span class="rounded-lg bg-slate-100 p-2 text-slate-800"><i data-lucide="file-check-2" class="size-5"></i></span>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-800">Auditee (3)</span>
          </div>
          <h3 class="mt-4 font-heading text-lg font-bold text-ustp-dark">Office Head / Faculty</h3>
          <p class="mt-1 text-sm text-ustp-muted">Review departmental compliance findings, upload evidence, receive corrective alerts.</p>
          <div class="mt-4 border-t border-slate-100 pt-3 text-xs font-semibold text-ustp-navy">SCOPE: Own Department</div>
        </article>
      </div>
    </section>
  `;
  res.send(renderLayout({ title: 'Roles', heading: 'Roles & Permissions', user: req.user, currentPath: '/super-admin/roles', bodyHtml }));
});

app.get('/super-admin/departments', requireRole(0), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Departments',
    description: 'Campus and college department master records.',
    endpoint: 'departments',
    allowDepartmentCreate: true,
    allowDepartmentActions: true,
    actorRoleId: 0,
    columns: [
      { key: 'departmentCode', label: 'Code' },
      { key: 'departmentName', label: 'Department' },
      { key: 'collegeName', label: 'College' },
      { key: 'campusName', label: 'Campus' },
      { key: 'isActive', label: 'Status' },
    ],
  });
  res.send(renderLayout({ title: 'Departments', heading: 'Departments', user: req.user, currentPath: '/super-admin/departments', bodyHtml }));
});

app.get('/super-admin/archives', requireRole(0), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Archive Repository',
    description: 'Encrypted document repository for regulatory compliance records.',
    endpoint: 'archives',
    allowArchiveActions: true,
    columns: [
      { key: 'fileCode', label: 'Archive Code' },
      { key: 'originalName', label: 'File Name' },
      { key: 'departmentName', label: 'Department' },
      { key: 'referenceStandard', label: 'Standard' },
      { key: 'uploaderName', label: 'Uploaded By' },
      { key: 'createdAt', label: 'Date' },
    ],
  });
  res.send(renderLayout({ title: 'Archives', heading: 'Archive Repository', user: req.user, currentPath: '/super-admin/archives', bodyHtml }));
});

app.get('/super-admin/logs', requireRole(0), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'System Audit Logs',
    description: 'Immutable record of system operations, user logins, and compliance actions.',
    endpoint: 'logs',
    columns: [
      { key: 'action', label: 'Action' },
      { key: 'userName', label: 'User' },
      { key: 'entityType', label: 'Entity' },
      { key: 'ipAddress', label: 'IP Address' },
      { key: 'createdAt', label: 'Timestamp' },
    ],
  });
  res.send(renderLayout({ title: 'Audit Logs', heading: 'System Audit Logs', user: req.user, currentPath: '/super-admin/logs', bodyHtml }));
});

app.get('/super-admin/settings', requireRole(0), (req, res) => {
  const bodyHtml = `
    <section class="max-w-3xl">
      <header class="mb-6">
        <h2 class="font-heading text-2xl font-bold text-ustp-navy">System Settings</h2>
        <p class="text-sm text-ustp-muted">Deployment parameters, encryption status, and environment information.</p>
      </header>
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70 space-y-4">
        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
          <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><i data-lucide="shield-check" class="size-6"></i></span>
          <div>
            <h3 class="font-bold text-ustp-dark">AES-256-GCM Evidence Encryption</h3>
            <p class="text-xs text-ustp-muted">Active with per-file initialization vectors and SHA-256 checksums.</p>
          </div>
        </div>
        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
          <span class="flex size-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700"><i data-lucide="server" class="size-6"></i></span>
          <div>
            <h3 class="font-bold text-ustp-dark">Runtime Environment</h3>
            <p class="text-xs text-ustp-muted">CompliTrack Web API (Node.js 22 Express Engine) on Port 3000.</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <span class="flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><i data-lucide="bell-ring" class="size-6"></i></span>
          <div>
            <h3 class="font-bold text-ustp-dark">Notification Dispatch</h3>
            <p class="text-xs text-ustp-muted">In-app notifications enabled across ARTA 3/7/20-day and ISO 9001 clauses.</p>
          </div>
        </div>
      </div>
    </section>
  `;
  res.send(renderLayout({ title: 'Settings', heading: 'System Settings', user: req.user, currentPath: '/super-admin/settings', bodyHtml }));
});

// ADMIN (Role 1)
app.get('/admin/dashboard', requireRole(1), (req, res) => {
  const bodyHtml = renderDashboardComponent(1, 'Admin Dashboard', 'CET departments, audit activity, and corrective actions.');
  res.send(renderLayout({ title: 'Admin Dashboard', heading: 'Dashboard', user: req.user, currentPath: '/admin/dashboard', bodyHtml }));
});

app.get('/admin/departments', requireRole(1), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Departments',
    description: 'CET departments available for compliance tracking.',
    endpoint: 'departments',
    allowDepartmentCreate: true,
    allowDepartmentActions: true,
    actorRoleId: 1,
    columns: [
      { key: 'departmentCode', label: 'Code' },
      { key: 'departmentName', label: 'Department' },
      { key: 'collegeName', label: 'College' },
      { key: 'campusName', label: 'Campus' },
      { key: 'isActive', label: 'Status' },
    ],
  });
  res.send(renderLayout({ title: 'Departments', heading: 'Departments', user: req.user, currentPath: '/admin/departments', bodyHtml }));
});

app.get('/admin/users', requireRole(1), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'User Management',
    description: 'Manage auditors and auditees assigned to CET departments.',
    endpoint: 'users',
    allowUserCreate: true,
    allowUserActions: true,
    actorRoleId: 1,
    columns: [
      { key: 'userCode', label: 'User Code' },
      { key: 'fullName', label: 'Full Name' },
      { key: 'email', label: 'Email' },
      { key: 'roleId', label: 'Role' },
      { key: 'isActive', label: 'Status' },
    ],
  });
  res.send(renderLayout({ title: 'Users', heading: 'User Management', user: req.user, currentPath: '/admin/users', bodyHtml }));
});

app.get('/admin/compliance-records', requireRole(1), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Compliance Records',
    description: 'Review checks and corrective action status across CET departments.',
    endpoint: 'compliance-checks',
    columns: [
      { key: 'checkCode', label: 'Check Code' },
      { key: 'departmentName', label: 'Department' },
      { key: 'auditeeName', label: 'Auditee' },
      { key: 'referenceStandard', label: 'Standard' },
      { key: 'result', label: 'Result' },
      { key: 'correctiveStatus', label: 'Corrective Status' },
      { key: 'createdAt', label: 'Date' },
    ],
  });
  res.send(renderLayout({ title: 'Compliance Records', heading: 'Compliance Records', user: req.user, currentPath: '/admin/compliance-records', bodyHtml }));
});

app.get('/admin/archives', requireRole(1), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Archives',
    description: 'Read-only access to encrypted CET evidence archives.',
    endpoint: 'archives',
    allowArchiveActions: true,
    columns: [
      { key: 'fileCode', label: 'Archive Code' },
      { key: 'originalName', label: 'File Name' },
      { key: 'departmentName', label: 'Department' },
      { key: 'referenceStandard', label: 'Standard' },
      { key: 'createdAt', label: 'Uploaded' },
    ],
  });
  res.send(renderLayout({ title: 'Archives', heading: 'Archives', user: req.user, currentPath: '/admin/archives', bodyHtml }));
});

app.get('/admin/reports', requireRole(1), (req, res) => {
  const bodyHtml = renderDashboardComponent(1, 'Compliance Reports', 'CET compliance performance and corrective-action trends.');
  res.send(renderLayout({ title: 'Reports', heading: 'Compliance Reports', user: req.user, currentPath: '/admin/reports', bodyHtml }));
});

app.get('/admin/notifications', requireRole(1), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Notifications & Broadcasts',
    description: 'Review communications and send announcements to department auditees.',
    endpoint: 'notifications',
    allowNotificationActions: true,
    allowBroadcast: true,
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'message', label: 'Message' },
      { key: 'createdAt', label: 'Date' },
    ],
  });
  res.send(renderLayout({ title: 'Notifications', heading: 'Notifications', user: req.user, currentPath: '/admin/notifications', bodyHtml }));
});

// AUDITOR (Role 2)
app.get('/auditor/dashboard', requireRole(2), (req, res) => {
  const bodyHtml = renderDashboardComponent(2, 'Auditor Dashboard', 'Your compliance checks, corrective actions, and evidence.');
  res.send(renderLayout({ title: 'Auditor Dashboard', heading: 'Dashboard', user: req.user, currentPath: '/auditor/dashboard', bodyHtml }));
});

app.get('/auditor/checks', requireRole(2), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Compliance Checks',
    description: 'Assessments created for your assigned departments.',
    endpoint: 'compliance-checks',
    detailBase: 'auditor/checks',
    columns: [
      { key: 'checkCode', label: 'Check Code' },
      { key: 'departmentName', label: 'Department' },
      { key: 'auditeeName', label: 'Auditee' },
      { key: 'referenceStandard', label: 'Standard' },
      { key: 'result', label: 'Result' },
      { key: 'correctiveStatus', label: 'Status' },
    ],
  });
  res.send(renderLayout({ title: 'Compliance Checks', heading: 'Checks', user: req.user, currentPath: '/auditor/checks', bodyHtml }));
});

app.get('/auditor/checks/:id', requireRole(2), (req, res) => {
  const checkId = req.params.id;
  const bodyHtml = `
    <section x-data="checkDetailsPage({ id: '${checkId}' })" x-init="load()" class="max-w-4xl">
      <a href="/auditor/checks" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-ustp-navy"><i data-lucide="arrow-left" class="size-4"></i>Back to checks</a>
      <template x-if="loading"><div class="h-52 animate-pulse rounded-2xl bg-white"></div></template>
      <template x-if="error"><div class="rounded-xl bg-red-50 p-4 text-sm text-red-700" x-text="error"></div></template>
      <template x-if="check">
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="font-heading text-2xl font-bold text-ustp-navy" x-text="check.checkCode"></h2>
          <dl class="mt-6 grid gap-5 sm:grid-cols-2">
            <template x-for="field in fields" :key="field.key">
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted" x-text="field.label"></dt>
                <dd class="mt-1 text-sm text-ustp-dark" x-text="check[field.key] || '—'"></dd>
              </div>
            </template>
          </dl>
          <div class="mt-6 border-t border-slate-100 pt-5">
            <h3 class="font-semibold text-ustp-navy">Remarks & Finding</h3>
            <p class="mt-2 whitespace-pre-wrap text-sm text-ustp-muted" x-text="check.remarks || 'No remarks provided.'"></p>
          </div>
        </article>
      </template>
    </section>
  `;
  res.send(renderLayout({ title: 'Check Details', heading: 'Check Details', user: req.user, currentPath: '/auditor/checks', bodyHtml }));
});

app.get('/auditor/check-create', requireRole(2), (req, res) => {
  const bodyHtml = `
    <section x-data="checkCreatePage()" class="max-w-3xl">
      <div class="mb-6">
        <h2 class="font-heading text-2xl font-bold text-ustp-navy">Create compliance check</h2>
        <p class="mt-1 text-sm text-ustp-muted">Record an assessment for an auditee in your assigned department.</p>
      </div>
      <template x-if="error"><div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700" x-text="error"></div></template>
      <template x-if="loading"><div class="h-64 animate-pulse rounded-2xl bg-white"></div></template>
      <form x-show="!loading" @submit.prevent="submit()" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <label class="block text-sm font-medium">Department
          <select x-model="departmentId" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
            <option value="">Choose a department</option>
            <template x-for="d in departments" :key="d._id"><option :value="d._id" x-text="d.departmentName"></option></template>
          </select>
        </label>
        <label class="block text-sm font-medium">Auditee
          <select x-model="auditeeId" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
            <option value="">Choose an auditee</option>
            <template x-for="a in auditees.filter(u => u.departmentId === departmentId)" :key="a._id">
              <option :value="a._id" x-text="a.fullName + (a.officeName ? ' · ' + a.officeName : '')"></option>
            </template>
          </select>
        </label>
        <label class="block text-sm font-medium">Reference standard
          <select x-model="referenceStandard" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
            <option value="ARTA-3days">ARTA 3-day rule</option>
            <option value="ARTA-7days">ARTA 7-day rule</option>
            <option value="ARTA-20days">ARTA 20-day rule</option>
            <option value="ISO9001-9.2">ISO 9001:2015 Clause 9.2</option>
          </select>
        </label>
        <fieldset>
          <legend class="text-sm font-medium">Result</legend>
          <div class="mt-3 flex flex-wrap gap-4">
            <label class="flex items-center gap-2 text-sm"><input x-model="result" type="radio" value="compliant" class="text-ustp-navy">Compliant</label>
            <label class="flex items-center gap-2 text-sm"><input x-model="result" type="radio" value="non_compliant" class="text-ustp-navy">Non-compliant</label>
          </div>
        </fieldset>
        <label class="block text-sm font-medium">Remarks <span class="font-normal text-ustp-muted">(optional)</span>
          <textarea x-model="remarks" rows="4" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></textarea>
        </label>
        <div class="flex justify-end gap-3">
          <a href="/auditor/checks" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold">Cancel</a>
          <button :disabled="saving" type="submit" class="rounded-xl bg-ustp-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy" x-text="saving ? 'Saving…' : 'Create check'"></button>
        </div>
      </form>
    </section>
  `;
  res.send(renderLayout({ title: 'New Compliance Check', heading: 'New Compliance Check', user: req.user, currentPath: '/auditor/check-create', bodyHtml }));
});

app.get('/auditor/archives', requireRole(2), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Archive Review',
    description: 'Review and verify evidence files uploaded by auditees.',
    endpoint: 'archives',
    allowArchiveActions: true,
    columns: [
      { key: 'fileCode', label: 'Archive Code' },
      { key: 'originalName', label: 'File Name' },
      { key: 'departmentName', label: 'Department' },
      { key: 'referenceStandard', label: 'Standard' },
      { key: 'uploaderName', label: 'Uploaded By' },
      { key: 'createdAt', label: 'Date' },
    ],
  });
  res.send(renderLayout({ title: 'Archives', heading: 'Archive Review', user: req.user, currentPath: '/auditor/archives', bodyHtml }));
});

app.get('/auditor/corrective-actions', requireRole(2), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Corrective Actions',
    description: 'Track open, in-progress, and closed corrective actions.',
    endpoint: 'compliance-checks',
    allowStatusUpdates: true,
    detailBase: 'auditor/checks',
    columns: [
      { key: 'checkCode', label: 'Check Code' },
      { key: 'departmentName', label: 'Department' },
      { key: 'auditeeName', label: 'Auditee' },
      { key: 'remarks', label: 'Finding' },
      { key: 'correctiveStatus', label: 'Status' },
    ],
  });
  res.send(renderLayout({ title: 'Corrective Actions', heading: 'Corrective Actions', user: req.user, currentPath: '/auditor/corrective-actions', bodyHtml }));
});

app.get('/auditor/notifications', requireRole(2), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Notifications',
    description: 'Updates on evidence uploads and corrective action requests.',
    endpoint: 'notifications',
    allowNotificationActions: true,
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'message', label: 'Message' },
      { key: 'createdAt', label: 'Date' },
    ],
  });
  res.send(renderLayout({ title: 'Notifications', heading: 'Notifications', user: req.user, currentPath: '/auditor/notifications', bodyHtml }));
});

// AUDITEE (Role 3)
app.get('/auditee/dashboard', requireRole(3), (req, res) => {
  const bodyHtml = renderDashboardComponent(3, 'My Dashboard', 'Your compliance findings, evidence, and recent updates.');
  res.send(renderLayout({ title: 'My Dashboard', heading: 'My Dashboard', user: req.user, currentPath: '/auditee/dashboard', bodyHtml }));
});

app.get('/auditee/my-findings', requireRole(3), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'My Findings',
    description: 'Compliance checks and corrective action status for your office.',
    endpoint: 'compliance-checks',
    detailBase: 'auditee/finding-details',
    columns: [
      { key: 'checkCode', label: 'Check Code' },
      { key: 'referenceStandard', label: 'Standard' },
      { key: 'result', label: 'Result' },
      { key: 'correctiveStatus', label: 'Status' },
      { key: 'dueDate', label: 'Due Date' },
    ],
  });
  res.send(renderLayout({ title: 'My Findings', heading: 'My Findings', user: req.user, currentPath: '/auditee/my-findings', bodyHtml }));
});

app.get('/auditee/finding-details/:id', requireRole(3), (req, res) => {
  const checkId = req.params.id;
  const bodyHtml = `
    <section x-data="checkDetailsPage({ id: '${checkId}' })" x-init="load()" class="max-w-4xl">
      <a href="/auditee/my-findings" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-ustp-navy"><i data-lucide="arrow-left" class="size-4"></i>Back to my findings</a>
      <template x-if="loading"><div class="h-52 animate-pulse rounded-2xl bg-white"></div></template>
      <template x-if="error"><div class="rounded-xl bg-red-50 p-4 text-sm text-red-700" x-text="error"></div></template>
      <template x-if="check">
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="font-heading text-2xl font-bold text-ustp-navy" x-text="check.checkCode"></h2>
          <dl class="mt-6 grid gap-5 sm:grid-cols-2">
            <template x-for="field in fields" :key="field.key">
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ustp-muted" x-text="field.label"></dt>
                <dd class="mt-1 text-sm text-ustp-dark" x-text="check[field.key] || '—'"></dd>
              </div>
            </template>
          </dl>
          <div class="mt-6 border-t border-slate-100 pt-5">
            <h3 class="font-semibold text-ustp-navy">Remarks</h3>
            <p class="mt-2 whitespace-pre-wrap text-sm text-ustp-muted" x-text="check.remarks || 'No remarks provided.'"></p>
          </div>
          <a href="/auditee/upload" class="mt-6 inline-flex rounded-xl bg-ustp-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy">Upload Evidence</a>
        </article>
      </template>
    </section>
  `;
  res.send(renderLayout({ title: 'Finding Details', heading: 'Finding Details', user: req.user, currentPath: '/auditee/my-findings', bodyHtml }));
});

app.get('/auditee/upload', requireRole(3), (req, res) => {
  const bodyHtml = `
    <section x-data="evidenceUploadPage()" class="max-w-3xl">
      <div class="mb-6">
        <h2 class="font-heading text-2xl font-bold text-ustp-navy">Upload Corrective Evidence</h2>
        <p class="mt-1 text-sm text-ustp-muted">Select an open non-compliant finding and attach supporting evidence.</p>
      </div>
      <div x-cloak x-show="error || message" x-transition class="fixed right-6 top-6 z-[100] flex min-h-[52px] w-[calc(100%-3rem)] max-w-sm items-center rounded-lg border-l-4 bg-ustp-navy py-4 pr-5 text-[13px] font-medium leading-5 text-white shadow-xl" :class="error ? 'border-red-500' : 'border-ustp-gold'" style="padding-left: 24px">
        <span class="min-w-0 flex-1" x-text="error || message"></span>
      </div>
      <template x-if="loading"><div class="h-60 animate-pulse rounded-2xl bg-white"></div></template>
      <form x-show="!loading" @submit.prevent="submit()" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <label class="block text-sm font-medium">Finding
          <select x-model="selectedCheckId" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
            <option value="">Select a non-compliant check</option>
            <template x-for="c in checks" :key="c._id"><option :value="c._id" x-text="c.checkCode + ' · ' + c.referenceStandard"></option></template>
          </select>
        </label>
        <label class="block text-sm font-medium">Evidence File
          <input x-ref="fileInput" type="file" accept=".pdf,.docx,.png,.jpg,.jpeg" @change="file = $event.target.files[0] || null" required class="mt-2 block w-full rounded-xl border border-slate-200 p-3 text-sm">
          <span class="mt-2 block text-xs text-ustp-muted">PDF, DOCX, PNG, or JPG · maximum 20 MB</span>
        </label>
        <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm text-ustp-muted">
          <i data-lucide="lock-keyhole" class="mt-0.5 size-5 shrink-0 text-ustp-navy"></i>
          <span>File will be encrypted with AES-256-GCM when securely stored in the CET Archive.</span>
        </div>
        <button :disabled="saving" type="submit" class="rounded-xl bg-ustp-navy px-5 py-3 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy disabled:opacity-60" x-text="saving ? 'Uploading…' : 'Upload Evidence'"></button>
      </form>
    </section>
  `;
  res.send(renderLayout({ title: 'Upload Evidence', heading: 'Upload Evidence', user: req.user, currentPath: '/auditee/upload', bodyHtml }));
});

app.get('/auditee/archives', requireRole(3), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'My Archives',
    description: 'Evidence files uploaded by your office.',
    endpoint: 'archives',
    allowArchiveActions: true,
    columns: [
      { key: 'fileCode', label: 'Archive Code' },
      { key: 'originalName', label: 'File Name' },
      { key: 'referenceStandard', label: 'Standard' },
      { key: 'createdAt', label: 'Date' },
    ],
  });
  res.send(renderLayout({ title: 'My Archives', heading: 'My Archives', user: req.user, currentPath: '/auditee/archives', bodyHtml }));
});

app.get('/auditee/notifications', requireRole(3), (req, res) => {
  const bodyHtml = renderResourcePageComponent({
    title: 'Notifications',
    description: 'Auditor updates, CAR status changes, and administrative announcements.',
    endpoint: 'notifications',
    allowNotificationActions: true,
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'message', label: 'Message' },
      { key: 'createdAt', label: 'Date' },
    ],
  });
  res.send(renderLayout({ title: 'Notifications', heading: 'Notifications', user: req.user, currentPath: '/auditee/notifications', bodyHtml }));
});

app.get('/auditee/help', requireRole(3), (req, res) => {
  const bodyHtml = `
    <section>
      <div class="mb-6">
        <h2 class="font-heading text-2xl font-bold text-ustp-navy">Reference Standards</h2>
        <p class="mt-1 text-sm text-ustp-muted">Quick reference for the compliance standards applied in CompliTrack audits.</p>
      </div>
      <div class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <span class="inline-flex rounded-lg bg-ustp-navy/5 p-2 text-ustp-navy"><i data-lucide="landmark" class="size-5"></i></span>
          <h3 class="mt-4 font-heading text-lg font-bold text-ustp-dark">ARTA Citizen’s Charter (RA 11032)</h3>
          <ul class="mt-3 space-y-2 text-sm text-ustp-muted">
            <li><strong>ARTA 3-day rule:</strong> Simple transactions must be completed within 3 working days.</li>
            <li><strong>ARTA 7-day rule:</strong> Complex transactions must be completed within 7 working days.</li>
            <li><strong>ARTA 20-day rule:</strong> Technical/highly complex transactions completed within 20 working days.</li>
          </ul>
        </article>
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <span class="inline-flex rounded-lg bg-ustp-navy/5 p-2 text-ustp-navy"><i data-lucide="badge-check" class="size-5"></i></span>
          <h3 class="mt-4 font-heading text-lg font-bold text-ustp-dark">ISO 9001:2015 Clause 9.2 &amp; 10.2</h3>
          <p class="mt-3 text-sm leading-6 text-ustp-muted">Clause 9.2 governs internal quality audits to verify conformity with planned requirements. Clause 10.2 governs corrective actions and root-cause non-conformity resolutions.</p>
        </article>
      </div>
    </section>
  `;
  res.send(
    renderLayout({
      title: 'Help & Standards',
      heading: 'Help & Standards',
      user: req.user,
      currentPath: '/auditee/help',
      bodyHtml,
    })
  );
});

// ==========================================
// MOBILE EMULATOR (Flutter App Simulation)
// ==========================================

function renderMobileEmulatorPage() {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CompliTrack Mobile Emulator | USTP Claveria</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
  ${viteAssetTag('resources/css/app.css')}
  <style>
    /* Smartphone Chassis Styling */
    .phone-chassis {
      width: 385px;
      height: 780px;
      background: #111827;
      border-radius: 50px;
      padding: 12px;
      box-shadow: 0 25px 60px -15px rgba(0, 32, 91, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1), inset 0 0 0 2px #374151;
      position: relative;
    }
    .phone-screen {
      width: 100%;
      height: 100%;
      background: #F8FAFC;
      border-radius: 40px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      position: relative;
    }
    .phone-notch {
      width: 120px;
      height: 24px;
      background: #111827;
      border-radius: 0 0 14px 14px;
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      z-index: 50;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .notch-camera {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: #1f2937;
      box-shadow: inset 0 0 2px #3b82f6;
    }
    .notch-speaker {
      width: 40px;
      height: 3px;
      border-radius: 2px;
      background: #374151;
    }
    .home-indicator {
      width: 120px;
      height: 4px;
      background: #cbd5e1;
      border-radius: 2px;
      margin: 6px auto;
    }
  </style>
</head>
<body class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans antialiased" x-data="mobileApp()" x-init="init()">
  <!-- Top Bar -->
  <header class="border-b border-slate-800 bg-slate-950/80 px-6 py-3 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="flex size-9 items-center justify-center rounded-xl bg-ustp-gold text-ustp-navy font-black text-sm">CT</div>
      <div>
        <h1 class="font-heading text-sm font-bold text-white flex items-center gap-2">
          CompliTrack Mobile Emulator
          <span class="rounded bg-ustp-gold/20 px-2 py-0.5 text-[10px] font-bold text-ustp-gold border border-ustp-gold/40">Flutter 3.24+ UI</span>
        </h1>
        <p class="text-[11px] text-slate-400">Interactive simulation of the mobile client (USTP Claveria CET)</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 bg-slate-800 rounded-xl p-1 text-xs">
        <span class="px-2 text-slate-400 font-semibold">Active Role:</span>
        <button type="button" @click="setRole(2)" :class="activeRole === 2 ? 'bg-ustp-gold text-ustp-navy font-bold shadow' : 'text-slate-300 hover:text-white'" class="rounded-lg px-2.5 py-1 transition">Auditor</button>
        <button type="button" @click="setRole(3)" :class="activeRole === 3 ? 'bg-ustp-gold text-ustp-navy font-bold shadow' : 'text-slate-300 hover:text-white'" class="rounded-lg px-2.5 py-1 transition">Auditee</button>
        <button type="button" @click="setRole(1)" :class="activeRole === 1 ? 'bg-ustp-gold text-ustp-navy font-bold shadow' : 'text-slate-300 hover:text-white'" class="rounded-lg px-2.5 py-1 transition">Admin</button>
        <button type="button" @click="setRole(0)" :class="activeRole === 0 ? 'bg-ustp-gold text-ustp-navy font-bold shadow' : 'text-slate-300 hover:text-white'" class="rounded-lg px-2.5 py-1 transition">Super Admin</button>
      </div>
      <a href="/login" class="flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-700">
        <i data-lucide="monitor" class="size-4 text-ustp-gold"></i>
        <span>Desktop Portal</span>
      </a>
    </div>
  </header>

  <!-- Emulator Canvas -->
  <main class="flex-1 flex items-center justify-center p-6 overflow-y-auto">
    <div class="phone-chassis">
      <div class="phone-notch">
        <div class="notch-speaker"></div>
        <div class="notch-camera"></div>
      </div>

      <div class="phone-screen text-ustp-dark">
        <!-- Status Bar -->
        <div class="pt-3 px-6 pb-1 flex items-center justify-between text-[11px] font-semibold text-slate-700 select-none z-40 bg-white border-b border-slate-100">
          <span>9:41</span>
          <div class="flex items-center gap-1.5 text-slate-600">
            <i data-lucide="signal" class="size-3"></i>
            <span class="text-[9px] font-bold">5G</span>
            <i data-lucide="wifi" class="size-3"></i>
            <i data-lucide="battery" class="size-3.5"></i>
          </div>
        </div>

        <!-- UstpAppBar (Matches mobile/lib/presentation/widgets/ustp_app_bar.dart) -->
        <div class="bg-ustp-navy px-4 py-2.5 flex items-center justify-between text-white shadow-sm z-30">
          <div class="flex items-center gap-2">
            <img src="/images/ustp-claveria-logo.jpg" alt="USTP" class="size-7 rounded-full object-cover ring-1 ring-ustp-gold">
            <div>
              <div class="font-heading text-xs font-bold leading-tight">Compli<span class="text-ustp-gold">Track</span></div>
              <div class="text-[8px] tracking-wider text-slate-300 font-medium">USTP CLAVERIA · CET</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" @click="currentTab = 'notifications'" class="relative p-1 text-slate-200 hover:text-white" aria-label="Notifications">
              <i data-lucide="bell" class="size-4"></i>
              <span x-show="unreadCount > 0" class="absolute -top-1 -right-1 flex size-3.5 items-center justify-center rounded-full bg-red-600 text-[8px] font-bold" x-text="unreadCount"></span>
            </button>
          </div>
        </div>

        <!-- Dynamic Screen Content -->
        <div class="flex-1 overflow-y-auto bg-slate-50 relative select-none">
          
          <!-- TAB: DASHBOARD -->
          <div x-show="currentTab === 'dashboard'" class="p-3.5 space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-[10px] uppercase font-bold tracking-wider text-ustp-muted">Performance Overview</p>
                <h2 class="font-heading text-sm font-bold text-ustp-navy" x-text="activeUser.fullName"></h2>
              </div>
              <span class="rounded-full bg-ustp-gold/20 text-ustp-navy text-[10px] font-bold px-2 py-0.5" x-text="roleTitle"></span>
            </div>

            <!-- Auditor Top Metric Cards -->
            <div class="grid grid-cols-2 gap-2">
              <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-2">
                <div class="size-7 rounded-lg bg-ustp-navy/10 flex items-center justify-center text-ustp-navy"><i data-lucide="clipboard-check" class="size-4"></i></div>
                <div>
                  <p class="text-[8px] text-ustp-muted font-bold">TOTAL CHECKS</p>
                  <p class="text-base font-bold font-heading text-ustp-dark" x-text="checks.length"></p>
                </div>
              </div>
              <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-2">
                <div class="size-7 rounded-lg bg-red-50 flex items-center justify-center text-red-600"><i data-lucide="circle-alert" class="size-4"></i></div>
                <div>
                  <p class="text-[8px] text-ustp-muted font-bold">OPEN CARs</p>
                  <p class="text-base font-bold font-heading text-red-600" x-text="openCarsCount"></p>
                </div>
              </div>
            </div>

            <!-- Circular Gauge Card (CircularPercentIndicator in Flutter) -->
            <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs">
              <p class="text-[11px] font-bold font-heading text-ustp-dark mb-2">Compliant vs Non-Compliant</p>
              <div class="flex items-center gap-4">
                <div class="relative size-18 flex items-center justify-center">
                  <svg class="size-16 -rotate-90" viewBox="0 0 36 36">
                    <path class="text-red-500" stroke-width="4" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="text-emerald-500 transition-all duration-500" stroke-width="4" :stroke-dasharray="compliantPercent + ', 100'" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-xs font-bold text-ustp-navy" x-text="checks.length"></span>
                    <span class="text-[7px] text-ustp-muted font-bold">TOTAL</span>
                  </div>
                </div>
                <div class="space-y-1 text-xs">
                  <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <span class="text-[11px] font-semibold text-ustp-dark" x-text="compliantCount + ' Compliant'"></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-red-500"></span>
                    <span class="text-[11px] font-semibold text-ustp-dark" x-text="nonCompliantCount + ' Non-Compliant'"></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Quick Action Button -->
            <template x-if="activeRole === 2">
              <button type="button" @click="showNewCheckModal = true" class="w-full flex items-center justify-center gap-2 rounded-xl bg-ustp-gold py-2.5 text-xs font-bold text-ustp-navy shadow-sm hover:bg-amber-400">
                <i data-lucide="plus" class="size-4"></i>
                <span>Create Compliance Check</span>
              </button>
            </template>
            <template x-if="activeRole === 3">
              <button type="button" @click="currentTab = 'upload'" class="w-full flex items-center justify-center gap-2 rounded-xl bg-ustp-navy py-2.5 text-xs font-bold text-white shadow-sm hover:bg-ustp-gold hover:text-ustp-navy">
                <i data-lucide="upload-cloud" class="size-4"></i>
                <span>Upload Corrective Evidence</span>
              </button>
            </template>

            <!-- Recent Status Changes -->
            <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs">
              <p class="text-[11px] font-bold font-heading text-ustp-dark mb-2">Recent Status Changes</p>
              <div class="space-y-2">
                <template x-for="chk in checks.slice(0, 3)" :key="chk._id">
                  <div @click="selectedCheck = chk; showDetailModal = true" class="p-2 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between text-left cursor-pointer hover:bg-slate-100">
                    <div class="min-w-0 pr-2">
                      <p class="text-[10px] font-bold text-ustp-navy truncate" x-text="chk.checkCode"></p>
                      <p class="text-[9px] text-ustp-muted truncate" x-text="chk.remarks || 'No remarks.'"></p>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded capitalize shrink-0" :class="chk.result === 'compliant' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'" x-text="(chk.correctiveStatus || chk.result).replace('_', ' ')"></span>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <!-- TAB: CHECKS / FINDINGS -->
          <div x-show="currentTab === 'checks'" class="p-3.5 space-y-3">
            <div class="flex items-center justify-between">
              <h2 class="font-heading text-sm font-bold text-ustp-navy" x-text="activeRole === 3 ? 'My Findings' : 'Compliance Checks'"></h2>
              <span class="text-[10px] text-ustp-muted font-semibold" x-text="checks.length + ' items'"></span>
            </div>
            <div class="space-y-2">
              <template x-for="chk in checks" :key="chk._id">
                <div @click="selectedCheck = chk; showDetailModal = true" class="p-3 rounded-xl bg-white border border-slate-200 shadow-xs cursor-pointer hover:border-ustp-navy">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] font-bold text-ustp-navy" x-text="chk.checkCode"></span>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full capitalize" :class="chk.result === 'compliant' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'" x-text="(chk.correctiveStatus || chk.result).replace('_', ' ')"></span>
                  </div>
                  <p class="text-[10px] text-slate-700 line-clamp-2" x-text="chk.remarks"></p>
                  <div class="mt-2 flex items-center justify-between text-[9px] text-ustp-muted border-t border-slate-100 pt-1.5">
                    <span x-text="chk.departmentName"></span>
                    <span class="font-semibold text-ustp-navy" x-text="chk.referenceStandard"></span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- TAB: CORRECTIVE ACTIONS (Auditor) -->
          <div x-show="currentTab === 'corrective'" class="p-3.5 space-y-3">
            <h2 class="font-heading text-sm font-bold text-ustp-navy">Corrective Action Board</h2>
            <div class="space-y-2.5">
              <template x-for="chk in checks.filter(c => c.result === 'non_compliant')" :key="chk._id">
                <div class="p-3 rounded-xl bg-white border border-slate-200 shadow-xs">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] font-bold text-ustp-navy" x-text="chk.checkCode"></span>
                    <select :value="chk.correctiveStatus" @change="updateCheckStatus(chk, $event.target.value)" class="text-[10px] font-bold rounded-lg border border-slate-200 px-1.5 py-1 bg-slate-50">
                      <option value="open">Open</option>
                      <option value="in_progress">In Progress</option>
                      <option value="closed">Closed</option>
                    </select>
                  </div>
                  <p class="text-[10px] text-slate-600 line-clamp-2 mt-1" x-text="chk.remarks"></p>
                  <p class="text-[9px] text-ustp-muted mt-2">Auditee: <span class="font-semibold text-slate-700" x-text="chk.auditeeName"></span></p>
                </div>
              </template>
            </div>
          </div>

          <!-- TAB: UPLOAD EVIDENCE (Auditee) -->
          <div x-show="currentTab === 'upload'" class="p-3.5 space-y-3">
            <h2 class="font-heading text-sm font-bold text-ustp-navy">Upload Evidence</h2>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs space-y-3">
              <div>
                <label class="block text-[10px] font-bold text-ustp-muted mb-1">SELECT FINDING</label>
                <select x-model="uploadCheckId" class="w-full text-xs rounded-lg border border-slate-200 p-2 bg-slate-50">
                  <option value="">Choose finding</option>
                  <template x-for="chk in checks.filter(c => c.result === 'non_compliant')" :key="chk._id">
                    <option :value="chk._id" x-text="chk.checkCode + ' · ' + chk.referenceStandard"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-ustp-muted mb-1">ATTACH DOCUMENT</label>
                <input type="text" x-model="uploadFileName" placeholder="e.g. ActionPlan_Evidence.pdf" class="w-full text-xs rounded-lg border border-slate-200 p-2 bg-slate-50">
                <span class="text-[9px] text-slate-400 mt-1 block">Supports PDF, DOCX, PNG, JPG (max 20MB)</span>
              </div>
              <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100 flex items-start gap-2 text-[9px] text-slate-600">
                <i data-lucide="lock-keyhole" class="size-3.5 text-ustp-navy shrink-0 mt-0.5"></i>
                <span>File is encrypted with AES-256-GCM and hashed with SHA-256 upon upload.</span>
              </div>
              <button type="button" @click="submitEvidence()" :disabled="uploadSubmitting" class="w-full rounded-xl bg-ustp-navy py-2 text-xs font-bold text-white shadow hover:bg-ustp-gold hover:text-ustp-navy disabled:opacity-50">
                <span x-text="uploadSubmitting ? 'Uploading…' : 'Submit Evidence File'"></span>
              </button>
            </div>
          </div>

          <!-- TAB: NOTIFICATIONS -->
          <div x-show="currentTab === 'notifications'" class="p-3.5 space-y-2">
            <div class="flex items-center justify-between mb-1">
              <h2 class="font-heading text-sm font-bold text-ustp-navy">Notifications</h2>
              <button type="button" @click="markAllNotifsRead()" class="text-[10px] text-ustp-navy font-bold hover:underline">Mark all read</button>
            </div>
            <div class="space-y-2">
              <template x-for="n in notifications" :key="n._id">
                <div class="p-2.5 rounded-xl border transition" :class="n.isRead ? 'bg-white border-slate-200' : 'bg-blue-50/70 border-blue-200'">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-[11px] font-bold text-slate-800" x-text="n.title"></p>
                    <span x-show="!n.isRead" class="size-2 rounded-full bg-blue-600 shrink-0 mt-1"></span>
                  </div>
                  <p class="text-[10px] text-slate-600 mt-0.5" x-text="n.message"></p>
                </div>
              </template>
            </div>
          </div>

          <!-- TAB: PROFILE -->
          <div x-show="currentTab === 'profile'" class="p-3.5 space-y-3">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs text-center">
              <div class="size-16 rounded-full bg-ustp-gold mx-auto flex items-center justify-center font-bold text-xl text-ustp-navy shadow ring-4 ring-slate-50">
                <span x-text="activeUser.fullName.split(' ').map(n=>n[0]).join('').slice(0,2)"></span>
              </div>
              <h3 class="font-heading text-sm font-bold text-ustp-dark mt-2" x-text="activeUser.fullName"></h3>
              <p class="text-[10px] text-ustp-muted" x-text="activeUser.email"></p>
              <span class="inline-block mt-2 rounded-full bg-ustp-navy text-white text-[9px] font-bold px-2.5 py-0.5" x-text="roleTitle"></span>
            </div>
            <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-xs space-y-2 text-xs">
              <div class="flex justify-between border-b border-slate-100 pb-1.5"><span class="text-ustp-muted">User Code</span><span class="font-bold text-ustp-navy" x-text="activeUser.userCode"></span></div>
              <div class="flex justify-between border-b border-slate-100 pb-1.5"><span class="text-ustp-muted">Office</span><span class="font-semibold text-slate-700" x-text="activeUser.officeName || 'CET Office'"></span></div>
              <div class="flex justify-between"><span class="text-ustp-muted">Status</span><span class="font-bold text-emerald-600">● Active</span></div>
            </div>
          </div>

        </div>

        <!-- Detail Modal inside Phone -->
        <div x-cloak x-show="showDetailModal" class="absolute inset-0 bg-slate-950/60 z-50 flex flex-col justify-end p-2">
          <div class="bg-white rounded-2xl p-4 space-y-3 max-h-[85%] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between">
              <h3 class="font-heading text-xs font-bold text-ustp-navy" x-text="selectedCheck?.checkCode"></h3>
              <button type="button" @click="showDetailModal = false" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="size-4"></i></button>
            </div>
            <div class="space-y-1.5 text-[10px]">
              <div class="flex justify-between"><span class="text-ustp-muted font-bold">Standard</span><span class="font-semibold text-ustp-navy" x-text="selectedCheck?.referenceStandard"></span></div>
              <div class="flex justify-between"><span class="text-ustp-muted font-bold">Department</span><span x-text="selectedCheck?.departmentName"></span></div>
              <div class="flex justify-between"><span class="text-ustp-muted font-bold">Auditee</span><span x-text="selectedCheck?.auditeeName"></span></div>
              <div class="flex justify-between"><span class="text-ustp-muted font-bold">Auditor</span><span x-text="selectedCheck?.auditorName"></span></div>
              <div class="flex justify-between"><span class="text-ustp-muted font-bold">Status</span><span class="capitalize font-bold text-ustp-navy" x-text="selectedCheck?.correctiveStatus || selectedCheck?.result"></span></div>
            </div>
            <div class="border-t border-slate-100 pt-2">
              <p class="text-[9px] font-bold text-ustp-muted">Remarks</p>
              <p class="text-[10px] text-slate-700 mt-1" x-text="selectedCheck?.remarks || 'No remarks provided.'"></p>
            </div>
            <button type="button" @click="showDetailModal = false" class="w-full rounded-xl bg-slate-100 py-2 text-xs font-bold text-ustp-dark hover:bg-slate-200">Close</button>
          </div>
        </div>

        <!-- New Check Modal inside Phone -->
        <div x-cloak x-show="showNewCheckModal" class="absolute inset-0 bg-slate-950/60 z-50 flex flex-col justify-end p-2">
          <div class="bg-white rounded-2xl p-4 space-y-2.5 max-h-[90%] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between">
              <h3 class="font-heading text-xs font-bold text-ustp-navy">New Compliance Check</h3>
              <button type="button" @click="showNewCheckModal = false" class="p-1 text-slate-400"><i data-lucide="x" class="size-4"></i></button>
            </div>
            <div class="space-y-2 text-[10px]">
              <div>
                <label class="font-bold text-ustp-muted block mb-0.5">Department</label>
                <select x-model="newCheckDept" class="w-full rounded-lg border border-slate-200 p-1.5 text-xs bg-slate-50">
                  <option value="dept-1">Information Technology</option>
                  <option value="dept-2">Engineering</option>
                </select>
              </div>
              <div>
                <label class="font-bold text-ustp-muted block mb-0.5">Standard</label>
                <select x-model="newCheckStandard" class="w-full rounded-lg border border-slate-200 p-1.5 text-xs bg-slate-50">
                  <option value="ARTA-3days">ARTA 3-day rule</option>
                  <option value="ARTA-7days">ARTA 7-day rule</option>
                  <option value="ISO9001-9.2">ISO 9001:2015 Clause 9.2</option>
                </select>
              </div>
              <div>
                <label class="font-bold text-ustp-muted block mb-0.5">Result</label>
                <div class="flex gap-4">
                  <label class="flex items-center gap-1 text-xs"><input type="radio" x-model="newCheckResult" value="compliant"> Compliant</label>
                  <label class="flex items-center gap-1 text-xs"><input type="radio" x-model="newCheckResult" value="non_compliant"> Non-Compliant</label>
                </div>
              </div>
              <div>
                <label class="font-bold text-ustp-muted block mb-0.5">Remarks</label>
                <textarea x-model="newCheckRemarks" rows="2" class="w-full rounded-lg border border-slate-200 p-1.5 text-xs bg-slate-50" placeholder="Audit observations..."></textarea>
              </div>
            </div>
            <div class="flex gap-2 pt-1">
              <button type="button" @click="showNewCheckModal = false" class="flex-1 rounded-xl border border-slate-200 py-1.5 text-xs font-semibold">Cancel</button>
              <button type="button" @click="submitNewCheck()" class="flex-1 rounded-xl bg-ustp-navy py-1.5 text-xs font-bold text-white hover:bg-ustp-gold hover:text-ustp-navy">Save</button>
            </div>
          </div>
        </div>

        <!-- Flutter Bottom Navigation Shell -->
        <nav class="bg-white border-t border-slate-200 px-2 py-1.5 flex items-center justify-around z-30 shadow-xs">
          <button type="button" @click="currentTab = 'dashboard'" :class="currentTab === 'dashboard' ? 'text-ustp-navy font-bold' : 'text-slate-400'" class="flex flex-col items-center gap-0.5">
            <i data-lucide="layout-dashboard" class="size-4"></i>
            <span class="text-[8px]">Home</span>
          </button>
          <button type="button" @click="currentTab = 'checks'" :class="currentTab === 'checks' ? 'text-ustp-navy font-bold' : 'text-slate-400'" class="flex flex-col items-center gap-0.5">
            <i data-lucide="clipboard-check" class="size-4"></i>
            <span class="text-[8px]" x-text="activeRole === 3 ? 'Findings' : 'Checks'"></span>
          </button>
          <template x-if="activeRole === 2">
            <button type="button" @click="currentTab = 'corrective'" :class="currentTab === 'corrective' ? 'text-ustp-navy font-bold' : 'text-slate-400'" class="flex flex-col items-center gap-0.5">
              <i data-lucide="kanban" class="size-4"></i>
              <span class="text-[8px]">CARs</span>
            </button>
          </template>
          <template x-if="activeRole === 3">
            <button type="button" @click="currentTab = 'upload'" :class="currentTab === 'upload' ? 'text-ustp-navy font-bold' : 'text-slate-400'" class="flex flex-col items-center gap-0.5">
              <i data-lucide="upload-cloud" class="size-4"></i>
              <span class="text-[8px]">Upload</span>
            </button>
          </template>
          <button type="button" @click="currentTab = 'notifications'" :class="currentTab === 'notifications' ? 'text-ustp-navy font-bold' : 'text-slate-400'" class="flex flex-col items-center gap-0.5">
            <i data-lucide="bell" class="size-4"></i>
            <span class="text-[8px]">Alerts</span>
          </button>
          <button type="button" @click="currentTab = 'profile'" :class="currentTab === 'profile' ? 'text-ustp-navy font-bold' : 'text-slate-400'" class="flex flex-col items-center gap-0.5">
            <i data-lucide="user" class="size-4"></i>
            <span class="text-[8px]">Profile</span>
          </button>
        </nav>

        <!-- Home Bar -->
        <div class="bg-white pb-1 pt-0.5">
          <div class="home-indicator"></div>
        </div>
      </div>
    </div>
  </main>

  ${viteAssetTag('resources/js/app.js')}
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('mobileApp', () => ({
        activeRole: 2,
        currentTab: 'dashboard',
        checks: [],
        notifications: [],
        selectedCheck: null,
        showDetailModal: false,
        showNewCheckModal: false,
        uploadCheckId: '',
        uploadFileName: '',
        uploadSubmitting: false,
        newCheckDept: 'dept-1',
        newCheckStandard: 'ARTA-3days',
        newCheckResult: 'compliant',
        newCheckRemarks: '',

        roleUsers: {
          0: { _id: 'user-0', fullName: 'Super Administrator', email: 'admin@example.edu', userCode: 'SA-0001', roleId: 0 },
          1: { _id: 'user-1', fullName: 'Engr. Maria Santos', email: 'demo.admin@example.test', userCode: 'ADM-0001', roleId: 1 },
          2: { _id: 'user-2', fullName: 'Prof. Alex Reyes', email: 'demo.auditor.it@example.test', userCode: 'AUD-0001', roleId: 2 },
          3: { _id: 'user-3', fullName: 'Dr. Roberto Cruz', email: 'demo.auditee.it@example.test', userCode: 'ATE-0001', roleId: 3 },
        },

        get activeUser() {
          return this.roleUsers[this.activeRole];
        },
        get roleTitle() {
          return ['Super Admin', 'Admin', 'Auditor', 'Auditee'][this.activeRole] || 'User';
        },
        get compliantCount() {
          return this.checks.filter(c => c.result === 'compliant').length;
        },
        get nonCompliantCount() {
          return this.checks.filter(c => c.result === 'non_compliant').length;
        },
        get openCarsCount() {
          return this.checks.filter(c => c.correctiveStatus === 'open').length;
        },
        get compliantPercent() {
          return this.checks.length ? Math.round((this.compliantCount / this.checks.length) * 100) : 0;
        },
        get unreadCount() {
          return this.notifications.filter(n => !n.isRead).length;
        },

        async init() {
          await this.loadData();
          this.$nextTick(() => window.renderIcons?.());
        },

        async setRole(roleId) {
          this.activeRole = roleId;
          this.currentTab = 'dashboard';
          // Update cookie to simulate this user
          document.cookie = 'complitrack_user_id=' + this.activeUser._id + '; path=/; max-age=86400';
          await this.loadData();
        },

        async loadData() {
          try {
            const [chkRes, notifRes] = await Promise.all([
              fetch('/api/v1/compliance-checks?perPage=50').then(r => r.json()),
              fetch('/api/v1/notifications?perPage=20').then(r => r.json()),
            ]);
            this.checks = chkRes.data || [];
            this.notifications = notifRes.data || [];
          } catch (e) {
            console.error('Failed to load mobile data:', e);
          }
          this.$nextTick(() => window.renderIcons?.());
        },

        async updateCheckStatus(check, newStatus) {
          try {
            await fetch('/api/v1/compliance-checks/' + encodeURIComponent(check._id) + '/status', {
              method: 'PATCH',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ correctiveStatus: newStatus, remarks: 'Updated via Mobile App.' })
            });
            check.correctiveStatus = newStatus;
            await this.loadData();
          } catch (e) {
            alert('Failed to update status.');
          }
        },

        async submitNewCheck() {
          try {
            await fetch('/api/v1/compliance-checks', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                departmentId: this.newCheckDept,
                auditeeId: 'user-3',
                referenceStandard: this.newCheckStandard,
                result: this.newCheckResult,
                remarks: this.newCheckRemarks || 'Recorded via Mobile App.',
              })
            });
            this.showNewCheckModal = false;
            this.newCheckRemarks = '';
            await this.loadData();
          } catch (e) {
            alert('Failed to create check.');
          }
        },

        async submitEvidence() {
          if (!this.uploadCheckId) { alert('Please choose a non-compliant finding.'); return; }
          this.uploadSubmitting = true;
          try {
            await fetch('/api/v1/archives/upload', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                checkId: this.uploadCheckId,
              })
            });
            alert('Evidence file uploaded & encrypted successfully.');
            this.uploadCheckId = '';
            this.uploadFileName = '';
            this.currentTab = 'checks';
            await this.loadData();
          } catch (e) {
            alert('Failed to upload evidence.');
          } finally {
            this.uploadSubmitting = false;
          }
        },

        async markAllNotifsRead() {
          await fetch('/api/v1/notifications/read-all', { method: 'PATCH' });
          this.notifications.forEach(n => n.isRead = true);
        }
      }));
    });
  </script>
</body>
</html>`;
}

app.get('/mobile', (req, res) => {
  res.send(renderMobileEmulatorPage());
});

// 404 Fallback
app.use((req, res) => {
  res.status(404).send(
    renderLayout({
      title: 'Page Not Found',
      user: getCurrentUser(req),
      currentPath: req.path,
      bodyHtml: `
        <div class="py-16 text-center">
          <h2 class="font-heading text-4xl font-bold text-ustp-navy">404</h2>
          <p class="mt-2 text-slate-500">Page not found.</p>
          <a href="/" class="mt-4 inline-block rounded-xl bg-ustp-navy px-4 py-2 text-sm font-semibold text-white">Back to Home</a>
        </div>
      `,
    })
  );
});

// ==========================================
// START SERVER
// ==========================================

app.listen(PORT, HOST, () => {
  console.log(`[CompliTrack] Server running on http://${HOST}:${PORT}`);
});
