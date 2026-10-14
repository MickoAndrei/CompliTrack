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
      id: json['_id'] as String? ?? json['id'] as String? ?? '',
      fileCode: json['fileCode'] as String? ?? '',
      originalName: json['originalName'] as String? ?? '',
      mimeType: json['mimeType'] as String? ?? 'application/pdf',
      fileSize: json['fileSize'] as int? ?? 0,
      checksum: json['checksum'] as String? ?? '',
      departmentCode: json['departmentCode'] as String? ?? json['department']?['departmentCode'] as String? ?? '',
      referenceStandard: json['referenceStandard'] as String? ?? '',
      uploadedBy: json['uploadedBy'] as String? ?? json['uploader']?['fullName'] as String? ?? '',
      isVerified: json['isVerified'] as bool? ?? true,
      isEncrypted: json['isEncrypted'] as bool? ?? true,
      createdAt: json['createdAt'] != null
          ? DateTime.tryParse(json['createdAt'].toString()) ?? DateTime.now()
          : DateTime.now(),
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
