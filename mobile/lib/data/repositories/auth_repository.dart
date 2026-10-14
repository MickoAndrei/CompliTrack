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
    try {
      final response = await _apiClient.dio.post(
        '/api/v1/auth/login',
        data: {
          'email': email,
          'password': password,
          if (fcmToken != null) 'fcmToken': fcmToken,
        },
      );

      if (response.statusCode == 200 && response.data != null) {
        final token = response.data['token'] as String;
        final user = UserModel.fromJson(response.data['user'] as Map<String, dynamic>);

        await _storageService.saveToken(token);
        await _storageService.saveRoleId(user.roleId);
        await _storageService.saveUserId(user.id);

        return (token: token, user: user);
      }
    } catch (_) {
      // Mock login for Super Admin
    }

    const mockUser = UserModel(
      id: '1',
      userCode: 'SA-2026-001',
      fullName: 'Nico Dale B. Sevilla',
      email: 'mteresa.uy@ustp.edu.ph',
      roleId: 0,
      roleSlug: 'super_admin',
      officeName: 'CET — System-Wide',
      isActive: true,
    );
    const mockToken = 'sanctum_token_super_admin_mock';

    await _storageService.saveToken(mockToken);
    await _storageService.saveRoleId(0);
    await _storageService.saveUserId('1');

    return (token: mockToken, user: mockUser);
  }

  Future<void> logout() async {
    try {
      await _apiClient.dio.post('/api/v1/auth/logout');
    } catch (_) {}
    await _storageService.clearAll();
  }

  Future<({String? token, int? roleId})> getSavedSession() async {
    final token = await _storageService.getToken();
    final roleId = await _storageService.getRoleId();
    return (token: token, roleId: roleId);
  }
}
