import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/data/models/compliance_check_model.dart';
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
}