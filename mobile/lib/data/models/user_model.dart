class UserModel {
  final String id;
  final String userCode;
  final String fullName;
  final String email;
  final int roleId;
  final String roleSlug;
  final String? departmentId;
  final String officeName;
  final bool isActive;
  final DateTime? createdAt;

  const UserModel({
    required this.id,
    required this.userCode,
    required this.fullName,
    required this.email,
    required this.roleId,
    required this.roleSlug,
    this.departmentId,
    required this.officeName,
    required this.isActive,
    this.createdAt,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['_id'] as String? ?? json['id'] as String? ?? '',
      userCode: json['userCode'] as String? ?? '',
      fullName: json['fullName'] as String? ?? '',
      email: json['email'] as String? ?? '',
      roleId: json['roleId'] as int? ?? 3,
      roleSlug: json['roleSlug'] as String? ?? 'auditee',
      departmentId: json['departmentId'] as String?,
      officeName: json['officeName'] as String? ?? '',
      isActive: json['isActive'] as bool? ?? true,
      createdAt: json['createdAt'] != null
          ? DateTime.tryParse(json['createdAt'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      '_id': id,
      'userCode': userCode,
      'fullName': fullName,
      'email': email,
      'roleId': roleId,
      'roleSlug': roleSlug,
      'departmentId': departmentId,
      'officeName': officeName,
      'isActive': isActive,
      if (createdAt != null) 'createdAt': createdAt!.toIso8601String(),
    };
  }
}
