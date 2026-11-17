import 'package:intl/intl.dart';

/// Stored instants are UTC. Calendar dates retain their literal day.
String formatPortalDate(Object? value) {
  if (value == null) return 'Not recorded';
  final text = value.toString();
  final date = value is DateTime ? value : DateTime.tryParse(text);
  if (date == null) return 'Date unavailable';
  if (RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text)) {
    return DateFormat('MMM d, yyyy').format(date);
  }
  final manila = date.toUtc().add(const Duration(hours: 8));
  return '${DateFormat('MMM d, yyyy h:mm:ss a').format(manila)} PHT (UTC+08:00)';
}
