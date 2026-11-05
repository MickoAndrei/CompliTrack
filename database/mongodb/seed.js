// Run in MongoDB Compass > Playgrounds after init.js.
// This imports the non-secret baseline data. Run `php artisan db:seed` to add
// the environment-configured Super Admin and optional demo records.
const database = db.getSiblingDB("complitrack");

const roles = [
  { roleId: 0, roleSlug: "super_admin", roleName: "Super Admin", permissions: ["*"] },
  { roleId: 1, roleSlug: "admin", roleName: "Admin", permissions: [] },
  { roleId: 2, roleSlug: "auditor", roleName: "Auditor", permissions: [] },
  { roleId: 3, roleSlug: "auditee", roleName: "Auditee", permissions: [] },
];

for (const role of roles) {
  database.roles.updateOne(
    { roleId: role.roleId },
    { $setOnInsert: { ...role, createdAt: new Date() } },
    { upsert: true }
  );
}

database.departments.updateOne(
  { departmentCode: "CET-IT" },
  {
    $setOnInsert: {
      departmentCode: "CET-IT",
      departmentName: "Information Technology",
      collegeName: "CET",
      campusName: "Claveria",
      headUserId: null,
      isActive: true,
      createdAt: new Date(),
    },
  },
  { upsert: true },
);

print("Imported baseline roles and department into complitrack.");
