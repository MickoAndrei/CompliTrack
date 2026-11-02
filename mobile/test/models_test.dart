import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/data/models/compliance_check_model.dart';
import 'package:complitrack/data/models/super_admin_dashboard_model.dart';
import 'package:complitrack/data/models/user_model.dart';

void main() {
  group('UserModel', () {
    test('parses the API identity and role fields', () {
      final user = UserModel.fromJson({
        '_id': '66aabbccddeeff0011223344',
        'userCode': 'ATE-0001',
        'fullName': 'Alex Auditee',
        'email': 'alex@example.com',
        'roleId': 3,
        'roleSlug': 'auditee',
        'departmentId': '66aabbccddeeff0011223355',
        'officeName': 'CET',
        'isActive': true,
      });

      expect(user.id, '66aabbccddeeff0011223344');
      expect(user.roleId, 3);
      expect(user.roleSlug, 'auditee');
      expect(user.departmentId, '66aabbccddeeff0011223355');
    });
  });

  group('ComplianceCheckModel', () {
    test('parses optional corrective-action and linked archive fields', () {
      final check = ComplianceCheckModel.fromJson({
        '_id': '66aabbccddeeff0011223344',
        'checkCode': 'CHK-0001',
        'departmentId': '66aabbccddeeff0011223355',
        'auditorId': '66aabbccddeeff0011223366',
        'auditeeId': '66aabbccddeeff0011223377',
        'referenceStandard': 'ARTA-3days',
        'result': 'non_compliant',
        'correctiveStatus': 'open',
        'linkedArchiveIds': ['66aabbccddeeff0011223388'],
        'statusHistory': [
          {'status': 'open', 'remarks': 'Initial finding'},
        ],
        'createdAt': '2026-10-15T00:00:00Z',
      });

      expect(check.id, '66aabbccddeeff0011223344');
      expect(check.correctiveStatus, 'open');
      expect(check.linkedArchiveIds, ['66aabbccddeeff0011223388']);
      expect(check.statusHistory.single['status'], 'open');
    });
  });

  group('SuperAdminDashboardModel', () {
    test('parses storage usage returned as a numeric string', () {
      final metrics = SuperAdminDashboardModel.fromJson({
        'totalUsers': 10,
        'totalDepts': 2,
        'storageUsedGb': '3.42',
        'activeAudits': 4,
        'deptCompliance': [
          {
            'departmentCode': 'CET',
            'departmentName': 'College of Engineering',
            'totalChecks': 3,
            'compliancePercentage': 66,
          },
        ],
        'monthlyComplianceTrend': [
          {'month': 'Nov', 'compliancePercentage': 66},
        ],
      });

      expect(metrics.storageUsedGb, 3.42);
      expect(metrics.userGrowth, isEmpty);
      expect(metrics.deptCompliance.single.compliancePercentage, 66);
    });

    test('rejects invalid storage usage strings', () {
      expect(
        () => SuperAdminDashboardModel.fromJson({
          'totalUsers': 10,
          'totalDepts': 2,
          'storageUsedGb': 'unavailable',
          'activeAudits': 4,
          'deptCompliance': [],
          'userGrowth': [],
        }),
        throwsFormatException,
      );
    });
  });
}
