import 'package:dio/dio.dart';
import '../models/super_admin_dashboard_model.dart';

class SuperAdminRepository {
  final Dio _dio;

  SuperAdminRepository({Dio? dio}) : _dio = dio ?? Dio();

  Future<SuperAdminDashboardModel> getDashboardMetrics() async {
    try {
      final response = await _dio.get('/api/v1/super-admin/dashboard');
      if (response.data != null) {
        return SuperAdminDashboardModel.fromJson(response.data as Map<String, dynamic>);
      }
    } catch (_) {
      // Offline fallback mock data
    }
    return SuperAdminDashboardModel.fromJson({});
  }
}
