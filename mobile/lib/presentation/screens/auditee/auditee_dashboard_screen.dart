import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/compliance_gauge.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/ustp_app_bar.dart';

class AuditeeDashboardScreen extends StatefulWidget {
  const AuditeeDashboardScreen({super.key});

  @override
  State<AuditeeDashboardScreen> createState() => _AuditeeDashboardScreenState();
}

class _AuditeeDashboardScreenState extends State<AuditeeDashboardScreen> {
  final _repository = ComplianceRepository();
  late Future<List<ComplianceCheckModel>> _checks;

  @override
  void initState() {
    super.initState();
    _checks = _repository.getChecks();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: const UstpAppBar(title: 'Auditee Dashboard'),
      body: FutureBuilder<List<ComplianceCheckModel>>(
        future: _checks,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  'Could not load your compliance data.\n${snapshot.error}',
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }

          final checks = snapshot.data!;
          final compliant = checks.where((check) => check.result == 'compliant').length;
          final open = checks.where((check) => check.correctiveStatus == 'open').length;
          final inProgress = checks.where((check) => check.correctiveStatus == 'in_progress').length;
          final overdue = checks.where((check) {
            return check.correctiveStatus == 'open'
                && check.createdAt.isBefore(DateTime.now().subtract(const Duration(days: 7)));
          }).toList();
          final percentage = checks.isEmpty ? 0.0 : compliant * 100 / checks.length;

          return RefreshIndicator(
            onRefresh: () async {
              setState(() => _checks = _repository.getChecks());
              await _checks;
            },
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (overdue.isNotEmpty)
                  Card(
                    color: const Color(0xFFFEF2F2),
                    child: ListTile(
                      leading: const Icon(Icons.warning_amber_rounded, color: AppColors.error),
                      title: Text('${overdue.length} open finding(s) are older than 7 days'),
                      subtitle: Text(overdue.map((check) => check.checkCode).join(', ')),
                    ),
                  ),
                ComplianceGauge(
                  percentage: percentage,
                  title: 'COMPLIANCE RATE',
                  subtitle: '${checks.length} assigned checks',
                ),
                const SizedBox(height: 12),
                _MetricRow(
                  open: open,
                  inProgress: inProgress,
                  compliant: compliant,
                ),
                const SizedBox(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Recent findings', style: Theme.of(context).textTheme.titleMedium),
                    TextButton(
                      onPressed: () => context.go('/auditee/status'),
                      child: const Text('View all'),
                    ),
                  ],
                ),
                if (checks.isEmpty)
                  const EmptyState(
                    title: 'No checks assigned',
                    subtitle: 'Your assigned compliance checks will appear here.',
                  )
                else
                  ...checks.take(5).map((check) => Card(
                        child: ListTile(
                          title: Text(check.checkCode),
                          subtitle: Text(check.remarks ?? check.referenceStandard),
                          trailing: StatusBadge(
                            status: check.correctiveStatus ?? check.result,
                          ),
                          onTap: () => context.go('/auditee/status'),
                        ),
                      )),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () => context.go('/auditee/archives'),
                  icon: const Icon(Icons.archive_outlined),
                  label: const Text('View my archives'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _MetricRow extends StatelessWidget {
  const _MetricRow({
    required this.open,
    required this.inProgress,
    required this.compliant,
  });

  final int open;
  final int inProgress;
  final int compliant;

  @override
  Widget build(BuildContext context) => Row(
        children: [
          _Metric(label: 'Open', value: open, color: AppColors.error),
          const SizedBox(width: 8),
          _Metric(label: 'In progress', value: inProgress, color: AppColors.ustpGold),
          const SizedBox(width: 8),
          _Metric(label: 'Compliant', value: compliant, color: AppColors.success),
        ],
      );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value, required this.color});

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                Text('$value', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: color)),
                Text(label, textAlign: TextAlign.center),
              ],
            ),
          ),
        ),
      );
}
