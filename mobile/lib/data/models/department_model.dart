class DepartmentModel {
  final String id;
  final String departmentCode;
  final String departmentName;
  final String collegeName;
  final String campusName;
  final String? headUserId;
  final bool isActive;
  final DateTime? createdAt;

  const DepartmentModel({
    required this.id,
    required this.departmentCode,
    required this.departmentName,
    this.collegeName = 'College of Engineering and Technology',
    this.campusName = 'CET Claveria',
    this.headUserId,
    this.isActive = true,
    this.createdAt,
  });

  factory DepartmentModel.fromJson(Map<String, dynamic> json) {
    return DepartmentModel(
      id: json['_id'] as String? ?? json['id'] as String? ?? '',
      departmentCode: json['departmentCode'] as String? ?? '',
      departmentName: json['departmentName'] as String? ?? '',
      collegeName: json['collegeName'] as String? ?? 'College of Engineering and Technology',
      campusName: json['campusName'] as String? ?? 'CET Claveria',
      headUserId: json['headUserId'] as String?,
      isActive: json['isActive'] as bool? ?? true,
      createdAt: json['createdAt'] != null
          ? DateTime.tryParse(json['createdAt'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      '_id': id,
      'departmentCode': departmentCode,
      'departmentName': departmentName,
      'collegeName': collegeName,
      'campusName': campusName,
      'headUserId': headUserId,
      'isActive': isActive,
      if (createdAt != null) 'createdAt': createdAt!.toIso8601String(),
    };
  }
}
