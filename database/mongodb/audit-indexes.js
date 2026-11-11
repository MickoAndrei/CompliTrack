// Select the intended application database in MongoDB Compass/mongosh first.
// Additive only: no record updates, deletes, seeding, or index drops.
// Review duplicate auditCode/assignmentKey values before executing.
db.audits.createIndex({ auditCode: 1 }, { unique: true, name: "audits_code_unique" });
db.audits.createIndex({ assignmentKey: 1 }, { unique: true, sparse: true, name: "audits_assignment_key_unique" });
db.audits.createIndex({ auditorId: 1, departmentId: 1, createdAt: -1 }, { name: "audits_auditor_department" });
db.audits.createIndex({ reviewerId: 1, status: 1 }, { name: "audits_reviewer_status" });
