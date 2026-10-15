import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/app.dart';
import 'package:complitrack/core/constants/api_constants.dart';
import 'package:complitrack/data/models/user_model.dart';
import 'package:complitrack/data/repositories/auth_repository.dart';
import 'package:complitrack/presentation/providers/auth_provider.dart';
import 'package:provider/provider.dart';

class _StubAuthRepository extends AuthRepository {
  @override
  Future<({String? token, int? roleId, UserModel? user})>
      getSavedSession() async {
    return (token: null, roleId: null, user: null);
  }
}

void main() {
  testWidgets('App renders smoke test', (WidgetTester tester) async {
    await dotenv.load(fileName: '.env');
    expect(ApiConstants.baseUrl, 'http://10.0.2.2:8000');
    expect(ApiConstants.realtimeUrl, 'http://10.0.2.2:4000');
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthProvider(authRepository: _StubAuthRepository()),
        child: const CompliTrackApp(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(CompliTrackApp), findsOneWidget);
  });
}
