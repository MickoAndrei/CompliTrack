import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:percent_indicator/circular_percent_indicator.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import 'upload_evidence_screen.dart';
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

  Future<void> _refresh() async {
    final next = _repository.getChecks();
    setState(() => _checks = next);
    await next;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: AppColors.ustpLight,
        appBar: UstpAppBar(
          onProfileTap: () => context.go('/auditee/profile'),
        ),
        body: FutureBuilder<List<ComplianceCheckModel>>(
          future: _checks,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting &&
                !snapshot.hasData) {
              return const Center(
                child: CircularProgressIndicator(color: AppColors.ustpNavy),
              );
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'Could not load your compliance data.',
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: _refresh,
                        child: const Text('Try again'),
                      ),
                    ],
                  ),
                ),
              );
            }

            final checks = snapshot.data ?? const <ComplianceCheckModel>[];
            final compliant =
                checks.where((check) => check.result == 'compliant').length;
            final openChecks = checks
                .where((check) => check.correctiveStatus == 'open')
                .toList();
            final inProgress = checks
                .where((check) => check.correctiveStatus == 'in_progress')
                .length;
            final closed = checks
                .where((check) => check.correctiveStatus == 'closed')
                .length;
            final percentage =
                checks.isEmpty ? 0.0 : compliant * 100 / checks.length;
            final overdue = openChecks
                .where((check) =>
                    DateTime.now()
                        .difference(check.createdAt.toLocal())
                        .inDays >=
                    7)
                .toList();

            return RefreshIndicator(
              color: AppColors.ustpNavy,
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                children: [
                  if (overdue.isNotEmpty) ...[
                    _OverdueBanner(check: overdue.first),
                    const SizedBox(height: 16),
                  ],
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                          child: _PersonalStatusCard(percentage: percentage)),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _IssueCountCard(count: openChecks.length),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: _ProgressCountCard(
                          label: 'IN PROGRESS',
                          count: inProgress,
                          color: const Color(0xFFEA8A00),
                          tint: const Color(0xFFFFFBEB),
                          icon: Icons.schedule_rounded,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _ProgressCountCard(
                          label: 'CLOSED',
                          count: closed,
                          color: const Color(0xFF16A34A),
                          tint: const Color(0xFFF0FDF4),
                          icon: Icons.check_circle_outline_rounded,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _QuickUploadCard(
                    onTap: () => UploadEvidenceScreen.showQuick(context),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Latest Findings',
                        style: TextStyle(
                          fontFamily: 'Poppins',
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ustpDark,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (checks.isEmpty)
                    const _EmptyFindingsCard()
                  else
                    for (final check in checks.take(2)) ...[
                      _LatestFindingCard(check: check),
                      const SizedBox(height: 10),
                    ],
                ],
              ),
            );
          },
        ),
      );
}

class _OverdueBanner extends StatelessWidget {
  const _OverdueBanner({required this.check});
  final ComplianceCheckModel check;

  @override
  Widget build(BuildContext context) {
    final days = DateTime.now().difference(check.createdAt.toLocal()).inDays;
    return Container(
      height: 41,
      padding: const EdgeInsets.symmetric(horizontal: 11),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF5F5),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: const Color(0xFFFFA3A3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.warning_rounded, size: 18, color: Color(0xFFB42318)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              '${check.checkCode} Open for $days days – please upload evidence.',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 10,
                color: Color(0xFFB42318),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PersonalStatusCard extends StatelessWidget {
  const _PersonalStatusCard({required this.percentage});
  final double percentage;

  @override
  Widget build(BuildContext context) => Container(
        height: 64,
        padding: const EdgeInsets.symmetric(horizontal: 11),
        decoration: _dashboardCardDecoration(),
        child: Row(
          children: [
            CircularPercentIndicator(
              radius: 20,
              lineWidth: 4,
              percent: (percentage / 100).clamp(0.0, 1.0),
              center: Text(
                '${percentage.round()}%',
                style: const TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ustpNavy,
                ),
              ),
              progressColor: AppColors.ustpNavy,
              backgroundColor: const Color(0xFFE9EEF5),
              circularStrokeCap: CircularStrokeCap.round,
            ),
            const SizedBox(width: 10),
            const Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'COMPLIANCE',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 9,
                      color: AppColors.ustpMuted,
                    ),
                  ),
                  Text(
                    'Personal Status',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontFamily: 'Poppins',
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ustpNavy,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
}

class _IssueCountCard extends StatelessWidget {
  const _IssueCountCard({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) => Container(
        height: 78,
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF5F5),
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: const Color(0xFFFFA3A3)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'MY OPEN ISSUES',
              style: TextStyle(
                fontFamily: 'Inter',
                fontSize: 9,
                color: Color(0xFFB42318),
              ),
            ),
            const Spacer(),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '$count',
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 23,
                    height: 1,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFFB42318),
                  ),
                ),
                const Icon(Icons.error_outline_rounded,
                    size: 20, color: Color(0xFFFF3B3B)),
              ],
            ),
          ],
        ),
      );
}

