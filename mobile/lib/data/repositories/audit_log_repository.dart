import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../models/audit_log_model.dart';

class AuditLogRepository {
  final Dio _dio;

  AuditLogRepository({Dio? dio}) : _dio = dio ?? Dio();

  Future<List<AuditLogModel>> getAuditLogs({
    String? userId,
    String? action,
    DateTimeRange? dateRange,
    String? search,
  }) async {
    try {
      final response = await _dio.get(
        '/api/v1/audit-logs',
        queryParameters: {
          if (userId != null && userId.isNotEmpty) 'userId': userId,
          if (action != null && action.isNotEmpty) 'action': action,
          if (search != null && search.isNotEmpty) 'search': search,
          if (dateRange != null) ...{
            'startDate': dateRange.start.toIso8601String(),
            'endDate': dateRange.end.toIso8601String(),
          },
        },
      );
      if (response.data != null && response.data['data'] != null) {
        final list = response.data['data'] as List<dynamic>;
        return list.map((e) => AuditLogModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      // Offline fallback mock data
    }
    return _getMockAuditLogs(userId: userId, action: action, search: search);
  }

  List<AuditLogModel> _getMockAuditLogs({
    String? userId,
    String? action,
    String? search,
  }) {
    final mockData = [
      AuditLogModel(
        id: '1',
        userId: '2',
        userName: 'Rafael Domingo',
        action: 'Reset password',
        createdAt: DateTime(2026, 9, 10, 9, 12),
      ),
      AuditLogModel(
        id: '2',
        userId: '3',
        userName: 'Kristine Salcedo',
        action: 'Closed CAR-2026-0113',
        createdAt: DateTime(2026, 9, 10, 8, 54),
      ),
      AuditLogModel(
        id: '3',
        userId: '1',
        userName: 'Ma. Teresa Uy',
        action: 'Created department: Mechatronics',
        createdAt: DateTime(2026, 9, 9, 16, 20),
      ),
      AuditLogModel(
        id: '4',
        userId: '0',
        userName: 'System',
        action: 'Nightly backup completed',
        createdAt: DateTime(2026, 9, 9, 2, 0),
      ),
      AuditLogModel(
        id: '5',
        userId: '5',
        userName: 'Jomar Villareal',
        action: 'Uploaded evidence to CHK-2026-0402',
        createdAt: DateTime(2026, 9, 8, 14, 41),
      ),
    ];

    return mockData.where((log) {
      if (userId != null && userId.isNotEmpty && log.userName != userId) return false;
      if (action != null && action.isNotEmpty && !log.action.contains(action)) return false;
      if (search != null && search.isNotEmpty) {
        final query = search.toLowerCase();
        final matchUser = log.userName.toLowerCase().contains(query);
        final matchAction = log.action.toLowerCase().contains(query);
        return matchUser || matchAction;
      }
      return true;
    }).toList();
  }
}
