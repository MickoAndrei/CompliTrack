import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorageService {
  final FlutterSecureStorage _storage;

  SecureStorageService({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const String _keyToken = 'auth_token';
  static const String _keyRoleId = 'user_role_id';
  static const String _keyUserId = 'user_id';
  static const String _keyFcmToken = 'fcm_token';

  Future<void> saveToken(String token) async {
    await _storage.write(key: _keyToken, value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: _keyToken);
  }

  Future<void> saveRoleId(int roleId) async {
    await _storage.write(key: _keyRoleId, value: roleId.toString());
  }

  Future<int?> getRoleId() async {
    final val = await _storage.read(key: _keyRoleId);
    if (val != null) {
      return int.tryParse(val);
    }
    return null;
  }

  Future<void> saveUserId(String userId) async {
    await _storage.write(key: _keyUserId, value: userId);
  }

  Future<String?> getUserId() async {
    return await _storage.read(key: _keyUserId);
  }

  Future<void> saveFcmToken(String fcmToken) async {
    await _storage.write(key: _keyFcmToken, value: fcmToken);
  }

  Future<String?> getFcmToken() async {
    return await _storage.read(key: _keyFcmToken);
  }

  Future<void> clearAll() async {
    await _storage.deleteAll();
  }
}
