class ModelJson {
  static String requiredString(Map<String, dynamic> json, String key, {String? alias}) {
    final value = json[key] ?? (alias == null ? null : json[alias]);
    if (value is String) return value;
    throw FormatException('Missing or invalid "$key" field.');
  }

  static String? optionalString(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value == null) return null;
    if (value is String) return value;
    throw FormatException('Invalid "$key" field.');
  }

  static int requiredInt(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is int) return value;
    throw FormatException('Missing or invalid "$key" field.');
  }

  static bool requiredBool(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is bool) return value;
    throw FormatException('Missing or invalid "$key" field.');
  }

  static DateTime requiredDateTime(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is DateTime) return value.toUtc();
    if (value is String) {
      final parsed = DateTime.tryParse(value);
      if (parsed != null) return parsed.toUtc();
    }
    throw FormatException('Missing or invalid "$key" field.');
  }
}
