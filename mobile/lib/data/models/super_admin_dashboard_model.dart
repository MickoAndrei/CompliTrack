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
    return SuperAdminDashboardModel(
      totalUsers: json['totalUsers'] as int? ?? 248,
      totalDepts: json['totalDepts'] as int? ?? 12,
      storageUsedGb: (json['storageUsedGb'] as num?)?.toDouble() ?? 38.4,
      activeAudits: json['activeAudits'] as int? ?? 17,
      userGrowth: (json['userGrowth'] as List<dynamic>?)
              ?.map((e) => UserGrowthData.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [
            UserGrowthData(month: 'Apr', count: 152),
            UserGrowthData(month: 'May', count: 170),
            UserGrowthData(month: 'Jun', count: 188),
            UserGrowthData(month: 'Jul', count: 210),
            UserGrowthData(month: 'Aug', count: 230),
            UserGrowthData(month: 'Sep', count: 248),
          ],
      deptCompliance: (json['deptCompliance'] as List<dynamic>?)
              ?.map((e) => DepartmentComplianceData.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [
            DepartmentComplianceData(departmentCode: 'IT', compliancePercentage: 92.0),
            DepartmentComplianceData(departmentCode: 'Civil', compliancePercentage: 78.0),
            DepartmentComplianceData(departmentCode: 'EE', compliancePercentage: 85.0),
            DepartmentComplianceData(departmentCode: 'ME', compliancePercentage: 71.0),
            DepartmentComplianceData(departmentCode: 'CpE', compliancePercentage: 88.0),
          ],
    );
  }
}

class UserGrowthData {
  final String month;
  final int count;

  const UserGrowthData({required this.month, required this.count});

  factory UserGrowthData.fromJson(Map<String, dynamic> json) {
    return UserGrowthData(
      month: json['month'] as String? ?? '',
      count: json['count'] as int? ?? 0,
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
      departmentCode: json['departmentCode'] as String? ?? '',
      compliancePercentage: (json['compliancePercentage'] as num?)?.toDouble() ?? 0.0,
    );
  }
}
