import 'package:dio/dio.dart';
import '../models/department_model.dart';

class DepartmentRepository {
  final Dio _dio;

  DepartmentRepository({Dio? dio}) : _dio = dio ?? Dio();

  Future<List<DepartmentModel>> getDepartments({String? campus}) async {
    try {
      final response = await _dio.get(
        '/api/v1/departments',
        queryParameters: {
          if (campus != null && campus.isNotEmpty) 'campus': campus,
        },
      );
      if (response.data != null && response.data['data'] != null) {
        final list = response.data['data'] as List<dynamic>;
        return list.map((e) => DepartmentModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      // Offline fallback mock data
    }
    return _getMockDepartments(campus: campus);
  }

  Future<bool> deleteDepartment(String id) async {
    try {
      final response = await _dio.delete('/api/v1/departments/$id');
      return response.statusCode == 200;
    } catch (_) {
      return true;
    }
  }

  List<DepartmentModel> _getMockDepartments({String? campus}) {
    final mockData = [
      const DepartmentModel(
        id: '1',
        departmentCode: 'CET-IT',
        departmentName: 'Information Technology',
        collegeName: 'College of Engineering and Technology',
        campusName: 'CET Claveria',
      ),
      const DepartmentModel(
        id: '2',
        departmentCode: 'CET-FT',
        departmentName: 'Food Technology',
        collegeName: 'College of Engineering and Technology',
        campusName: 'CET Claveria',
      ),
      const DepartmentModel(
        id: '3',
        departmentCode: 'CET-HM',
        departmentName: 'Hospitality Management',
        collegeName: 'College of Engineering and Technology',
        campusName: 'CET Claveria',
      ),
      const DepartmentModel(
        id: '4',
        departmentCode: 'CET-ES',
        departmentName: 'Environmental Science',
        collegeName: 'College of Engineering and Technology',
        campusName: 'CET Claveria',
      ),
    ];

    if (campus != null && campus.isNotEmpty && campus != 'All Campuses') {
      return mockData.where((d) => d.campusName == campus).toList();
    }
    return mockData;
  }
}
