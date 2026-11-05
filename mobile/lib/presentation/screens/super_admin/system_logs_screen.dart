import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/audit_log_model.dart';
import '../../../data/repositories/audit_log_repository.dart';
import '../admin/admin_notifications_screen.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class SystemLogsScreen extends StatefulWidget {
  const SystemLogsScreen({super.key});

  @override
  State<SystemLogsScreen> createState() => _SystemLogsScreenState();
}

class _SystemLogsScreenState extends State<SystemLogsScreen> {
  final AuditLogRepository _repository = AuditLogRepository();
  final TextEditingController _searchController = TextEditingController();

  String? _selectedUser;
  String? _selectedAction;
  DateTimeRange? _selectedDateRange;
  bool _isLoading = true;
  List<AuditLogModel> _logs = [];

  @override
  void initState() {
    super.initState();
    _loadLogs();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadLogs() async {
    setState(() => _isLoading = true);
    try {
      final results = await _repository.getAuditLogs(
        userId: _selectedUser,
        action: _selectedAction,
        dateRange: _selectedDateRange,
        search: _searchController.text.trim(),
      );
      if (mounted) {
        setState(() {
          _logs = results;
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _logs = [];
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _pickDateRange() async {
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2025),
      lastDate: DateTime.now(),
      initialDateRange: _selectedDateRange,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppColors.ustpNavy,
              onPrimary: AppColors.ustpWhite,
              surface: AppColors.ustpWhite,
              onSurface: AppColors.ustpDark,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() => _selectedDateRange = picked);
      _loadLogs();
    }
  }

  void _showExportDisabledNotice() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.download_rounded, color: AppColors.ustpNavy, size: 22),
            SizedBox(width: 8),
            Text(
              'Export CSV',
              style: TextStyle(
                fontFamily: 'Poppins',
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
        content: const Text(
          'Exporting system logs to CSV is restricted on mobile devices. Please use the Web Application to export full audit logs.',
          style: TextStyle(fontFamily: 'Inter', fontSize: 14),
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.ustpNavy,
              foregroundColor: AppColors.ustpWhite,
            ),
            onPressed: () => Navigator.pop(context),
            child: const Text('Got it'),
          ),
        ],
      ),
    );
  }

  String _getActionDisplayName(String action) {
    switch (action) {
      case 'Reset password':
        return 'Password Reset';
      case 'Closed CAR':
        return 'Closed CAR';
      case 'Created department':
        return 'Department Created';
      case 'Uploaded evidence':
        return 'Evidence Uploaded';
      default:
        return action;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: UstpAppBar(
        onNotificationTap: () => AdminNotificationsScreen.showOverlay(context),
      ),
      body: Column(
        children: [
          // Filter & Search Controls Header Section
          Container(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
            color: const Color(0xFFF8F9FA),
            child: Column(
              children: [
                // Search Input Box
                Container(
                  height: 44,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: TextField(
                    controller: _searchController,
                    onChanged: (_) => _loadLogs(),
                    style: const TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 14,
                      color: Color(0xFF0F172A),
                    ),
                    decoration: const InputDecoration(
                      hintText: 'Search logs...',
                      hintStyle: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 14,
                        color: Color(0xFF94A3B8),
                      ),
                      prefixIcon: Icon(
                        Icons.search_rounded,
                        color: Color(0xFF94A3B8),
                        size: 20,
                      ),
                      border: InputBorder.none,
                      contentPadding: EdgeInsets.symmetric(vertical: 10),
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // Filters Row (All Users, All Actions, Date Range)
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      // All Users Filter Dropdown
                      PopupMenuButton<String?>(
                        initialValue: _selectedUser,
                        onSelected: (val) {
                          setState(() => _selectedUser = val);
                          _loadLogs();
                        },
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                        color: Colors.white,
                        elevation: 4,
                        itemBuilder: (context) => [
                          const PopupMenuItem<String?>(
                            value: null,
                            child: Text('All Users',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'Rafael Domingo',
                            child: Text('Rafael Domingo',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'Kristine Salcedo',
                            child: Text('Kristine Salcedo',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'Ma. Teresa Uy',
                            child: Text('Ma. Teresa Uy',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'System',
                            child: Text('System',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                        ],
                        child: Container(
                          height: 38,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                _selectedUser ?? 'All Users',
                                style: const TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 13,
                                  fontWeight: FontWeight.w500,
                                  color: Color(0xFF1E293B),
                                ),
                              ),
                              const SizedBox(width: 8),
                              const Icon(
                                Icons.arrow_downward,
                                size: 14,
                                color: Color(0xFF64748B),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // All Actions Filter Dropdown
                      PopupMenuButton<String?>(
                        initialValue: _selectedAction,
                        onSelected: (val) {
                          setState(() => _selectedAction = val);
                          _loadLogs();
                        },
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                        color: Colors.white,
                        elevation: 4,
                        itemBuilder: (context) => [
                          const PopupMenuItem<String?>(
                            value: null,
                            child: Text('All Actions',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'Reset password',
                            child: Text('Password Reset',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'Closed CAR',
                            child: Text('Closed CAR',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'Created department',
                            child: Text('Department Created',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                          const PopupMenuItem<String?>(
                            value: 'Uploaded evidence',
                            child: Text('Evidence Uploaded',
                                style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A))),
                          ),
                        ],
                        child: Container(
                          height: 38,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                _selectedAction == null
                                    ? 'All Actions'
                                    : _getActionDisplayName(_selectedAction!),
                                style: const TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 13,
                                  fontWeight: FontWeight.w500,
                                  color: Color(0xFF1E293B),
                                ),
                              ),
                              const SizedBox(width: 8),
                              const Icon(
                                Icons.arrow_downward,
                                size: 14,
                                color: Color(0xFF64748B),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // Date Range Filter Button
                      GestureDetector(
                        onTap: _pickDateRange,
                        child: Container(
                          height: 38,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.calendar_today_rounded,
                                  size: 13, color: Color(0xFF64748B)),
                              const SizedBox(width: 6),
                              Text(
                                _selectedDateRange == null
                                    ? 'Date Range'
                                    : '${DateFormat('MMM d').format(_selectedDateRange!.start)} - ${DateFormat('MMM d').format(_selectedDateRange!.end)}',
                                style: const TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 13,
                                  fontWeight: FontWeight.w500,
                                  color: Color(0xFF1E293B),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),

                // Export CSV Button (Right Aligned)
                Align(
                  alignment: Alignment.centerRight,
                  child: Material(
                    color: const Color(0xFFFBBF24),
                    borderRadius: BorderRadius.circular(10),
                    child: InkWell(
                      onTap: _showExportDisabledNotice,
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        height: 38,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 8),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.file_download_outlined,
                              size: 16,
                              color: Color(0xFF0F172A),
                            ),
                            SizedBox(width: 6),
                            Text(
                              'Export CSV',
                              style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13.5,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Audit Log Cards Container
          Expanded(
            child: _isLoading
                ? const Center(
                    child: CircularProgressIndicator(
                        color: AppColors.ustpNavy))
                : _logs.isEmpty
                    ? const EmptyState(
                        title: 'No audit logs found',
                        subtitle: 'No system logs match your filter criteria.',
                        icon: AppIcons.settings,
                      )
                    : RefreshIndicator(
                        color: AppColors.ustpNavy,
                        onRefresh: _loadLogs,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                    color: const Color(0xFFE2E8F0)),
                              ),
                              child: ListView.separated(
                                shrinkWrap: true,
                                physics:
                                    const NeverScrollableScrollPhysics(),
                                itemCount: _logs.length,
                                separatorBuilder: (context, index) =>
                                    const Divider(
                                  height: 1,
                                  thickness: 1,
                                  color: Color(0xFFF1F5F9),
                                ),
                                itemBuilder: (context, index) {
                                  final log = _logs[index];
                                  return _buildAuditLogRow(log);
                                },
                              ),
                            ),
                          ],
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildAuditLogRow(AuditLogModel log) {
    final formattedTime = DateFormat('yyyy-MM-dd HH:mm').format(log.createdAt);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: User Name & Timestamp
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                log.userName,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 14.5,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF0F172A),
                ),
              ),
              Text(
                formattedTime,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 12,
                  color: Color(0xFF64748B),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),

          // Action Description
          Text(
            log.action,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 13.5,
              color: Color(0xFF64748B),
              fontWeight: FontWeight.w400,
            ),
          ),
        ],
      ),
    );
  }
}
