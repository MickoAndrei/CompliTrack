import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/compliance_check_model.dart';

class ComplianceRepository {
  ComplianceRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  final Dio _dio;

  Future<List<ComplianceCheckModel>> getChecks({
    String? correctiveStatus,
    int perPage = 20,
  }) async {
    final response = await _dio.get(
      '/api/v1/compliance-checks',
      queryParameters: {
        if (correctiveStatus != null) 'correctiveStatus': correctiveStatus,
        'perPage': perPage,
      },
    );
    return ApiResponse.list(response.data)
        .map(ComplianceCheckModel.fromJson)
        .toList();
  }

  Future<ComplianceCheckModel> getCheck(String id) async {
    final response = await _dio.get('/api/v1/compliance-checks/$id');
    return ComplianceCheckModel.fromJson(ApiResponse.object(response.data));
  }

  Future<ComplianceCheckModel> createCheck({
    required String departmentId,
    required String auditeeId,
    required String referenceStandard,
    required String result,
    String? remarks,
    List<String> linkedArchiveIds = const [],
  }) async {
    final response = await _dio.post(
      '/api/v1/compliance-checks',
      data: {
        'departmentId': departmentId,
        'auditeeId': auditeeId,
        'referenceStandard': referenceStandard,
        'result': result,
        if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
        if (linkedArchiveIds.isNotEmpty) 'linkedArchiveIds': linkedArchiveIds,
      },
    );
    return ComplianceCheckModel.fromJson(ApiResponse.object(response.data));
  }

  Future<ComplianceCheckModel> updateStatus({
    required String checkId,
    required String correctiveStatus,
    String? remarks,
  }) async {
    final response = await _dio.patch(
      '/api/v1/compliance-checks/$checkId/status',
      data: {
        'correctiveStatus': correctiveStatus,
        if (remarks != null && remarks.isNotEmpty) 'remarks': remarks,
      },
    );
    return ComplianceCheckModel.fromJson(ApiResponse.object(response.data));
  }
}
