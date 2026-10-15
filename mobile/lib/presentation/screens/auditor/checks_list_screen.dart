import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/auditor_page_header.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/ustp_app_bar.dart';

class ChecksListScreen extends StatefulWidget {
  const ChecksListScreen({super.key});

  @override
  State<ChecksListScreen> createState() => _ChecksListScreenState();
}

class _ChecksListScreenState extends State<ChecksListScreen> {
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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/auditor/checks/create'),
        backgroundColor: AppColors.ustpGold,
        foregroundColor: AppColors.ustpDark,
        icon: const Icon(AppIcons.plus),
        label: const Text(
          'New Compliance Check',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: FutureBuilder<List<ComplianceCheckModel>>(
        future: _checks,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _ChecksError(onRetry: _refresh);
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final checks = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 92),
              children: [
                const AuditorPageHeader(
                  title: 'Compliance Checks',
                  subtitle: 'Auditor · Assigned departments',
                ),
                if (checks.isEmpty)
                  const SizedBox(
                    height: 320,
                    child: EmptyState(
                      title: 'No compliance checks',
                      subtitle: 'Create a check to record a compliance result.',
                      icon: AppIcons.compliance,
                    ),
                  )
                else
                  for (final check in checks)
                    _ComplianceCheckCard(check: check),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ComplianceCheckCard extends StatelessWidget {
  const _ComplianceCheckCard({required this.check});

  final ComplianceCheckModel check;

  @override
  Widget build(BuildContext context) {
    final date = DateFormat('yyyy-MM-dd').format(check.createdAt.toLocal());
    final correctiveStatus = check.correctiveStatus;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(12, 11, 12, 8),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
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
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpNavy,
                  ),
                ),
              ),
              Text(
                date,
                style: const TextStyle(
                  fontSize: 9,
                  color: AppColors.ustpMuted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          _DetailLine(
            label: 'Dept / Auditee:',
            value: [
              check.departmentName ?? check.departmentId,
              check.auditeeName ?? 'Auditee',
            ].join(' · '),
          ),
          const SizedBox(height: 4),
          _DetailLine(
            label: 'Reference Standard:',
            value: check.referenceStandard,
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 5,
            children: [
              StatusBadge(status: check.result),
              if (correctiveStatus != null)
                StatusBadge(status: correctiveStatus)
              else
                const StatusBadge(status: 'closed'),
            ],
          ),
          const Padding(
            padding: EdgeInsets.only(top: 7),
            child: Divider(height: 1, color: Color(0xFFE2E8F0)),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton(
                onPressed: () => context.push('/auditor/checks/${check.id}'),
                style: TextButton.styleFrom(
                  minimumSize: const Size(42, 30),
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  foregroundColor: AppColors.ustpNavy,
                  textStyle: const TextStyle(fontSize: 10),
                ),
                child: const Text('View'),
              ),
              TextButton(
                onPressed: () => context.push('/auditor/checks/${check.id}'),
                style: TextButton.styleFrom(
                  minimumSize: const Size(42, 30),
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  foregroundColor: AppColors.warning,
                  textStyle: const TextStyle(fontSize: 10),
                ),
                child: const Text('Status'),
              ),
              TextButton(
                onPressed: () => context.push('/auditor/checks/${check.id}'),
                style: TextButton.styleFrom(
                  minimumSize: const Size(42, 30),
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  foregroundColor: AppColors.ustpNavy,
                  textStyle: const TextStyle(fontSize: 10),
                ),
                child: const Text('Evidence'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 92,
          child: Text(
            label,
            style: const TextStyle(fontSize: 9, color: AppColors.ustpMuted),
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w600,
              color: AppColors.ustpDark,
            ),
          ),
        ),
      ],
    );
  }
}

class _ChecksError extends StatelessWidget {
  const _ChecksError({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('Could not load compliance checks.'),
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
