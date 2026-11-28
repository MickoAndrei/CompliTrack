import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';

class AuditWorkspaceRepository {
  AuditWorkspaceRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;
  final Dio _dio;

  Future<Map<String, dynamic>> list({String search = '', String status = '', int page = 1}) async {
    final response = await _dio.get('/api/v1/audit-workspaces', queryParameters: {
      if (search.isNotEmpty) 'search': search,
      if (status.isNotEmpty) 'status': status,
      'page': page, 'perPage': 12,
    });
    return ApiResponse.object(response.data);
  }

  Future<Map<String, dynamic>> detail(String id) async =>
    ApiResponse.object((await _dio.get('/api/v1/audit-workspaces/$id')).data);

  Future<void> update(String id, int version, String action, Map<String, dynamic> values) async {
    await _dio.post('/api/v1/audit-workspaces/$id', data: {
      ...values, 'version': version, 'action': action,
    });
  }

  Future<List<Map<String, dynamic>>> plans() async {
    final response = ApiResponse.object((await _dio.get('/api/v1/audit-plans')).data);
    return (response['plans'] as List).map((item) => Map<String, dynamic>.from(item as Map)).toList();
  }

  Future<void> decidePlan(String id, int version, String action, String remarks) async {
    await _dio.post('/api/v1/audit-plans/$id', data: {
      'version': version, 'action': action, 'remarks': remarks,
    });
  }
}
