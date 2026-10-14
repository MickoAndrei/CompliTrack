class ApiConstants {
  static const baseUrl = String.fromEnvironment(
    'COMPLITRACK_API_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  static const realtimeUrl = String.fromEnvironment(
    'COMPLITRACK_REALTIME_URL',
    defaultValue: 'http://10.0.2.2:4000',
  );

  static const apiPrefix = '/api/v1';
}