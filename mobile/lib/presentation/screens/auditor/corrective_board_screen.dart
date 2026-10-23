import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/auditor_page_header.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class CorrectiveBoardScreen extends StatefulWidget {
  const CorrectiveBoardScreen({super.key});

  @override
  State<CorrectiveBoardScreen> createState() => _CorrectiveBoardScreenState();
}

class _CorrectiveBoardScreenState extends State<CorrectiveBoardScreen> {
  final _repository = ComplianceRepository();
  late Future<List<ComplianceCheckModel>> _checks;

  @override
  void initState() {
    super.initState();
    _checks = _repository.getChecks(perPage: 100);
  }

  Future<void> _refresh() async {
    setState(() => _checks = _repository.getChecks(perPage: 100));
    await _checks;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: UstpAppBar(
        onNotificationTap: () async =>
            context.go('/auditor/profile/notifications'),
      ),
      body: FutureBuilder<List<ComplianceCheckModel>>(
        future: _checks,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _BoardError(onRetry: _refresh);
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final actions = snapshot.data!
              .where((check) => check.correctiveStatus != null)
              .toList();
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 18),
              children: [
                const AuditorPageHeader(
                  title: 'Corrective Actions',
                  subtitle: 'Review and manage actions timeline',
                ),
                for (final status in const ['in_progress', 'open', 'closed'])
                  _StatusSection(
                    status: status,
                    checks: actions
                        .where((check) => check.correctiveStatus == status)
                        .toList(),
                  ),
                if (actions.isEmpty)
                  const SizedBox(
                    height: 260,
                    child: EmptyState(
                      title: 'No corrective actions',
                      subtitle:
                          'Non-compliant checks will appear here when assigned.',
                      icon: AppIcons.inProgress,
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _StatusSection extends StatelessWidget {
  const _StatusSection({required this.status, required this.checks});

  final String status;
  final List<ComplianceCheckModel> checks;

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'in_progress' => ('In Progress', AppColors.warning),
      'open' => ('Open', AppColors.error),
      _ => ('Closed', AppColors.success),
    };

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(2, 4, 2, 5),
            child: Row(
              children: [
                Container(
                  width: 7,
                  height: 7,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
                const Spacer(),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: const BoxDecoration(
                    color: Color(0xFFE2E8F0),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '${checks.length}',
                    style: const TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.bold,
                      color: AppColors.ustpMuted,
                    ),
                  ),
                ),
              ],
            ),
          ),
          for (final check in checks)
            _ActionCard(
              check: check,
              onTap: () => _showStatusHistory(context, check),
              onOpen: () => context.push('/auditor/checks/${check.id}'),
            ),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.check,
    required this.onTap,
    required this.onOpen,
  });

  final ComplianceCheckModel check;
  final VoidCallback onTap;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(9),
      child: Container(
        margin: const EdgeInsets.only(bottom: 6),
        padding: const EdgeInsets.fromLTRB(9, 8, 7, 6),
        decoration: BoxDecoration(
          color: AppColors.ustpWhite,
          borderRadius: BorderRadius.circular(9),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    check.checkCode,
                    style: const TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.bold,
                      color: AppColors.ustpNavy,
                    ),
                  ),
                ),
                if (check.departmentName != null)
                  Text(
                    check.departmentName!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 8,
                      color: AppColors.ustpMuted,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              check.remarks?.isNotEmpty == true
                  ? check.remarks!
                  : check.referenceStandard,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w600,
                color: AppColors.ustpDark,
              ),
            ),
            const Padding(
              padding: EdgeInsets.only(top: 5),
              child: Divider(height: 1, color: Color(0xFFE2E8F0)),
            ),
            Row(
              children: [
                const CircleAvatar(
                  radius: 8,
                  backgroundColor: AppColors.ustpGold,
                  child:
                      Icon(AppIcons.user, size: 10, color: AppColors.ustpNavy),
                ),
                const Spacer(),
                Text(
                  check.statusHistory.isNotEmpty
                      ? _formatDate(check.statusHistory.last['timestamp'])
                      : DateFormat('yyyy-MM-dd')
                          .format(check.createdAt.toLocal()),
                  style: const TextStyle(
                    fontSize: 8,
                    color: AppColors.ustpMuted,
                  ),
                ),
                IconButton(
                  tooltip: 'Open check details',
                  visualDensity: VisualDensity.compact,
                  onPressed: onOpen,
                  icon: const Icon(
                    AppIcons.compliance,
                    size: 15,
                    color: AppColors.ustpNavy,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

Future<void> _showStatusHistory(
  BuildContext context,
  ComplianceCheckModel check,
) {
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => Dialog(
      backgroundColor: AppColors.ustpWhite,
      insetPadding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 390),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${check.checkCode} - Status History',
                      style: const TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpDark,
                      ),
                    ),
                  ),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    onPressed: () => Navigator.pop(dialogContext),
                    icon: const Icon(AppIcons.close, size: 17),
                  ),
                ],
              ),
              const SizedBox(height: 3),
              Text(
                check.remarks?.isNotEmpty == true
                    ? check.remarks!
                    : check.referenceStandard,
                style: const TextStyle(
                  fontSize: 10,
                  color: AppColors.ustpMuted,
                ),
              ),
              Text(
                'Department: ${check.departmentName ?? check.departmentId}',
                style: const TextStyle(
                  fontSize: 10,
                  color: AppColors.ustpMuted,
                ),
              ),
              const SizedBox(height: 13),
              if (check.statusHistory.isEmpty)
                const Text(
                  'No status history is available for this action.',
                  style: TextStyle(fontSize: 11, color: AppColors.ustpMuted),
                )
              else
                for (var index = 0; index < check.statusHistory.length; index++)
                  _HistoryItem(
                    entry: check.statusHistory[index],
                  ),
              const SizedBox(height: 12),
              Align(
                alignment: Alignment.centerRight,
                child: FilledButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.ustpNavy,
                    foregroundColor: AppColors.ustpWhite,
                    minimumSize: const Size(72, 36),
                  ),
                  child: const Text('Close'),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _HistoryItem extends StatelessWidget {
  const _HistoryItem({required this.entry});

  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final status = entry['status']?.toString() ?? 'unknown';
    final (label, color, icon) = switch (status) {
      'open' => ('Opened', AppColors.error, AppIcons.open),
      'in_progress' => ('In Progress', AppColors.warning, AppIcons.inProgress),
      'closed' => ('Closed', AppColors.success, AppIcons.closed),
      _ => (status, AppColors.ustpMuted, AppIcons.compliance),
    };

    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 20,
            height: 20,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: color, size: 12),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
                Text(
                  _formatDate(entry['timestamp']),
                  style: const TextStyle(
                    fontSize: 9,
                    color: AppColors.ustpMuted,
                  ),
                ),
                if (entry['remarks'] is String &&
                    (entry['remarks'] as String).isNotEmpty)
                  Text(
                    entry['remarks'] as String,
                    style: const TextStyle(
                      fontSize: 9,
                      color: AppColors.ustpMuted,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BoardError extends StatelessWidget {
  const _BoardError({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('Could not load corrective actions.'),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(AppIcons.refresh),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}

String _formatDate(dynamic timestamp) {
  final parsed = timestamp is DateTime
      ? timestamp
      : DateTime.tryParse(timestamp?.toString() ?? '');
  return parsed == null
      ? 'Date unavailable'
      : DateFormat('yyyy-MM-dd').format(parsed.toLocal());
}
