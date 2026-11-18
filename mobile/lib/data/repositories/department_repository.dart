import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/department_model.dart';

class DepartmentRepository {
  final Dio _dio;

  DepartmentRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<List<DepartmentModel>> getDepartments({String? campus}) async {
    final departments = <DepartmentModel>[];
    var page = 1;
    while (true) {
      final response = await _dio.get(
        '/api/v1/departments',
        queryParameters: {
          'page': page,
          'perPage': 100,
          if (campus != null && campus.isNotEmpty) 'campusName': campus,
        },
      );
      departments.addAll(ApiResponse.list(response.data).map(DepartmentModel.fromJson));
      final meta = response.data['meta'];
      final lastPage = meta is Map ? int.tryParse('${meta['lastPage']}') ?? 1 : 1;
      if (page >= lastPage) return departments;
      page++;
    }
  }

  Future<bool> deleteDepartment(String id) async {
    final response = await _dio.delete('/api/v1/departments/$id');
    return response.statusCode == 200;
  }

  Future<DepartmentModel> createDepartment({
    required String departmentCode,
    required String departmentName,
    required String collegeName,
    required String campusName,
  }) async {
    final response = await _dio.post(
      '/api/v1/departments',
      data: {
        'departmentCode': departmentCode,
        'departmentName': departmentName,
        'collegeName': collegeName,
        'campusName': campusName,
      },
    );
    return DepartmentModel.fromJson(ApiResponse.object(response.data));
  }

  Future<DepartmentModel> updateDepartment(
    String id, {
    String? departmentName,
    String? collegeName,
    String? campusName,
    String? headUserId,
    bool clearHead = false,
  }) async {
    final response = await _dio.patch(
      '/api/v1/departments/$id',
      data: {
        if (departmentName != null) 'departmentName': departmentName,
        if (collegeName != null) 'collegeName': collegeName,
        if (campusName != null) 'campusName': campusName,
        if (headUserId != null) 'headUserId': headUserId,
        if (clearHead) 'headUserId': null,
      },
    );
    return DepartmentModel.fromJson(ApiResponse.object(response.data));
  }
}
