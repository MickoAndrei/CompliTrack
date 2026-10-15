import 'package:flutter_dotenv/flutter_dotenv.dart';

class ApiConstants {
  static const _apiUrlOverride = String.fromEnvironment('COMPLITRACK_API_URL');
  static const _realtimeUrlOverride =
      String.fromEnvironment('COMPLITRACK_REALTIME_URL');

  static final baseUrl = _configuredUrl(
    'COMPLITRACK_API_URL',
    _apiUrlOverride,
    'http://10.0.2.2:8000',
  );

  static final realtimeUrl = _configuredUrl(
    'COMPLITRACK_REALTIME_URL',
    _realtimeUrlOverride,
    'http://10.0.2.2:4000',
  );

  static const apiPrefix = '/api/v1';

  static String _configuredUrl(
    String key,
    String dartDefine,
    String defaultValue,
  ) {
    if (dartDefine.trim().isNotEmpty) return dartDefine.trim();
    final configured = dotenv.env[key];
    return configured == null || configured.trim().isEmpty
        ? defaultValue
        : configured.trim();
  }
}
