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
    final collegeRaw = json['collegeName'] ??
        json['collageName'] ??
        json['college_name'] ??
        json['college'];
    final campusRaw =
        json['campusName'] ?? json['campus_name'] ?? json['campus'];
    final codeRaw =
        json['departmentCode'] ?? json['code'] ?? json['department_code'];
    final nameRaw =
        json['departmentName'] ?? json['name'] ?? json['department_name'];

    final collegeStr = collegeRaw?.toString();
    final campusStr = campusRaw?.toString();
    final codeStr = codeRaw?.toString();
    final nameStr = nameRaw?.toString();

    return DepartmentModel(
      id: ModelJson.optionalString(json, '_id') ??
          ModelJson.optionalString(json, 'id') ??
          ModelJson.optionalString(json, 'departmentId') ??
          '',
      departmentCode: (codeStr != null && codeStr.isNotEmpty) ? codeStr : '',
      departmentName: (nameStr != null && nameStr.isNotEmpty) ? nameStr : '',
      collegeName: collegeStr ?? '',
      campusName: (campusStr != null && campusStr.isNotEmpty)
          ? campusStr
          : 'CET Claveria',
      headUserId: ModelJson.optionalString(json, 'headUserId'),
      isActive: ModelJson.optionalBool(json, 'isActive') ?? true,
      createdAt: json['createdAt'] != null
          ? DateTime.tryParse(json['createdAt'].toString())?.toUtc()
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
