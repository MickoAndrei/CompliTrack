import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/user_model.dart';

class UserRepository {
  final Dio _dio;

  UserRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<List<UserModel>> getUsers({
    int? roleId,
    String? search,
    int perPage = 20,
  }) async {
    final response = await _dio.get(
      '/api/v1/users',
      queryParameters: {
        if (roleId != null) 'roleId': roleId,
        if (search != null && search.isNotEmpty) 'search': search,
        'perPage': perPage,
      },
    );
    return ApiResponse.list(response.data).map(UserModel.fromJson).toList();
  }

  Future<Map<String, dynamic>> getOptions() async {
    final response = await _dio.get('/api/v1/users/options');
    return response.data['options'] as Map<String, dynamic>;
  }

  Future<UserModel> createUser({
    required String fullName,
    required String email,
    required int roleId,
    String? departmentId,
    String? officeName,
    bool? isActive,
    List<String>? assignedDepartmentIds,
    String? archiveResponsibility,
    List<String>? archiveUnitIds,
    List<String>? archiveCollegeIds,
    String? archiveCluster,
  }) async {
    final response = await _dio.post(
      '/api/v1/users',
      data: {
        'fullName': fullName,
        'email': email,
        'roleId': roleId,
        if (departmentId != null) 'departmentId': departmentId,
        if (officeName != null && officeName.isNotEmpty)
          'officeName': officeName,
        if (isActive != null) 'isActive': isActive,
        if (assignedDepartmentIds != null)
          'assignedDepartmentIds': assignedDepartmentIds,
        if (archiveResponsibility != null && archiveResponsibility.isNotEmpty)
          'archiveResponsibility': archiveResponsibility,
        if (archiveUnitIds != null) 'archiveUnitIds': archiveUnitIds,
        if (archiveCollegeIds != null) 'archiveCollegeIds': archiveCollegeIds,
        if (archiveCluster != null && archiveCluster.isNotEmpty)
          'archiveCluster': archiveCluster,
      },
    );
    return UserModel.fromJson(ApiResponse.object(response.data));
  }

  Future<UserModel> updateUser(
    String userId, {
    String? fullName,
    String? email,
    int? roleId,
    String? departmentId,
    String? officeName,
    bool? isActive,
    List<String>? assignedDepartmentIds,
    String? archiveResponsibility,
    List<String>? archiveUnitIds,
    List<String>? archiveCollegeIds,
    String? archiveCluster,
  }) async {
    final response = await _dio.patch(
      '/api/v1/users/$userId',
      data: {
        if (fullName != null) 'fullName': fullName,
        if (email != null) 'email': email,
        if (roleId != null) 'roleId': roleId,
        if (departmentId != null) 'departmentId': departmentId,
        if (officeName != null) 'officeName': officeName,
        if (isActive != null) 'isActive': isActive,
        if (assignedDepartmentIds != null)
          'assignedDepartmentIds': assignedDepartmentIds,
        if (archiveResponsibility != null)
          'archiveResponsibility': archiveResponsibility,
        if (archiveUnitIds != null) 'archiveUnitIds': archiveUnitIds,
        if (archiveCollegeIds != null) 'archiveCollegeIds': archiveCollegeIds,
        if (archiveCluster != null) 'archiveCluster': archiveCluster,
      },
    );
    return UserModel.fromJson(ApiResponse.object(response.data));
  }

  Future<bool> deactivateUser(String userId) async {
    final response =
        await _dio.patch('/api/v1/users/$userId', data: {'isActive': false});
    return response.statusCode == 200;
  }

  Future<bool> resetPassword(String userId) async {
    final response = await _dio.post('/api/v1/users/$userId/reset-password');
    return response.statusCode == 200;
  }
}
