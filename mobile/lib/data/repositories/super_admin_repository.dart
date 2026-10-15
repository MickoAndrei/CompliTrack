import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/super_admin_dashboard_model.dart';

class SuperAdminRepository {
  final Dio _dio;

  SuperAdminRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<SuperAdminDashboardModel> getDashboardMetrics() async {
    final response = await _dio.get('/api/v1/reports');
    return SuperAdminDashboardModel.fromJson(ApiResponse.object(response.data));
  }
}
