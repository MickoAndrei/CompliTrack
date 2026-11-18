import 'package:complitrack/data/repositories/super_admin_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('uses accepted-audit compliance instead of legacy check percentages',
      () async {
    final dio = Dio();
    addTearDown(() => dio.close());
    var percentage = 66.7;
    dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      final data = options.path == '/api/v1/reports'
          ? {
              'totalUsers': 10,
              'totalDepts': 3,
              'storageUsedGb': 1,
              'activeAudits': 2,
              'deptCompliance': [
                {'departmentCode': 'CET-ABE', 'compliancePercentage': 0},
              ],
            }
          : {
              'departmentCompliance': [
                {'code': 'CET-ABE', 'percentage': percentage},
                {'code': 'CET-ENV', 'percentage': null},
                {'code': 'CET-FPT', 'percentage': 0},
              ],
            };
      handler.resolve(Response(
          requestOptions: options, statusCode: 200, data: {'data': data}));
    }));
    final repository = SuperAdminRepository(dio: dio);
    final metrics = await repository.getDashboardMetrics();
    expect(metrics.totalUsers, 10);
    expect(metrics.storageUsedLabel, '1.00 GB');
    expect(metrics.deptCompliance, hasLength(3));
    expect(metrics.deptCompliance.first.departmentCode, 'CET-ABE');
    expect(metrics.deptCompliance.first.compliancePercentage, 66.7);
    expect(metrics.deptCompliance[1].isAssessed, isFalse);
    expect(metrics.deptCompliance[2].isAssessed, isTrue);
    expect(metrics.deptCompliance[2].compliancePercentage, 0);

    percentage = 75;
    final refreshed = await repository.getDashboardMetrics();
    expect(refreshed.deptCompliance.first.compliancePercentage, 75);
  });

  test('rejects missing dashboard compliance instead of showing stale checks',
      () async {
    final dio = Dio();
    addTearDown(() => dio.close());
    dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(
          requestOptions: options, statusCode: 200, data: {'data': {}}));
    }));
    await expectLater(SuperAdminRepository(dio: dio).getDashboardMetrics(),
        throwsFormatException);
  });
}
