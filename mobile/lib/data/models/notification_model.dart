class NotificationModel {
  final String id;
  final String recipientUserId;
  final String? senderUserId;
  final String type;
  final String title;
  final String message;
  final String? relatedCheckId;
  final String? relatedUserCode;
  final bool isRead;
  final DateTime createdAt;

  const NotificationModel({
    required this.id,
    required this.recipientUserId,
    this.senderUserId,
    required this.type,
    required this.title,
    required this.message,
    this.relatedCheckId,
    this.relatedUserCode,
    required this.isRead,
    required this.createdAt,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['_id'] as String? ?? json['id'] as String? ?? '',
      recipientUserId: json['recipientUserId'] as String? ?? '',
      senderUserId: json['senderUserId'] as String?,
      type: json['type'] as String? ?? 'system_alert',
      title: json['title'] as String? ?? '',
      message: json['message'] as String? ?? '',
      relatedCheckId: json['relatedCheckId'] as String?,
      relatedUserCode: json['relatedUserCode'] as String?,
      isRead: json['isRead'] as bool? ?? false,
      createdAt: json['createdAt'] != null
          ? DateTime.tryParse(json['createdAt'].toString()) ?? DateTime.now()
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      '_id': id,
      'recipientUserId': recipientUserId,
      'senderUserId': senderUserId,
      'type': type,
      'title': title,
      'message': message,
      'relatedCheckId': relatedCheckId,
      'relatedUserCode': relatedUserCode,
      'isRead': isRead,
      'createdAt': createdAt.toIso8601String(),
    };
  }
}
