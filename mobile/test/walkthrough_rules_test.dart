import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/core/utils/car_status.dart';
import 'package:complitrack/core/utils/date_formatter.dart';

void main() {
  test('CAR aliases do not imply verification and unknown values survive', () {
    expect(canonicalCarStatus('In progress'), 'in_progress');
    expect(canonicalCarStatus('in-progress'), 'in_progress');
    expect(canonicalCarStatus('Awaiting Verification'), 'awaiting_verification');
    expect(canonicalCarStatus('closed_pending'), 'closed_pending');
  });
  test('Manila instant formatting preserves calendar-only dates', () {
    expect(formatPortalDate('2026-12-31T23:59:59Z'), 'Jan 1, 2027 7:59:59 AM PHT (UTC+08:00)');
    expect(formatPortalDate('2026-12-31'), 'Dec 31, 2026');
  });
}
