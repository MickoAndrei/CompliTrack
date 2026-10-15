import 'model_json.dart';

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
      id: ModelJson.requiredString(json, '_id', alias: 'id'),
      recipientUserId: ModelJson.requiredString(json, 'recipientUserId'),
      senderUserId: ModelJson.optionalString(json, 'senderUserId'),
      type: ModelJson.requiredString(json, 'type'),
      title: ModelJson.requiredString(json, 'title'),
      message: ModelJson.requiredString(json, 'message'),
      relatedCheckId: ModelJson.optionalString(json, 'relatedCheckId'),
      relatedUserCode: ModelJson.optionalString(json, 'relatedUserCode'),
      isRead: ModelJson.requiredBool(json, 'isRead'),
      createdAt: ModelJson.requiredDateTime(json, 'createdAt'),
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
