import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/data/models/user_model.dart';
import 'package:complitrack/data/repositories/auth_repository.dart';
import 'package:complitrack/presentation/providers/auth_provider.dart';
import 'package:complitrack/presentation/screens/auditee/help_reference_screen.dart';
import 'package:provider/provider.dart';

class _StubAuthRepository extends AuthRepository {
  @override
  Future<({String? token, int? roleId, UserModel? user})>
      getSavedSession() async {
    return (token: null, roleId: null, user: null);
  }
}

void main() {
  testWidgets('auditor help topics filter and show an empty state on mobile',
      (tester) async {
    dotenv.loadFromString(
      envString:
          'COMPLITRACK_API_URL=http://10.0.2.2:8000\nCOMPLITRACK_REALTIME_URL=http://10.0.2.2:4000',
    );
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthProvider(authRepository: _StubAuthRepository()),
        child: const MaterialApp(
          home: HelpReferenceScreen(forAuditor: true),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('5 topics to guide you'), findsOneWidget);
    expect(find.text('Prepare Assigned Audits'), findsNWidgets(2));
    expect(tester.takeException(), isNull);

    await tester.enterText(
      find.byKey(const Key('auditor-help-search')),
      'controlled forms',
    );
    await tester.pumpAndSettle();
    expect(find.text('Confirm the approved criteria'), findsOneWidget);

    await tester.enterText(
      find.byKey(const Key('auditor-help-search')),
      'no matching topic',
    );
    await tester.pumpAndSettle();
    expect(find.text('No topics available'), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });
}
