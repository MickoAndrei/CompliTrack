import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/department_model.dart';

class DepartmentRepository {
  final Dio _dio;

  DepartmentRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<List<DepartmentModel>> getDepartments({String? campus}) async {
    final response = await _dio.get(
      '/api/v1/departments',
      queryParameters: {
        if (campus != null && campus.isNotEmpty) 'campusName': campus,
      },
    );
    return ApiResponse.list(response.data)
        .map(DepartmentModel.fromJson)
        .toList();
  }

  Future<bool> deleteDepartment(String id) async {
    final response = await _dio.delete('/api/v1/departments/$id');
    return response.statusCode == 200;
  }
}
