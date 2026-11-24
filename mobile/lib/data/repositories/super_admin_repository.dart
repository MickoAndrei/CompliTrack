import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/super_admin_dashboard_model.dart';

class SuperAdminRepository {
  final Dio _dio;

  SuperAdminRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<SuperAdminDashboardModel> getDashboardMetrics() async {
    final responses = await Future.wait([
      _dio.get('/api/v1/reports'),
      _dio.get('/api/v1/system/dashboard'),
    ]);
    final report = ApiResponse.object(responses[0].data);
    final dashboard = ApiResponse.object(responses[1].data);
    if (dashboard['departmentCompliance'] is! List) {
      throw const FormatException('Missing department compliance data.');
    }
    return SuperAdminDashboardModel.fromJson({
      ...report,
      'departmentCompliance': dashboard['departmentCompliance'],
    });
  }
}
