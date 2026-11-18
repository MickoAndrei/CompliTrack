class SuperAdminDashboardModel {
  final int totalUsers;
  final int totalDepts;
  final double storageUsedGb;
  final int activeAudits;
  final List<UserGrowthData> userGrowth;
  final List<DepartmentComplianceData> deptCompliance;

  const SuperAdminDashboardModel({
    required this.totalUsers,
    required this.totalDepts,
    required this.storageUsedGb,
    required this.activeAudits,
    required this.userGrowth,
    required this.deptCompliance,
  });

  String get storageUsedLabel {
    var size = storageUsedGb * 1024 * 1024 * 1024;
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
      size /= 1024;
      unit++;
    }
    return '${size.toStringAsFixed(unit == 0 ? 0 : 2)} ${units[unit]}';
  }

  factory SuperAdminDashboardModel.fromJson(Map<String, dynamic> json) {
    final users = json['userGrowth'] ?? const [];
    final departments =
        json['departmentCompliance'] ?? json['deptCompliance'] ?? const [];
    if (users is! List || departments is! List) {
      throw const FormatException(
          'The server returned invalid dashboard series.');
    }

    return SuperAdminDashboardModel(
      totalUsers: _requiredInt(json, 'totalUsers'),
      totalDepts: _requiredInt(json, 'totalDepts'),
      storageUsedGb: _storageUsedGb(json),
      activeAudits: _requiredInt(json, 'activeAudits'),
      userGrowth: users
          .map((item) => UserGrowthData.fromJson(_requireMap(item)))
          .toList(),
      deptCompliance: departments
          .map((item) => DepartmentComplianceData.fromJson(_requireMap(item)))
          .toList(),
    );
  }

  static double _storageUsedGb(Map<String, dynamic> json) {
    final stats = json['databaseStats'];
    if (stats is Map &&
        stats['storageSizeBytes'] != null &&
        stats['indexSizeBytes'] != null) {
      final storage =
          _requiredDouble({'bytes': stats['storageSizeBytes']}, 'bytes');
      final indexes =
          _requiredDouble({'bytes': stats['indexSizeBytes']}, 'bytes');
      if (storage < 0 || indexes < 0) {
        throw const FormatException('Invalid database storage size.');
      }
      return (storage + indexes) / (1024 * 1024 * 1024);
    }
    final size = _requiredDouble(json, 'storageUsedGb');
    if (size < 0) {
      throw const FormatException('Invalid database storage size.');
    }
    return size;
  }

  static int _requiredInt(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is int) return value;
    throw FormatException('Missing or invalid "$key" field.');
  }

  static double _requiredDouble(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is num && value.isFinite) return value.toDouble();
    if (value is String) {
      final parsed = double.tryParse(value);
      if (parsed != null && parsed.isFinite) return parsed;
    }
    throw FormatException('Missing or invalid "$key" field.');
  }

  static Map<String, dynamic> _requireMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) {
      return value.map((key, value) => MapEntry(key.toString(), value));
    }
    throw const FormatException('Invalid dashboard series entry.');
  }
}

class UserGrowthData {
  final String month;
  final int count;

  const UserGrowthData({required this.month, required this.count});

  factory UserGrowthData.fromJson(Map<String, dynamic> json) {
    final rawCount = json['count'];
    final count =
        rawCount is num ? rawCount.toInt() : int.tryParse('$rawCount');
    if (count == null || count < 0) {
      throw const FormatException('Invalid user growth count.');
    }

    return UserGrowthData(
      month: json['month'] as String,
      count: count,
    );
  }
}

class DepartmentComplianceData {
  final String departmentCode;
  final double compliancePercentage;
  final bool isAssessed;

  const DepartmentComplianceData({
    required this.departmentCode,
    required this.compliancePercentage,
    this.isAssessed = true,
  });

  factory DepartmentComplianceData.fromJson(Map<String, dynamic> json) {
    final percentage = json.containsKey('percentage')
        ? json['percentage']
        : json['compliancePercentage'];
    if (percentage != null &&
        (percentage is! num ||
            !percentage.isFinite ||
            percentage < 0 ||
            percentage > 100)) {
      throw const FormatException('Invalid department compliance percentage.');
    }
    return DepartmentComplianceData(
      departmentCode: (json['code'] ?? json['departmentCode']) as String,
      compliancePercentage: (percentage as num?)?.toDouble() ?? 0,
      isAssessed: percentage != null,
    );
  }
}
