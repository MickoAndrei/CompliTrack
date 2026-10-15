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

  Future<UserModel> createUser({
    required String fullName,
    required String email,
    required int roleId,
    required String departmentId,
    String? officeName,
  }) async {
    final response = await _dio.post(
      '/api/v1/users',
      data: {
        'fullName': fullName,
        'email': email,
        'roleId': roleId,
        'departmentId': departmentId,
        if (officeName != null && officeName.isNotEmpty)
          'officeName': officeName,
      },
    );
    return UserModel.fromJson(ApiResponse.object(response.data));
  }

  Future<UserModel> updateUser(
    String userId, {
    String? fullName,
    String? email,
    String? departmentId,
    String? officeName,
    bool? isActive,
  }) async {
    final response = await _dio.patch(
      '/api/v1/users/$userId',
      data: {
        if (fullName != null) 'fullName': fullName,
        if (email != null) 'email': email,
        if (departmentId != null) 'departmentId': departmentId,
        if (officeName != null) 'officeName': officeName,
        if (isActive != null) 'isActive': isActive,
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
