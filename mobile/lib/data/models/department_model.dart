import 'model_json.dart';

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
      id: ModelJson.requiredString(json, '_id', alias: 'id'),
      departmentCode: ModelJson.requiredString(json, 'departmentCode'),
      departmentName: ModelJson.requiredString(json, 'departmentName'),
      collegeName: ModelJson.requiredString(json, 'collegeName'),
      campusName: ModelJson.requiredString(json, 'campusName'),
      headUserId: ModelJson.optionalString(json, 'headUserId'),
      isActive: ModelJson.requiredBool(json, 'isActive'),
      createdAt: json['createdAt'] != null
          ? ModelJson.requiredDateTime(json, 'createdAt')
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
