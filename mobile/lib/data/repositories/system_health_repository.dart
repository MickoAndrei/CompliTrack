import 'package:dio/dio.dart';
import '../../core/constants/api_constants.dart';
import '../../core/network/api_client.dart';

class SystemHealthStatus {
  final bool apiAvailable;
  final bool databaseAvailable;

  const SystemHealthStatus({
    required this.apiAvailable,
    required this.databaseAvailable,
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
    var databaseAvailable = false;

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
      final response = await ApiClient().dio.get(
        '/api/v1/system/health',
        options: Options(
          connectTimeout: const Duration(seconds: 4),
          receiveTimeout: const Duration(seconds: 4),
          sendTimeout: const Duration(seconds: 4),
        ),
      );
      final body = response.data;
      if (body is Map) databaseAvailable = body['database'] == 'ok';
    } on DioException {
      databaseAvailable = false;
    } catch (_) {
      databaseAvailable = false;
    }

    dio.close(force: true);
    return SystemHealthStatus(
      apiAvailable: apiAvailable,
      databaseAvailable: databaseAvailable,
    );
  }
}
