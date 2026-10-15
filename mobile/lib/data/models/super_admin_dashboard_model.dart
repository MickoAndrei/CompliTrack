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

  factory SuperAdminDashboardModel.fromJson(Map<String, dynamic> json) {
    final users = json['userGrowth'] ?? const [];
    final departments = json['deptCompliance'] ?? const [];
    if (users is! List || departments is! List) {
      throw const FormatException('The server returned invalid dashboard series.');
    }

    return SuperAdminDashboardModel(
      totalUsers: _requiredInt(json, 'totalUsers'),
      totalDepts: _requiredInt(json, 'totalDepts'),
      storageUsedGb: _requiredDouble(json, 'storageUsedGb'),
      activeAudits: _requiredInt(json, 'activeAudits'),
      userGrowth: users
          .map((item) => UserGrowthData.fromJson(_requireMap(item)))
          .toList(),
      deptCompliance: departments
          .map((item) => DepartmentComplianceData.fromJson(_requireMap(item)))
          .toList(),
    );
  }

  static int _requiredInt(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is int) return value;
    throw FormatException('Missing or invalid "$key" field.');
  }

  static double _requiredDouble(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is num) return value.toDouble();
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
    return UserGrowthData(
      month: json['month'] as String,
      count: json['count'] as int,
    );
  }
}

class DepartmentComplianceData {
  final String departmentCode;
  final double compliancePercentage;

  const DepartmentComplianceData({
    required this.departmentCode,
    required this.compliancePercentage,
  });

  factory DepartmentComplianceData.fromJson(Map<String, dynamic> json) {
    return DepartmentComplianceData(
      departmentCode: json['departmentCode'] as String,
      compliancePercentage: (json['compliancePercentage'] as num).toDouble(),
    );
  }
}
