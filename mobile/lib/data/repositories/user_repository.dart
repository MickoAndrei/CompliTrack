import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/user_model.dart';

class UserRepository {
  final Dio _dio;

  UserRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<List<UserModel>> getUsers({int? roleId, String? search}) async {
    final response = await _dio.get(
      '/api/v1/users',
      queryParameters: {
        if (roleId != null) 'roleId': roleId,
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );
    return ApiResponse.list(response.data).map(UserModel.fromJson).toList();
  }

  Future<bool> deactivateUser(String userId) async {
    final response = await _dio.patch('/api/v1/users/$userId', data: {'isActive': false});
    return response.statusCode == 200;
  }

  Future<bool> resetPassword(String userId) async {
    final response = await _dio.post('/api/v1/users/$userId/reset-password');
    return response.statusCode == 200;
  }
}
