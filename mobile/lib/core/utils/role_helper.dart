class RoleHelper {
  static const int roleSuperAdmin = 0;
  static const int roleAdmin = 1;
  static const int roleAuditor = 2;
  static const int roleAuditee = 3;

  static bool hasMobileAccess(int roleId) => roleId >= 0 && roleId <= 3;
  static bool canUpload(int roleId) => roleId == roleAuditor || roleId == roleAuditee;
  static bool canChangeStatus(int roleId) => roleId == roleAuditor;

  static String getRoleSlug(int roleId) => switch (roleId) {
    0 => 'super_admin',
    1 => 'admin',
    2 => 'auditor',
    3 => 'auditee',
    _ => 'unknown',
  };

  static String getRoleName(int roleId) => switch (roleId) {
    0 => 'Super Admin',
    1 => 'Admin',
    2 => 'Auditor',
    3 => 'Auditee',
    _ => 'Unknown',
  };

  static String getDashboardRoute(int roleId) => switch (roleId) {
    0 => '/super-admin',
    1 => '/admin',
    2 => '/auditor',
    3 => '/auditee',
    _ => '/login',
  };
}
