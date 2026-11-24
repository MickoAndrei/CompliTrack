// Run this script in MongoDB Compass > Playgrounds, connected to the same
// MongoDB deployment configured by MONGODB_URI. Collections are created empty.
const database = db.getSiblingDB("complitrack");

const collections = [
  "roles",
  "users",
  "departments",
  "compliance_checks",
  "audits",
  "archive_documents",
  "notifications",
  "audit_logs",
  "counters",
  "personal_access_tokens",
  "password_reset_tokens",
  "sessions",
  "cache",
  "jobs",
];

for (const name of collections) {
  if (!database.getCollectionNames().includes(name)) {
    database.createCollection(name);
  }
}

database.roles.createIndex({ roleId: 1 }, { unique: true, name: "roles_role_id_unique" });
database.users.createIndex({ email: 1 }, { unique: true, name: "users_email_unique" });
database.users.createIndex({ userCode: 1 }, { unique: true, name: "users_user_code_unique" });
database.users.createIndex({ roleId: 1, departmentId: 1 }, { name: "users_role_department" });
database.users.createIndex({ roleId: 1, assignedDepartmentIds: 1 }, { name: "users_role_assigned_departments" });
database.users.createIndex({ fullName: 1 }, { name: "users_full_name" });
database.departments.createIndex({ departmentCode: 1 }, { unique: true, name: "departments_code_unique" });
database.archive_documents.createIndex({ fileCode: 1 }, { unique: true, name: "archives_file_code_unique" });
database.archive_documents.createIndex({ departmentId: 1, uploadedByUserId: 1 }, { name: "archives_department_uploader" });
database.archive_documents.createIndex({ checksum: 1 }, { name: "archives_checksum" });
database.archive_documents.createIndex({ referenceStandard: 1, createdAt: -1 }, { name: "archives_standard_created" });
database.compliance_checks.createIndex({ checkCode: 1 }, { unique: true, name: "checks_code_unique" });
database.audits.createIndex({ auditCode: 1 }, { unique: true, name: "audits_code_unique" });
database.audits.createIndex({ assignmentKey: 1 }, { unique: true, sparse: true, name: "audits_assignment_key_unique" });
database.audits.createIndex({ auditorId: 1, departmentId: 1, createdAt: -1 }, { name: "audits_auditor_department" });
database.audits.createIndex({ reviewerId: 1, status: 1 }, { name: "audits_reviewer_status" });
database.audits.createIndex({ departmentId: 1, "evidenceRequests.auditeeId": 1, updatedAt: -1 }, { name: "audits_auditee_requests" });
database.compliance_checks.createIndex({ departmentId: 1, result: 1, correctiveStatus: 1 }, { name: "checks_department_result_status" });
database.compliance_checks.createIndex({ auditeeId: 1, correctiveStatus: 1 }, { name: "checks_auditee_status" });
database.compliance_checks.createIndex({ auditorId: 1, createdAt: -1 }, { name: "checks_auditor_created" });
database.notifications.createIndex({ recipientUserId: 1, isRead: 1, createdAt: -1 }, { name: "notifications_recipient_read_created" });
// The All inbox does not constrain isRead; this index preserves newest-first pagination.
database.notifications.createIndex({ recipientUserId: 1, createdAt: -1, _id: -1 }, { name: "notifications_recipient_created_id" });
database.notifications.createIndex({ createdAt: 1 }, { expireAfterSeconds: 15552000, name: "notifications_created_ttl" });
database.notifications.createIndex({ dedupeKey: 1 }, { unique: true, sparse: true, name: "notifications_dedupe_unique" });
database.notifications.createIndex({ realtimeDeliveredAt: 1, realtimeLeaseUntil: 1, createdAt: 1 }, { name: "notifications_realtime_pending" });
database.audit_logs.createIndex({ userId: 1, createdAt: -1 }, { name: "audit_logs_user_created" });
database.audit_logs.createIndex({ action: 1, createdAt: -1 }, { name: "audit_logs_action_created" });
database.audit_logs.createIndex({ createdAt: -1, _id: -1 }, { name: "audit_logs_created" });
database.audit_logs.createIndex({ category: 1, outcome: 1, createdAt: -1, _id: -1 }, { name: "audit_logs_category_outcome_created" });
database.personal_access_tokens.createIndex({ token: 1 }, { unique: true, name: "personal_access_tokens_token_unique" });
database.personal_access_tokens.createIndex({ tokenable_id: 1 }, { name: "personal_access_tokens_tokenable_id" });
database.password_reset_tokens.createIndex({ email: 1 }, { unique: true, name: "password_reset_tokens_email_unique" });
database.password_reset_tokens.createIndex({ createdAt: 1 }, { expireAfterSeconds: 600, name: "password_reset_tokens_created_ttl" });

print(`Initialized ${database.getName()} with ${collections.length} collections and indexes.`);
