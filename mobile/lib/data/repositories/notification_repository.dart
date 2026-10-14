import 'package:dio/dio.dart';
import '../models/notification_model.dart';

class NotificationRepository {
  final Dio _dio;

  NotificationRepository({Dio? dio}) : _dio = dio ?? Dio();

  Future<List<NotificationModel>> getNotifications() async {
    try {
      final response = await _dio.get('/api/v1/notifications');
      if (response.data != null && response.data['data'] != null) {
        final list = response.data['data'] as List<dynamic>;
        return list.map((e) => NotificationModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      // Offline fallback mock data
    }
    return _getMockNotifications();
  }

  Future<bool> markAsRead(String id) async {
    try {
      final response = await _dio.patch('/api/v1/notifications/$id/read');
      return response.statusCode == 200;
    } catch (_) {
      return true;
    }
  }

  Future<bool> markAllAsRead() async {
    try {
      final response = await _dio.post('/api/v1/notifications/read-all');
      return response.statusCode == 200;
    } catch (_) {
      return true;
    }
  }

  List<NotificationModel> _getMockNotifications() {
    return [
      NotificationModel(
        id: '1',
        recipientUserId: '0',
        type: 'user_added',
        title: 'Kristine Salcedo was added to CET - Electrical Eng. as Auditor.',
        message: 'Kristine Salcedo was added to CET - Electrical Eng. as Auditor.',
        relatedUserCode: 'AUD-2026-032',
        isRead: false,
        createdAt: DateTime.now().subtract(const Duration(hours: 2)),
      ),
      NotificationModel(
        id: '2',
        recipientUserId: '0',
        type: 'password_reset_request',
        title: 'Rafael Domingo requested a password reset',
        message: 'Rafael Domingo requested a password reset',
        relatedUserCode: 'ADM-2026-014',
        isRead: false,
        createdAt: DateTime.now().subtract(const Duration(days: 1)),
      ),
      NotificationModel(
        id: '3',
        recipientUserId: '0',
        type: 'status_changed',
        title: 'ISO 9001 Clause 8.2 audit for CET - IT Department was marked complete',
        message: 'ISO 9001 Clause 8.2 audit for CET - IT Department was marked complete',
        relatedCheckId: 'CHK-2026-0001',
        isRead: true,
        createdAt: DateTime.now().subtract(const Duration(days: 3)),
      ),
    ];
  }
}
