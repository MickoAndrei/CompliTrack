import 'package:complitrack/data/repositories/system_health_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('reads all service states from the authenticated dashboard endpoint',
      () async {
    final dio = Dio();
    addTearDown(() => dio.close());
    var realtime = 'Operational';
    dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      expect(options.path, '/api/v1/system/dashboard/health');
      handler.resolve(Response(requestOptions: options, statusCode: 200, data: {
        'data': {
          'components': [
            {'name': 'Laravel', 'status': 'Operational'},
            {'name': 'Node.js Service', 'status': realtime},
            {'name': 'MongoDB Cluster', 'status': 'Unavailable'},
            {'name': 'FCM Push Service', 'status': 'Not Configured'},
          ],
          'checkedAt': '2026-11-18T08:00:00Z',
        },
      }));
    }));
    final repository = SystemHealthRepository(dio: dio);
    final status = await repository.check();
    expect(status.components['Laravel'], 'Operational');
    expect(status.components['Node.js Service'], 'Operational');
    expect(status.components['MongoDB Cluster'], 'Unavailable');
    expect(status.components['FCM Push Service'], 'Not Configured');
    expect(status.checkedAt, DateTime.utc(2026, 11, 18, 8));
    expect(status.error, isNull);
    realtime = 'Unavailable';
    expect((await repository.check()).components['Node.js Service'],
        'Unavailable');
  });

  test('does not report services as down when the request fails', () async {
    final dio = Dio();
    addTearDown(() => dio.close());
    dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.reject(DioException(
          requestOptions: options, type: DioExceptionType.connectionTimeout));
    }));
    final status = await SystemHealthRepository(dio: dio).check();
    expect(status.components.values, everyElement('Unknown'));
    expect(status.checkedAt, isNull);
    expect(status.error, isNotNull);
  });

  test('missing or unexpected component states remain unknown', () {
    final status = SystemHealthStatus.fromJson({
      'components': [
        {'name': 'Laravel', 'status': 'unexpected'},
        {'name': 'FCM Push Service', 'status': 'Configured'},
      ],
      'checkedAt': '2026-11-18T08:00:00Z',
    });
    expect(status.components['Laravel'], 'Unknown');
    expect(status.components['MongoDB Cluster'], 'Unknown');
    expect(status.components['FCM Push Service'], 'Configured');
    expect(() => SystemHealthStatus.fromJson({'components': []}),
        throwsFormatException);
  });
}
