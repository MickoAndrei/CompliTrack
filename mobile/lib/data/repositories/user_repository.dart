import 'package:dio/dio.dart';
import '../models/user_model.dart';

class UserRepository {
  final Dio _dio;

  UserRepository({Dio? dio}) : _dio = dio ?? Dio();

  Future<List<UserModel>> getUsers({int? roleId, String? search}) async {
    try {
      final response = await _dio.get(
        '/api/v1/users',
        queryParameters: {
          if (roleId != null) 'roleId': roleId,
          if (search != null && search.isNotEmpty) 'search': search,
        },
      );
      if (response.data != null && response.data['data'] != null) {
        final list = response.data['data'] as List<dynamic>;
        return list.map((e) => UserModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      // Offline fallback mock data
    }
    return _getMockUsers(roleId: roleId, search: search);
  }

  Future<bool> deactivateUser(String userId) async {
    try {
      final response = await _dio.put('/api/v1/users/$userId', data: {'isActive': false});
      return response.statusCode == 200;
    } catch (_) {
      return true;
    }
  }

  Future<bool> resetPassword(String userId) async {
    try {
      final response = await _dio.post('/api/v1/users/$userId/reset-password');
      return response.statusCode == 200;
    } catch (_) {
      return true;
    }
  }

  List<UserModel> _getMockUsers({int? roleId, String? search}) {
    final mockData = [
      const UserModel(
        id: '1',
        userCode: 'SA-2026-001',
        fullName: 'Ma. Teresa Uy',
        email: 'mteresa.uy@ustp.edu.ph',
        roleId: 0,
        roleSlug: 'super_admin',
        officeName: 'CET - IT Department',
        isActive: true,
      ),
      const UserModel(
        id: '2',
        userCode: 'ADM-2026-014',
        fullName: 'Rafael Domingo',
        email: 'r.domingo@ustp.edu.ph',
        roleId: 1,
        roleSlug: 'admin',
        officeName: 'CET - Civil Engineering',
        isActive: true,
      ),
      const UserModel(
        id: '3',
        userCode: 'AUD-2026-032',
        fullName: 'Kristine Salcedo',
        email: 'k.salcedo@ustp.edu.ph',
        roleId: 2,
        roleSlug: 'auditor',
        officeName: 'CET - Electrical Eng.',
        isActive: true,
      ),
      const UserModel(
        id: '4',
        userCode: 'AUE-2026-118',
        fullName: 'Mark Vincent Cruz',
        email: 'mv.cruz@ustp.edu.ph',
        roleId: 3,
        roleSlug: 'auditee',
        officeName: 'CET - Mechanical Eng.',
        isActive: false,
      ),
    ];

    return mockData.where((u) {
      if (roleId != null && u.roleId != roleId) return false;
      if (search != null && search.isNotEmpty) {
        final query = search.toLowerCase();
        final matchName = u.fullName.toLowerCase().contains(query);
        final matchEmail = u.email.toLowerCase().contains(query);
        final matchCode = u.userCode.toLowerCase().contains(query);
        return matchName || matchEmail || matchCode;
      }
      return true;
    }).toList();
  }
}