class _ProgressCountCard extends StatelessWidget {
  const _ProgressCountCard({
    required this.label,
    required this.count,
    required this.color,
    required this.tint,
    required this.icon,
  });

  final String label;
  final int count;
  final Color color;
  final Color tint;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
        height: 78,
        padding: const EdgeInsets.fromLTRB(11, 10, 12, 8),
        decoration: BoxDecoration(
          color: tint,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: color.withValues(alpha: .4)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: TextStyle(
                fontFamily: 'Inter',
                fontSize: 9,
                color: color == const Color(0xFFEA8A00)
                    ? const Color(0xFF7A4A00)
                    : const Color(0xFF166534),
              ),
            ),
            const Spacer(),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '$count',
                  style: TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 23,
                    height: 1,
                    fontWeight: FontWeight.w700,
                    color: color == const Color(0xFFEA8A00)
                        ? AppColors.ustpDark
                        : const Color(0xFF166534),
                  ),
                ),
                Icon(icon, size: 20, color: color),
              ],
            ),
          ],
        ),
      );
}

class _QuickUploadCard extends StatelessWidget {
  const _QuickUploadCard({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.ustpNavy,
          borderRadius: BorderRadius.circular(13),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ready to upload evidence?',
              style: TextStyle(
                fontFamily: 'Poppins',
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: AppColors.ustpGold,
              ),
            ),
            const SizedBox(height: 3),
            const Text(
              'File scanned docs directly to the audit pool.',
              style: TextStyle(
                fontFamily: 'Inter',
                fontSize: 10,
                color: Color(0xFFD6E0F1),
              ),
            ),
            const SizedBox(height: 11),
            SizedBox(
              width: double.infinity,
              height: 36,
              child: ElevatedButton(
                onPressed: onTap,
                child: const FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.cloud_upload_outlined, size: 16),
                      SizedBox(width: 7),
                      Text('Quick Upload', maxLines: 1, softWrap: false),
                    ],
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.ustpGold,
                  foregroundColor: AppColors.ustpNavy,
                  elevation: 0,
                  textStyle: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
}

class _LatestFindingCard extends StatelessWidget {
  const _LatestFindingCard({required this.check});
  final ComplianceCheckModel check;

  @override
  Widget build(BuildContext context) {
    final status = switch (check.correctiveStatus) {
      'open' => ('Open', const Color(0xFFB42318), const Color(0xFFFFF5F5)),
      'closed' => ('Closed', const Color(0xFF15803D), const Color(0xFFF0FDF4)),
      'in_progress' => (
          'In Progress',
          const Color(0xFF854D0E),
          const Color(0xFFFFFBEB)
        ),
      _ => ('Compliant', const Color(0xFF15803D), const Color(0xFFF0FDF4)),
    };

    return Container(
      padding: const EdgeInsets.fromLTRB(11, 10, 11, 11),
      decoration: _dashboardCardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  check.checkCode,
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ustpNavy,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: status.$3,
                  border: Border.all(color: status.$2.withValues(alpha: .35)),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Text(
                  status.$1,
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 9,
                    color: status.$2,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            _displayStandard(check.referenceStandard),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 11,
              color: AppColors.ustpDark,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            check.remarks ?? check.departmentName ?? '',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 10,
              color: AppColors.ustpMuted,
            ),
          ),
        ],
      ),
    );
  }
}

String _displayStandard(String value) => switch (value) {
      'ISO9001-9.2' => 'ISO 9001',
      'ARTA-3days' => 'ARTA Compliance 3',
      'ARTA-7days' => 'ARTA Compliance 7',
      'ARTA-20days' => 'ARTA Compliance 20',
      _ => value,
    };

class _EmptyFindingsCard extends StatelessWidget {
  const _EmptyFindingsCard();

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: _dashboardCardDecoration(),
        child: const Text(
          'No checks assigned yet.',
          style: TextStyle(
            fontFamily: 'Inter',
            fontSize: 12,
            color: AppColors.ustpMuted,
          ),
        ),
      );
}

BoxDecoration _dashboardCardDecoration() => BoxDecoration(
      color: AppColors.ustpWhite,
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: const Color(0xFFE2E8F0)),
    );
