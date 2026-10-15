import 'package:dio/dio.dart';
import 'package:intl/intl.dart';
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
        if (startDate != null)
          'startDate': DateFormat('yyyy-MM-dd').format(startDate),
        if (endDate != null)
          'endDate': DateFormat('yyyy-MM-dd').format(endDate),
      },
    );
    return ApiResponse.object(response.data);
  }
}
