import 'model_json.dart';

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
      id: ModelJson.requiredString(json, '_id', alias: 'id'),
      userCode: ModelJson.requiredString(json, 'userCode'),
      fullName: ModelJson.requiredString(json, 'fullName'),
      email: ModelJson.requiredString(json, 'email'),
      roleId: ModelJson.requiredInt(json, 'roleId'),
      roleSlug: ModelJson.requiredString(json, 'roleSlug'),
      departmentId: ModelJson.optionalString(json, 'departmentId'),
      officeName: ModelJson.optionalString(json, 'officeName') ?? '',
      isActive: ModelJson.requiredBool(json, 'isActive'),
      createdAt: json['createdAt'] == null
          ? null
          : ModelJson.requiredDateTime(json, 'createdAt'),
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
