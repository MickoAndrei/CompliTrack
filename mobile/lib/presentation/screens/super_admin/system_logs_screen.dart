import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/audit_log_model.dart';
import '../../../data/repositories/audit_log_repository.dart';
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
            Icon(AppIcons.lock, color: AppColors.warning, size: 22),
            SizedBox(width: 8),
            Text(
              'Manage on Web',
              style: TextStyle(
                fontFamily: 'Poppins',
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
        content: const Text(
          'Exporting system logs to CSV is restricted on mobile devices. Please log in to the Web Application to export full audit logs.',
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        onNotificationTap: () {},
        onProfileTap: () {},
      ),
      body: Column(
        children: [
          // Filter & Search Controls Header
          Container(
            padding: const EdgeInsets.all(16),
            color: AppColors.ustpWhite,
            child: Column(
              children: [
                // Search Input Box
                TextField(
                  controller: _searchController,
                  onChanged: (_) => _loadLogs(),
                  decoration: InputDecoration(
                    hintText: 'Search logs...',
                    hintStyle: const TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 14,
                      color: AppColors.ustpMuted,
                    ),
                    prefixIcon: const Icon(AppIcons.search, color: AppColors.ustpMuted, size: 20),
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                    fillColor: AppColors.ustpLight,
                    filled: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
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
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.ustpLight,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String?>(
                            value: _selectedUser,
                            hint: const Text(
                              'All Users',
                              style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: AppColors.ustpDark,
                              ),
                            ),
                            icon: const Icon(AppIcons.chevronDown, size: 16, color: AppColors.ustpDark),
                            items: const [
                              DropdownMenuItem(value: null, child: Text('All Users')),
                              DropdownMenuItem(value: 'Rafael Domingo', child: Text('Rafael Domingo')),
                              DropdownMenuItem(value: 'Kristine Salcedo', child: Text('Kristine Salcedo')),
                              DropdownMenuItem(value: 'Ma. Teresa Uy', child: Text('Ma. Teresa Uy')),
                              DropdownMenuItem(value: 'System', child: Text('System')),
                            ],
                            onChanged: (val) {
                              setState(() => _selectedUser = val);
                              _loadLogs();
                            },
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // All Actions Filter Dropdown
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.ustpLight,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String?>(
                            value: _selectedAction,
                            hint: const Text(
                              'All Actions',
                              style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: AppColors.ustpDark,
                              ),
                            ),
                            icon: const Icon(AppIcons.chevronDown, size: 16, color: AppColors.ustpDark),
                            items: const [
                              DropdownMenuItem(value: null, child: Text('All Actions')),
                              DropdownMenuItem(value: 'Reset password', child: Text('Password Reset')),
                              DropdownMenuItem(value: 'Closed CAR', child: Text('Closed CAR')),
                              DropdownMenuItem(value: 'Created department', child: Text('Department Created')),
                              DropdownMenuItem(value: 'Uploaded evidence', child: Text('Evidence Uploaded')),
                            ],
                            onChanged: (val) {
                              setState(() => _selectedAction = val);
                              _loadLogs();
                            },
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // Date Range Filter Button
                      OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          side: BorderSide(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
                          backgroundColor: _selectedDateRange != null
                              ? AppColors.ustpNavy.withValues(alpha: 0.08)
                              : AppColors.ustpLight,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        onPressed: _pickDateRange,
                        icon: const Icon(Icons.calendar_today_outlined, size: 14, color: AppColors.ustpDark),
                        label: Text(
                          _selectedDateRange == null
                              ? 'Date Range'
                              : '${DateFormat('MMM d').format(_selectedDateRange!.start)} - ${DateFormat('MMM d').format(_selectedDateRange!.end)}',
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ustpDark,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),

                // Export CSV Row (Right Aligned)
                Align(
                  alignment: Alignment.centerRight,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.ustpGold,
                      foregroundColor: AppColors.ustpDark,
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: _showExportDisabledNotice,
                    icon: const Icon(AppIcons.lock, size: 16, color: AppColors.ustpDark),
                    label: const Text(
                      'Export CSV',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
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
                ? const Center(child: CircularProgressIndicator(color: AppColors.ustpNavy))
                : _logs.isEmpty
                    ? const EmptyState(
                        title: 'No audit logs found',
                        subtitle: 'No system logs match your filter criteria.',
                        icon: AppIcons.settings,
                      )
                    : RefreshIndicator(
                        color: AppColors.ustpNavy,
                        onRefresh: _loadLogs,
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(16),
                          physics: const AlwaysScrollableScrollPhysics(),
                          child: Container(
                            decoration: BoxDecoration(
                              color: AppColors.ustpWhite,
                              borderRadius: BorderRadius.circular(16),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.04),
                                  blurRadius: 8,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: ListView.separated(
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
                              itemCount: _logs.length,
                              separatorBuilder: (context, index) => const Divider(
                                height: 1,
                                color: AppColors.ustpLight,
                              ),
                              itemBuilder: (context, index) {
                                final log = _logs[index];
                                return _buildAuditLogRow(log);
                              },
                            ),
                          ),
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
                  fontFamily: 'Poppins',
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: AppColors.ustpDark,
                ),
              ),
              Text(
                formattedTime,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 12,
                  color: AppColors.ustpMuted,
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
              fontSize: 13,
              color: AppColors.ustpMuted,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}
