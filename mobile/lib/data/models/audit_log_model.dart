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
      id: json['_id'] as String? ?? json['id'] as String? ?? '',
      userId: json['userId'] as String? ?? '',
      userName: json['userName'] as String? ?? json['user']?['fullName'] as String? ?? 'System',
      action: json['action'] as String? ?? '',
      entityType: json['entityType'] as String?,
      entityId: json['entityId'] as String?,
      ipAddress: json['ipAddress'] as String?,
      userAgent: json['userAgent'] as String?,
      createdAt: json['createdAt'] != null
          ? DateTime.tryParse(json['createdAt'].toString()) ?? DateTime.now()
          : DateTime.now(),
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
