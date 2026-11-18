import '../../core/utils/role_helper.dart';
import 'model_json.dart';

class UserModel {
  final String id;
  final String userCode;
  final String fullName;
  final String email;
  final int roleId;
  final String roleSlug;
  final String? departmentId;
  final String? departmentName;
  final String officeName;
  final bool isActive;
  final DateTime? createdAt;
  final List<String> assignedDepartmentIds;
  final String? archiveResponsibility;
  final List<String> archiveUnitIds;
  final List<String> archiveCollegeIds;
  final String? archiveCluster;
  final String? clusterName;
  final List<String> organizationalUnits;
  final List<String> organizationalColleges;
  final bool isAuditTeamLeader;
  final bool canDeactivate;

  const UserModel({
    required this.id,
    required this.userCode,
    required this.fullName,
    required this.email,
    required this.roleId,
    required this.roleSlug,
    this.departmentId,
    this.departmentName,
    required this.officeName,
    required this.isActive,
    this.createdAt,
    this.assignedDepartmentIds = const [],
    this.archiveResponsibility,
    this.archiveUnitIds = const [],
    this.archiveCollegeIds = const [],
    this.archiveCluster,
    this.clusterName,
    this.organizationalUnits = const [],
    this.organizationalColleges = const [],
    this.isAuditTeamLeader = false,
    this.canDeactivate = true,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final roleId = ModelJson.requiredInt(json, 'roleId');
    return UserModel(
      id: ModelJson.requiredString(json, '_id', alias: 'id'),
      userCode: ModelJson.requiredString(json, 'userCode'),
      fullName: ModelJson.requiredString(json, 'fullName'),
      email: ModelJson.requiredString(json, 'email'),
      roleId: roleId,
      roleSlug: ModelJson.optionalString(json, 'roleSlug') ?? RoleHelper.getRoleSlug(roleId),
      departmentId: ModelJson.optionalString(json, 'departmentId'),
      departmentName: ModelJson.optionalString(json, 'departmentName'),
      officeName: ModelJson.optionalString(json, 'officeName') ?? '',
      isActive: ModelJson.requiredBool(json, 'isActive'),
      createdAt: json['createdAt'] == null
          ? null
          : ModelJson.requiredDateTime(json, 'createdAt'),
      assignedDepartmentIds: (json['assignedDepartmentIds'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      archiveResponsibility: ModelJson.optionalString(json, 'archiveResponsibility'),
      archiveUnitIds: (json['archiveUnitIds'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      archiveCollegeIds: (json['archiveCollegeIds'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      archiveCluster: ModelJson.optionalString(json, 'archiveCluster'),
      clusterName: ModelJson.optionalString(json, 'clusterName'),
      organizationalUnits: (json['organizationalUnits'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      organizationalColleges: (json['organizationalColleges'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      isAuditTeamLeader: ModelJson.optionalBool(json, 'isAuditTeamLeader') ?? false,
      canDeactivate: ModelJson.optionalBool(json, 'canDeactivate') ?? true,
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
      if (departmentName != null) 'departmentName': departmentName,
      'officeName': officeName,
      'isActive': isActive,
      if (createdAt != null) 'createdAt': createdAt!.toIso8601String(),
      'assignedDepartmentIds': assignedDepartmentIds,
      if (archiveResponsibility != null) 'archiveResponsibility': archiveResponsibility,
      'archiveUnitIds': archiveUnitIds,
      'archiveCollegeIds': archiveCollegeIds,
      if (archiveCluster != null) 'archiveCluster': archiveCluster,
      if (clusterName != null) 'clusterName': clusterName,
      'organizationalUnits': organizationalUnits,
      'organizationalColleges': organizationalColleges,
      'isAuditTeamLeader': isAuditTeamLeader,
      'canDeactivate': canDeactivate,
    };
  }
}
