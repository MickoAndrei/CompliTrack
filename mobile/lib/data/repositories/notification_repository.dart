import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/notification_model.dart';

class NotificationRepository {
  final Dio _dio;

  NotificationRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<List<NotificationModel>> getNotifications() async {
    final response = await _dio.get('/api/v1/notifications');
    return ApiResponse.list(response.data)
        .map(NotificationModel.fromJson)
        .toList();
  }

  Future<bool> markAsRead(String id) async {
    final response = await _dio.patch('/api/v1/notifications/$id/read');
    return response.statusCode == 200;
  }

  Future<bool> markAllAsRead() async {
    final response = await _dio.patch('/api/v1/notifications/read-all');
    return response.statusCode == 200;
  }

  Future<({int recipientCount, String departmentName})> broadcast({
    required String departmentId,
    required String message,
  }) async {
    final response = await _dio.post(
      '/api/v1/notifications/broadcast',
      data: {
        'departmentId': departmentId,
        'message': message,
      },
    );
    final data = ApiResponse.object(response.data);
    final count = data['recipientCount'];
    final department = data['departmentName'];
    if (count is! int || department is! String) {
      throw const FormatException(
        'The server returned an invalid broadcast result.',
      );
    }
    return (recipientCount: count, departmentName: department);
  }
}
