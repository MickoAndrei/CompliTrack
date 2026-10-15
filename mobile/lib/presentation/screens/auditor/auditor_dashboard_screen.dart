import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:percent_indicator/circular_percent_indicator.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/auditor_page_header.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/ustp_app_bar.dart';

class AuditorDashboardScreen extends StatefulWidget {
  const AuditorDashboardScreen({super.key});

  @override
  State<AuditorDashboardScreen> createState() => _AuditorDashboardScreenState();
}

class _AuditorDashboardScreenState extends State<AuditorDashboardScreen> {
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
        onNotificationTap: () => context.go('/auditor/profile/notifications'),
      ),
      body: FutureBuilder<List<ComplianceCheckModel>>(
        future: _checks,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _LoadError(
              message: 'Could not load assigned checks.',
              onRetry: _refresh,
            );
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }

          final checks = snapshot.data!;
          final compliant =
              checks.where((check) => check.result == 'compliant').length;
          final nonCompliant =
              checks.where((check) => check.result == 'non_compliant').length;
          final open =
              checks.where((check) => check.correctiveStatus == 'open').length;
          final closedThisMonth = checks.where((check) {
            final closedAt = check.closedAt;
            final now = DateTime.now();
            return closedAt != null &&
                closedAt.year == now.year &&
                closedAt.month == now.month;
          }).length;

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 18),
              children: [
                Row(
                  children: [
                    const Expanded(
                      child: AuditorPageHeader(
                        title: 'Performance Overview',
                        subtitle: 'Your compliance activity at a glance',
                      ),
                    ),
                    FilledButton.icon(
                      onPressed: () => context.push('/auditor/checks/create'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.ustpGold,
                        foregroundColor: AppColors.ustpDark,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 11,
                          vertical: 10,
                        ),
                        textStyle: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      icon: const Icon(AppIcons.plus, size: 16),
                      label: const Text('New Check'),
                    ),
                  ],
                ),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisSpacing: 8,
                  mainAxisSpacing: 8,
                  childAspectRatio: 1.85,
                  children: [
                    _MetricCard(
                      title: 'TOTAL CHECKS CONDUCTED',
                      value: checks.length,
                      icon: AppIcons.compliance,
                    ),
                    _MetricCard(
                      title: 'CLOSED THIS MONTH',
                      value: closedThisMonth,
                      icon: AppIcons.closed,
                      iconColor: AppColors.success,
                    ),
                    _MetricCard(
                      title: 'OPEN CARs ASSIGNED',
                      value: open,
                      icon: AppIcons.open,
                      iconColor: AppColors.error,
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: _cardDecoration(),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Compliant vs Non-Compliant',
                        style: TextStyle(
                          fontFamily: 'Poppins',
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.ustpDark,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          CircularPercentIndicator(
                            radius: 38,
                            lineWidth: 9,
                            percent: checks.isEmpty
                                ? 0
                                : compliant / checks.length,
                            circularStrokeCap: CircularStrokeCap.butt,
                            progressColor: AppColors.success,
                            backgroundColor: AppColors.error,
                            center: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  '${checks.length}',
                                  style: const TextStyle(
                                    fontFamily: 'Poppins',
                                    fontWeight: FontWeight.bold,
                                    fontSize: 17,
                                    color: AppColors.ustpDark,
                                  ),
                                ),
                                const Text(
                                  'TOTAL',
                                  style: TextStyle(
                                    fontSize: 8,
                                    color: AppColors.ustpMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 18),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _LegendRow(
                                color: AppColors.success,
                                label: '$compliant Compliant',
                              ),
                              const SizedBox(height: 8),
                              _LegendRow(
                                color: AppColors.error,
                                label: '$nonCompliant Non-Compliant',
                              ),
                            ],
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: _cardDecoration(),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Recent Status Changes',
                        style: TextStyle(
                          fontFamily: 'Poppins',
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.ustpDark,
                        ),
                      ),
                      const SizedBox(height: 8),
                      if (checks.isEmpty)
                        const EmptyState(
                          title: 'No recent checks',
                          subtitle: 'Your latest check activity will appear here.',
                          icon: AppIcons.compliance,
                        )
                      else
                        for (final check in checks.take(3))
                          _RecentCheckRow(check: check),
                    ],
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

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.title,
    required this.value,
    required this.icon,
    this.iconColor = AppColors.ustpNavy,
  });

  final String title;
  final int value;
  final IconData icon;
  final Color iconColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(9),
      decoration: _cardDecoration(),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: AppColors.ustpLight,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(icon, size: 18, color: iconColor),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 8,
                    color: AppColors.ustpMuted,
                  ),
                ),
                Text(
                  '$value',
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 17,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
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

class _LegendRow extends StatelessWidget {
  const _LegendRow({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(fontSize: 10, color: AppColors.ustpDark),
        ),
      ],
    );
  }
}

class _RecentCheckRow extends StatelessWidget {
  const _RecentCheckRow({required this.check});

  final ComplianceCheckModel check;

  @override
  Widget build(BuildContext context) {
    final status = check.correctiveStatus ?? check.result;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          const Icon(Icons.circle, color: AppColors.ustpNavy, size: 5),
          const SizedBox(width: 7),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${check.checkCode} ${check.correctiveStatus == null ? 'marked ${check.result.replaceAll('_', '-')}' : 'moved to ${_label(check.correctiveStatus!)}'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ustpDark,
                  ),
                ),
                Text(
                  DateFormat('MMM d').format(check.createdAt.toLocal()),
                  style: const TextStyle(
                    fontSize: 8,
                    color: AppColors.ustpMuted,
                  ),
                ),
              ],
            ),
          ),
          StatusBadge(status: status),
        ],
      ),
    );
  }
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(AppIcons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

BoxDecoration _cardDecoration() => BoxDecoration(
      color: AppColors.ustpWhite,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: const Color(0xFFE2E8F0)),
    );

String _label(String status) => switch (status) {
      'in_progress' => 'In Progress',
      'open' => 'Open',
      'closed' => 'Closed',
      _ => status,
    };
