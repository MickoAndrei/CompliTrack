import 'model_json.dart';

class AuditLogModel {
  final String id;
  final String userId;
  final String userName;
  final String action;
  final String? entityType;
  final String? entityId;
  final String? ipAddress;
  final String? userAgent;
  final DateTime createdAt;

  const AuditLogModel({
    required this.id,
    required this.userId,
    required this.userName,
    required this.action,
    this.entityType,
    this.entityId,
    this.ipAddress,
    this.userAgent,
    required this.createdAt,
  });

  factory AuditLogModel.fromJson(Map<String, dynamic> json) {
    return AuditLogModel(
      id: ModelJson.requiredString(json, '_id', alias: 'id'),
      userId: ModelJson.optionalString(json, 'userId') ?? '',
      userName: ModelJson.requiredString(json, 'userName'),
      action: ModelJson.requiredString(json, 'action'),
      entityType: ModelJson.optionalString(json, 'entityType'),
      entityId: ModelJson.optionalString(json, 'entityId'),
      ipAddress: ModelJson.optionalString(json, 'ipAddress'),
      userAgent: ModelJson.optionalString(json, 'userAgent'),
      createdAt: ModelJson.requiredDateTime(json, 'createdAt'),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      '_id': id,
      'userId': userId,
      'userName': userName,
      'action': action,
      'entityType': entityType,
      'entityId': entityId,
      'ipAddress': ipAddress,
      'userAgent': userAgent,
      'createdAt': createdAt.toIso8601String(),
    };
  }
}
