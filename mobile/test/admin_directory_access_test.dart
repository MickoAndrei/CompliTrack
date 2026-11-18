import 'dart:typed_data';
import 'package:complitrack/data/models/user_model.dart';
import 'package:complitrack/data/repositories/auth_repository.dart';
import 'package:complitrack/data/repositories/department_repository.dart';
import 'package:complitrack/data/repositories/user_repository.dart';
import 'package:complitrack/presentation/providers/auth_provider.dart';
import 'package:complitrack/presentation/screens/admin/admin_user_management_screen.dart';
import 'package:complitrack/presentation/screens/admin/department_management_screen.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _Auth extends AuthRepository {
  _Auth(this.role);
  final int role;
  @override
  Future<({String? token, int? roleId, UserModel? user})> getSavedSession() async =>
      (token: 'test', roleId: role, user: null);
  @override
  Future<Uint8List?> fetchAvatar() async => null;
}

void main() {
  setUp(() => dotenv.loadFromString(envString: 'COMPLITRACK_API_URL=http://localhost'));

  Future<void> mount(WidgetTester tester, Widget screen, int role) async {
    await tester.pumpWidget(ChangeNotifierProvider(
      create: (_) => AuthProvider(authRepository: _Auth(role)),
      child: MaterialApp(home: screen),
    ));
    await tester.pumpAndSettle();
  }

  Dio directoryDio(List<Map<String, Object?>> rows) {
    final dio = Dio();
    addTearDown(dio.close);
    dio.interceptors.add(InterceptorsWrapper(onRequest: (request, handler) {
      expect(request.method, 'GET');
      handler.resolve(Response(requestOptions: request, statusCode: 200,
        data: {'data': rows}));
    }));
    return dio;
  }

  testWidgets('ATL users are searchable with no account write actions', (tester) async {
    final dio = directoryDio([{
      '_id': 'user1', 'userCode': 'AUD-1', 'fullName': 'Alex Auditor',
      'email': 'alex@example.com', 'roleId': 2, 'isActive': true,
    }]);
    await mount(tester, AdminUserManagementScreen(repository: UserRepository(dio: dio)), 1);
    expect(find.text('Alex Auditor'), findsOneWidget);
    for (final label in ['Create User', 'Edit', 'Deactivate', 'Reset', 'Save User']) {
      expect(find.text(label), findsNothing);
    }
    await tester.enterText(find.byType(TextField), 'missing');
    await tester.pumpAndSettle();
    expect(find.text('No users found'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'Alex');
    await tester.pumpAndSettle();
    expect(find.text('Alex Auditor'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });

  for (final role in [1, 0]) {
    testWidgets('office write controls are ${role == 0 ? 'available to super admin' : 'hidden from ATL'}', (tester) async {
      final dio = directoryDio([{
        '_id': 'dept1', 'departmentCode': 'CET-IT', 'departmentName': 'Information Technology',
        'collegeName': 'CET', 'campusName': 'Claveria',
      }]);
      await mount(tester, DepartmentManagementScreen(repository: DepartmentRepository(dio: dio)), role);
      expect(find.text('Information Technology'), findsOneWidget);
      for (final label in ['Add Dept', 'Edit', 'Delete']) {
        expect(find.text(label), role == 0 ? findsOneWidget : findsNothing);
      }
      if (role == 0) {
        await tester.tap(find.text('Edit'));
        await tester.pumpAndSettle();
        expect(find.text('Save Changes'), findsOneWidget);
      }
      await tester.pumpWidget(const SizedBox());
    });
  }
}
