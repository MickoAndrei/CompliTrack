import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/core/utils/role_helper.dart';

void main() {
  group('RoleHelper', () {
    test('maps the four role IDs to their plan values', () {
      expect(RoleHelper.getRoleSlug(0), 'super_admin');
      expect(RoleHelper.getRoleSlug(1), 'admin');
      expect(RoleHelper.getRoleSlug(2), 'auditor');
      expect(RoleHelper.getRoleSlug(3), 'auditee');
    });

    test('limits upload and status changes by role', () {
      expect(RoleHelper.canUpload(0), isFalse);
      expect(RoleHelper.canUpload(1), isFalse);
      expect(RoleHelper.canUpload(2), isTrue);
      expect(RoleHelper.canUpload(3), isTrue);
      expect(RoleHelper.canChangeStatus(2), isTrue);
      expect(RoleHelper.canChangeStatus(3), isFalse);
    });

    test('rejects unknown roles and sends them to login', () {
      expect(RoleHelper.hasMobileAccess(4), isFalse);
      expect(RoleHelper.getRoleSlug(4), 'unknown');
      expect(RoleHelper.getDashboardRoute(4), '/login');
    });
  });
}