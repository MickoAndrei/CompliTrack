import 'model_json.dart';

class ArchiveDocumentModel {
  final String id;
  final String fileCode;
  final String originalName;
  final String mimeType;
  final int fileSize;
  final String checksum;
  final String departmentCode;
  final String referenceStandard;
  final String uploadedBy;
  final bool isVerified;
  final bool isEncrypted;
  final DateTime createdAt;

  const ArchiveDocumentModel({
    required this.id,
    required this.fileCode,
    required this.originalName,
    required this.mimeType,
    required this.fileSize,
    required this.checksum,
    required this.departmentCode,
    required this.referenceStandard,
    required this.uploadedBy,
    required this.isVerified,
    this.isEncrypted = true,
    required this.createdAt,
  });

  factory ArchiveDocumentModel.fromJson(Map<String, dynamic> json) {
    return ArchiveDocumentModel(
      id: ModelJson.requiredString(json, '_id', alias: 'id'),
      fileCode: ModelJson.requiredString(json, 'fileCode'),
      originalName: ModelJson.requiredString(json, 'originalName'),
      mimeType: ModelJson.requiredString(json, 'mimeType'),
      fileSize: ModelJson.requiredInt(json, 'fileSize'),
      checksum: ModelJson.requiredString(json, 'checksum'),
      departmentCode: ModelJson.requiredString(json, 'departmentCode'),
      referenceStandard: ModelJson.requiredString(json, 'referenceStandard'),
      uploadedBy: ModelJson.requiredString(json, 'uploadedBy'),
      isVerified: json['isVerified'] == true,
      isEncrypted: ModelJson.requiredBool(json, 'isEncrypted'),
      createdAt: ModelJson.requiredDateTime(json, 'createdAt'),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      '_id': id,
      'fileCode': fileCode,
      'originalName': originalName,
      'mimeType': mimeType,
      'fileSize': fileSize,
      'checksum': checksum,
      'departmentCode': departmentCode,
      'referenceStandard': referenceStandard,
      'uploadedBy': uploadedBy,
      'isVerified': isVerified,
      'isEncrypted': isEncrypted,
      'createdAt': createdAt.toIso8601String(),
    };
  }
}
