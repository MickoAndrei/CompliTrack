import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/audit_log_model.dart';

class AuditLogRepository {
  final Dio _dio;

  AuditLogRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<List<AuditLogModel>> getAuditLogs({
    String? userId,
    String? action,
    DateTimeRange? dateRange,
    String? search,
  }) async {
    final response = await _dio.get(
      '/api/v1/logs',
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
    return ApiResponse.list(response.data).map(AuditLogModel.fromJson).toList();
  }
}
