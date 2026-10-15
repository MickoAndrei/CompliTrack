import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/department_model.dart';
import '../../../data/repositories/department_repository.dart';
import '../../../data/repositories/notification_repository.dart';

class BroadcastNotificationDialog extends StatefulWidget {
  const BroadcastNotificationDialog({super.key});

  static Future<void> show(BuildContext context) => showDialog<void>(
        context: context,
        builder: (context) => const BroadcastNotificationDialog(),
      );

  @override
  State<BroadcastNotificationDialog> createState() =>
      _BroadcastNotificationDialogState();
}

class _BroadcastNotificationDialogState
    extends State<BroadcastNotificationDialog> {
  final _formKey = GlobalKey<FormState>();
  final _messageController = TextEditingController();
  final _departmentRepository = DepartmentRepository();
  final _notificationRepository = NotificationRepository();
  List<DepartmentModel> _departments = [];
  String? _departmentId;
  String? _loadError;
  String? _submitError;
  String? _successMessage;
  bool _isLoading = true;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _loadDepartments();
  }

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _loadDepartments() async {
    try {
      final departments = await _departmentRepository.getDepartments();
      if (!mounted) return;
      setState(() {
        _departments =
            departments.where((department) => department.isActive).toList();
        _departmentId = _departments.isEmpty ? null : _departments.first.id;
        _isLoading = false;
      });
    } on DioException catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = _errorMessage(error);
        _isLoading = false;
      });
    } catch (error, stackTrace) {
      FlutterError.reportError(FlutterErrorDetails(
        exception: error,
        stack: stackTrace,
        library: 'CompliTrack broadcast departments',
      ));
      if (mounted) {
        setState(() {
          _loadError = 'Unable to load departments.';
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _sendBroadcast() async {
    if (!(_formKey.currentState?.validate() ?? false) ||
        _departmentId == null) {
      return;
    }
    setState(() {
      _isSubmitting = true;
      _submitError = null;
    });
    try {
      final result = await _notificationRepository.broadcast(
        departmentId: _departmentId!,
        message: _messageController.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _successMessage =
            'Broadcast sent to ${result.recipientCount} auditee${result.recipientCount == 1 ? '' : 's'} in ${result.departmentName}.';
        _isSubmitting = false;
      });
    } on DioException catch (error) {
      if (mounted) {
        setState(() {
          _submitError = _errorMessage(error);
          _isSubmitting = false;
        });
      }
    } catch (error, stackTrace) {
      FlutterError.reportError(FlutterErrorDetails(
        exception: error,
        stack: stackTrace,
        library: 'CompliTrack broadcast notification',
      ));
      if (mounted) {
        setState(() {
          _submitError = 'Unable to send the broadcast.';
          _isSubmitting = false;
        });
      }
    }
  }

  String _errorMessage(DioException error) {
    final data = error.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
    }
    return 'Unable to connect to the server. Please try again.';
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text(
        'Broadcast Notification',
        style: TextStyle(
          fontFamily: 'Poppins',
          color: AppColors.ustpNavy,
          fontWeight: FontWeight.bold,
        ),
      ),
      content: SizedBox(
        width: 420,
        child: _isLoading
            ? const SizedBox(
                height: 100,
                child: Center(
                  child: CircularProgressIndicator(color: AppColors.ustpNavy),
                ),
              )
            : _loadError != null
                ? Text(_loadError!)
                : _successMessage != null
                    ? Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.check_circle, color: Colors.green),
                          const SizedBox(width: 10),
                          Expanded(child: Text(_successMessage!)),
                        ],
                      )
                    : _departments.isEmpty
                        ? const Text(
                            'There are no active departments to send to.',
                          )
                        : Form(
                            key: _formKey,
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Send a message to active auditees in the selected department.',
                                  style: TextStyle(
                                    color: AppColors.ustpMuted,
                                    fontSize: 13,
                                  ),
                                ),
                                const SizedBox(height: 16),
                                DropdownButtonFormField<String>(
                                  initialValue: _departmentId,
                                  decoration: const InputDecoration(
                                    labelText: 'Department',
                                    border: OutlineInputBorder(),
                                  ),
                                  items: _departments
                                      .map(
                                        (department) =>
                                            DropdownMenuItem<String>(
                                          value: department.id,
                                          child: Text(
                                            department.departmentName,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                      )
                                      .toList(),
                                  onChanged: _isSubmitting
                                      ? null
                                      : (value) => setState(
                                            () => _departmentId = value,
                                          ),
                                  validator: (value) => value == null
                                      ? 'Select a department'
                                      : null,
                                ),
                                const SizedBox(height: 14),
                                TextFormField(
                                  controller: _messageController,
                                  maxLines: 5,
                                  maxLength: 2000,
                                  enabled: !_isSubmitting,
                                  decoration: const InputDecoration(
                                    labelText: 'Message',
                                    hintText: 'Write your notification...',
                                    alignLabelWithHint: true,
                                    border: OutlineInputBorder(),
                                  ),
                                  validator: (value) =>
                                      value == null || value.trim().isEmpty
                                          ? 'Enter a message'
                                          : null,
                                ),
                                if (_submitError != null) ...[
                                  const SizedBox(height: 4),
                                  Text(
                                    _submitError!,
                                    style: const TextStyle(
                                      color: AppColors.error,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
      ),
      actions: _successMessage != null || _departments.isEmpty && !_isLoading
          ? [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Done'),
              ),
            ]
          : [
              TextButton(
                onPressed: _isSubmitting ? null : () => Navigator.pop(context),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: _isLoading || _loadError != null || _isSubmitting
                    ? null
                    : _sendBroadcast,
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.ustpNavy,
                  foregroundColor: AppColors.ustpWhite,
                ),
                child: _isSubmitting
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.ustpWhite,
                        ),
                      )
                    : const Text('Send Broadcast'),
              ),
            ],
    );
  }
}
