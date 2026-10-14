import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';

class ReportRepository {
  ReportRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  final Dio _dio;

  Future<Map<String, dynamic>> getReports({
    DateTime? startDate,
    DateTime? endDate,
  }) async {
    final response = await _dio.get(
      '/api/v1/reports',
      queryParameters: {
        if (startDate != null) 'startDate': startDate.toIso8601String(),
        if (endDate != null) 'endDate': endDate.toIso8601String(),
      },
    );
    return ApiResponse.object(response.data);
  }
}