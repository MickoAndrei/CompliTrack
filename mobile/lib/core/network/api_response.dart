class ApiResponse {
  static List<Map<String, dynamic>> list(dynamic body) {
    if (body is! Map<String, dynamic> || body['data'] is! List) {
      throw const FormatException('The server returned an invalid list response.');
    }

    return (body['data'] as List).map((item) {
      if (item is Map<String, dynamic>) return item;
      if (item is Map) {
        return item.map((key, value) => MapEntry(key.toString(), value));
      }
      throw const FormatException('The server returned an invalid list item.');
    }).toList();
  }

  static Map<String, dynamic> object(dynamic body) {
    if (body is! Map) {
      throw const FormatException('The server returned an invalid object response.');
    }

    final map = body.map((key, value) => MapEntry(key.toString(), value));
    final data = map['data'];
    if (data is Map) {
      return data.map((key, value) => MapEntry(key.toString(), value));
    }
    return map;
  }
}
