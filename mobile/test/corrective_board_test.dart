import 'dart:typed_data';
import 'package:complitrack/data/models/user_model.dart';
import 'package:complitrack/data/repositories/auth_repository.dart';
import 'package:complitrack/data/repositories/compliance_repository.dart';
import 'package:complitrack/presentation/providers/auth_provider.dart';
import 'package:complitrack/presentation/screens/auditor/corrective_board_screen.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _Auth extends AuthRepository {
  @override
  Future<({String? token, int? roleId, UserModel? user})> getSavedSession() async =>
      (token: 'test', roleId: 2, user: null);
  @override
  Future<Uint8List?> fetchAvatar() async => null;
}

void main() {
  setUp(() => dotenv.loadFromString(envString: 'COMPLITRACK_API_URL=http://localhost'));

  testWidgets('web-style records filter and paginate on a narrow phone', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final dio = Dio();
    addTearDown(dio.close);
    final requests = <Map<String, dynamic>>[];
    dio.interceptors.add(InterceptorsWrapper(onRequest: (request, handler) {
      requests.add(Map<String, dynamic>.from(request.queryParameters));
      final page = request.queryParameters['page'] as int;
      handler.resolve(Response(requestOptions: request, statusCode: 200, data: {
        'data': [{
          '_id': 'check$page', 'checkCode': 'CHK-$page',
          'auditItem': 'Review the documented laboratory procedures',
          'finding': 'Updated procedures require supporting evidence.',
          'departmentName': 'College of Engineering and Technology',
          'auditeeName': 'Alex Auditee', 'displayStatus': 'in_progress',
          'statusLabel': 'In Progress', 'dueDate': null,
        }],
        'summary': {'total': 12, 'inProgress': 9, 'awaitingVerification': 2, 'closed': 1},
        'departments': [{'id': 'dept1', 'name': 'Engineering'}],
        'meta': {'total': 12, 'currentPage': page, 'lastPage': 2},
      }));
    }));
    await tester.pumpWidget(ChangeNotifierProvider(
      create: (_) => AuthProvider(authRepository: _Auth()),
      child: MaterialApp(home: CorrectiveBoardScreen(repository: ComplianceRepository(dio: dio))),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Total Actions'), findsOneWidget);
    expect(find.text('12'), findsOneWidget);
    expect(requests.single['correctiveActions'], 1);
    expect(tester.takeException(), isNull);

    await tester.enterText(find.byType(TextField), 'laboratory');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    expect(requests.last['search'], 'laboratory');
    expect(requests.last['page'], 1);
    await tester.ensureVisible(find.text('All statuses'));
    await tester.tap(find.text('All statuses'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Closed').last);
    await tester.pumpAndSettle();
    expect(requests.last['status'], 'closed');

    await tester.scrollUntilVisible(find.text('Next'), 300,
        scrollable: find.byType(Scrollable).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(requests.last['page'], 2);
    expect(requests.last['search'], 'laboratory');
    expect(find.text('Reference: CHK-2'), findsOneWidget);
    expect(find.text('View Details'), findsOneWidget);
    expect(find.text('View History'), findsOneWidget);
    expect(find.text('Due date not recorded'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
}
