import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/app.dart';

void main() {
  testWidgets('App renders smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const CompliTrackApp());
    expect(find.byType(CompliTrackApp), findsOneWidget);
  });
}
