import 'model_json.dart';

class ComplianceCheckModel {
  const ComplianceCheckModel({
    required this.id,
    required this.checkCode,
    required this.departmentId,
    required this.auditorId,
    required this.auditeeId,
    required this.referenceStandard,
    required this.result,
    this.departmentName,
    this.auditorName,
    this.auditeeName,
    this.remarks,
    this.correctiveStatus,
    this.dueDate,
    this.closedAt,
    this.linkedArchiveIds = const [],
    this.statusHistory = const [],
    required this.createdAt,
  });

  final String id;
  final String checkCode;
  final String departmentId;
  final String auditorId;
  final String auditeeId;
  final String referenceStandard;
  final String result;
  final String? departmentName;
  final String? auditorName;
  final String? auditeeName;
  final String? remarks;
  final String? correctiveStatus;
  final DateTime? dueDate;
  final DateTime? closedAt;
  final List<String> linkedArchiveIds;
  final List<Map<String, dynamic>> statusHistory;
  final DateTime createdAt;

  factory ComplianceCheckModel.fromJson(Map<String, dynamic> json) {
    return ComplianceCheckModel(
      id: ModelJson.requiredString(json, '_id', alias: 'id'),
      checkCode: ModelJson.requiredString(json, 'checkCode'),
      departmentId: ModelJson.requiredString(json, 'departmentId'),
      auditorId: ModelJson.requiredString(json, 'auditorId'),
      auditeeId: ModelJson.requiredString(json, 'auditeeId'),
      referenceStandard: ModelJson.requiredString(json, 'referenceStandard'),
      result: ModelJson.requiredString(json, 'result'),
      departmentName: ModelJson.optionalString(json, 'departmentName'),
      auditorName: ModelJson.optionalString(json, 'auditorName'),
      auditeeName: ModelJson.optionalString(json, 'auditeeName'),
      remarks: ModelJson.optionalString(json, 'remarks'),
      correctiveStatus: ModelJson.optionalString(json, 'correctiveStatus'),
      dueDate: json['dueDate'] == null
          ? null
          : ModelJson.requiredDateTime(json, 'dueDate'),
      closedAt: json['closedAt'] == null
          ? null
          : ModelJson.requiredDateTime(json, 'closedAt'),
      linkedArchiveIds: json['linkedArchiveIds'] is List
          ? (json['linkedArchiveIds'] as List).map((id) => id.toString()).toList()
          : const [],
      statusHistory: json['statusHistory'] is List
          ? (json['statusHistory'] as List)
              .whereType<Map>()
              .map((entry) => Map<String, dynamic>.from(entry))
              .toList()
          : const [],
      createdAt: ModelJson.requiredDateTime(json, 'createdAt'),
    );
  }
}