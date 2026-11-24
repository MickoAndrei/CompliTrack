import 'package:complitrack/data/repositories/department_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('loads every department page and preserves campus filter', () async {
    final dio = Dio();
    addTearDown(dio.close);
    final pages = <int>[];
    dio.interceptors.add(InterceptorsWrapper(onRequest: (request, handler) {
      final page = request.queryParameters['page'] as int;
      pages.add(page);
      expect(request.queryParameters['campusName'], 'Claveria');
      handler.resolve(Response(requestOptions: request, statusCode: 200, data: {
        'data': [{'_id': 'department$page', 'departmentName': 'Department $page'}],
        'meta': {'lastPage': 2},
      }));
    }));
    final departments = await DepartmentRepository(dio: dio).getDepartments(campus: 'Claveria');
    expect(pages, [1, 2]);
    expect(departments.map((department) => department.id), ['department1', 'department2']);
  });
}
