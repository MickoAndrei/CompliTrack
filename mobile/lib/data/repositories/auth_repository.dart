import 'package:dio/dio.dart';
import '../models/user_model.dart';
import '../../core/network/api_client.dart';
import '../../core/services/secure_storage_service.dart';

class AuthRepository {
  final ApiClient _apiClient;
  final SecureStorageService _storageService;

  AuthRepository({
    ApiClient? apiClient,
    SecureStorageService? storageService,
  })  : _apiClient = apiClient ?? ApiClient(),
        _storageService = storageService ?? SecureStorageService();

  Future<({String token, UserModel user})?> login({
    required String email,
    required String password,
    String? fcmToken,
  }) async {
    final response = await _apiClient.dio.post(
      '/api/v1/auth/login',
      data: {
        'email': email,
        'password': password,
        if (fcmToken != null) 'fcmToken': fcmToken,
      },
    );
    final body = _requireMap(response.data);
    final token = body['token'];
    final userJson = body['user'];
    if (token is! String || token.isEmpty || userJson is! Map<String, dynamic>) {
      throw const FormatException('The login response is missing its token or user.');
    }

    final user = UserModel.fromJson(userJson);
    await _storageService.saveToken(token);
    await _storageService.saveRoleId(user.roleId);
    await _storageService.saveUserId(user.id);

    return (token: token, user: user);
  }

  Future<void> logout() async {
    try {
      await _apiClient.dio.post('/api/v1/auth/logout');
    } finally {
      await _storageService.clearAll();
    }
  }

  Future<void> requestPasswordReset(String email) async {
    await _apiClient.dio.post(
      '/api/v1/auth/forgot-password',
      data: {'email': email},
    );
  }

  Future<void> resetPassword({
    required String email,
    required String token,
    required String password,
  }) async {
    await _apiClient.dio.post(
      '/api/v1/auth/reset-password',
      data: {
        'email': email,
        'token': token,
        'password': password,
        'password_confirmation': password,
      },
    );
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await _apiClient.dio.patch(
      '/api/v1/auth/password',
      data: {
        'currentPassword': currentPassword,
        'password': newPassword,
        'password_confirmation': newPassword,
      },
    );
  }

  Future<({String? token, int? roleId, UserModel? user})> getSavedSession() async {
    final token = await _storageService.getToken();
    final roleId = await _storageService.getRoleId();
    if (token == null || token.isEmpty || roleId == null || roleId < 0 || roleId > 3) {
      await _storageService.clearAll();
      return (token: null, roleId: null, user: null);
    }

    try {
      final response = await _apiClient.dio.get('/api/v1/auth/me');
      final body = _requireMap(response.data);
      final userJson = body['user'];
      if (userJson is! Map<String, dynamic>) {
        throw const FormatException('The session response is missing its user.');
      }
      final user = UserModel.fromJson(userJson);
      if (user.roleId != roleId) {
        await _storageService.clearAll();
        return (token: null, roleId: null, user: null);
      }
      return (token: token, roleId: roleId, user: user);
    } on DioException catch (error) {
      if (error.response?.statusCode == 401) {
        await _storageService.clearAll();
        return (token: null, roleId: null, user: null);
      }
      return (token: token, roleId: roleId, user: null);
    } on FormatException {
      await _storageService.clearAll();
      rethrow;
    }
  }

  Map<String, dynamic> _requireMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    throw const FormatException('The server returned an invalid response.');
  }
}
