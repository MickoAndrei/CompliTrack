import 'package:dio/dio.dart';
import '../../core/constants/api_constants.dart';

class SystemHealthStatus {
  final bool apiAvailable;
  final bool realtimeAvailable;
  final bool databaseAvailable;
  final bool? pushConfigured;

  const SystemHealthStatus({
    required this.apiAvailable,
    required this.realtimeAvailable,
    required this.databaseAvailable,
    required this.pushConfigured,
  });
}

class SystemHealthRepository {
  Future<SystemHealthStatus> check() async {
    final dio = Dio(BaseOptions(
      connectTimeout: const Duration(seconds: 4),
      receiveTimeout: const Duration(seconds: 4),
      sendTimeout: const Duration(seconds: 4),
      validateStatus: (status) => status != null && status < 600,
    ));

    var apiAvailable = false;
    var realtimeAvailable = false;
    var databaseAvailable = false;
    bool? pushConfigured;

    try {
      final response = await dio.get('${ApiConstants.baseUrl}/up');
      apiAvailable = response.statusCode != null &&
          response.statusCode! >= 200 &&
          response.statusCode! < 300;
    } on DioException {
      apiAvailable = false;
    } catch (_) {
      apiAvailable = false;
    }

    try {
      final response = await dio.get('${ApiConstants.realtimeUrl}/health');
      realtimeAvailable = response.statusCode != null &&
          response.statusCode! >= 200 &&
          response.statusCode! < 300;
      final body = response.data;
      if (body is Map) {
        databaseAvailable = body['database'] == 'ok' || body['status'] == 'ok';
        final push = body['pushConfigured'];
        if (push is bool) pushConfigured = push;
      }
    } on DioException {
      realtimeAvailable = false;
      databaseAvailable = false;
    } catch (_) {
      realtimeAvailable = false;
      databaseAvailable = false;
    }

    dio.close(force: true);
    return SystemHealthStatus(
      apiAvailable: apiAvailable,
      realtimeAvailable: realtimeAvailable,
      databaseAvailable: databaseAvailable,
      pushConfigured: pushConfigured,
    );
  }
}
