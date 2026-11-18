import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';

class SystemHealthStatus {
  static const serviceNames = [
    'Laravel',
    'Node.js Service',
    'MongoDB Cluster',
    'FCM Push Service'
  ];
  final Map<String, String> components;
  final DateTime? checkedAt;
  final String? error;

  const SystemHealthStatus({
    required this.components,
    this.checkedAt,
    this.error,
  });

  factory SystemHealthStatus.fromJson(Map<String, dynamic> json) {
    final rows = json['components'];
    final checkedAt = DateTime.tryParse('${json['checkedAt']}');
    if (rows is! List || checkedAt == null) {
      throw const FormatException('Invalid system health response.');
    }
    final components = {for (final name in serviceNames) name: 'Unknown'};
    for (final row in rows) {
      if (row is Map && components.containsKey(row['name'])) {
        final status = row['status'];
        if (const [
          'Operational',
          'Unavailable',
          'Configured',
          'Not Configured',
          'Unknown'
        ].contains(status)) {
          components[row['name'] as String] = status as String;
        }
      }
    }
    return SystemHealthStatus(components: components, checkedAt: checkedAt);
  }
}

class SystemHealthRepository {
  final Dio _dio;
  SystemHealthRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<SystemHealthStatus> check() async {
    try {
      final response = await _dio.get(
        '/api/v1/system/dashboard/health',
        options: Options(
          receiveTimeout: const Duration(seconds: 10),
          sendTimeout: const Duration(seconds: 10),
        ),
      );
      return SystemHealthStatus.fromJson(ApiResponse.object(response.data));
    } catch (_) {
      return SystemHealthStatus(
        components: {
          for (final name in SystemHealthStatus.serviceNames) name: 'Unknown'
        },
        error: 'Unable to check system health. Try again.',
      );
    }
  }
}
