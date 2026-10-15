import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../../core/utils/role_helper.dart';
import 'finding_detail_screen.dart';
import 'upload_evidence_screen.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/ustp_app_bar.dart';

class MyFindingsScreen extends StatefulWidget {
  const MyFindingsScreen({super.key});

  @override
  State<MyFindingsScreen> createState() => _MyFindingsScreenState();
}

class _MyFindingsScreenState extends State<MyFindingsScreen> {
  final _searchController = TextEditingController();
  final _repository = ComplianceRepository();
  late Future<List<ComplianceCheckModel>> _checks;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _checks = _repository.getChecks();
  }

  List<_CheckItem> _visibleChecks(List<ComplianceCheckModel> records) {
    final checks = records.map(_CheckItem.fromModel).toList();
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return checks;
    return checks
        .where((check) =>
            check.code.toLowerCase().contains(query) ||
            check.standard.toLowerCase().contains(query) ||
            check.metric.toLowerCase().contains(query) ||
            check.status.toLowerCase().contains(query))
        .toList();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _showDetail(_CheckItem check) {
    return FindingDetailScreen.show(
      context,
      checkCode: check.code,
      referenceStandard: check.standard,
      auditor: check.auditor,
      status: check.status,
      linkedEvidence: check.linkedEvidence,
      statusHistory: check.statusHistory,
    );
  }

  void _showUpload(_CheckItem check) {
    if (context.read<AuthProvider>().roleId != RoleHelper.roleAuditee) {
      context.go('/403');
      return;
    }
    UploadEvidenceScreen.show(
      context,
      checkId: check.id,
      checkCode: check.code,
      referenceStandard: check.standard,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        onProfileTap: () => context.go('/auditee/profile'),
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          setState(() => _checks = _repository.getChecks());
          await _checks;
        },
        child: FutureBuilder<List<ComplianceCheckModel>>(
          future: _checks,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return ListView(children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('Could not load findings.\n${snapshot.error}', textAlign: TextAlign.center),
                ),
              ]);
            }
            if (!snapshot.hasData) {
              return ListView(children: [
                Padding(
                  padding: const EdgeInsets.all(48),
                  child: const Center(child: CircularProgressIndicator()),
                ),
              ]);
            }
            final checks = _visibleChecks(snapshot.data!);
            return ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
          children: [
            TextField(
              controller: _searchController,
              onChanged: (value) => setState(() => _query = value),
              decoration: InputDecoration(
                hintText: 'Search checks...',
                hintStyle: const TextStyle(color: AppColors.ustpMuted),
                prefixIcon: const Icon(AppIcons.search, size: 20),
                filled: true,
                fillColor: AppColors.ustpWhite,
                contentPadding: const EdgeInsets.symmetric(vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(
                    color: AppColors.ustpMuted.withValues(alpha: 0.2),
                  ),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(
                    color: AppColors.ustpMuted.withValues(alpha: 0.2),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 14),
            if (checks.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 48),
                child: Center(
                  child: Text(
                    'No checks match your search.',
                    style: TextStyle(color: AppColors.ustpMuted),
                  ),
                ),
              )
            else
              ...checks.map(
                (check) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _CheckCard(
                    item: check,
                    onViewStandard: () => _showDetail(check),
                    onUpload: () => _showUpload(check),
                  ),
                ),
              ),
              ],
          );
          },
        ),
      ),
    );
  }
}

class _CheckCard extends StatelessWidget {
  const _CheckCard({
    required this.item,
    required this.onViewStandard,
    required this.onUpload,
  });

  final _CheckItem item;
  final VoidCallback onViewStandard;
  final VoidCallback onUpload;

  @override
  Widget build(BuildContext context) {
    final (background, foreground, border) = switch (item.status) {
      'Open' => (
          AppColors.error.withValues(alpha: 0.08),
          AppColors.error,
          AppColors.error.withValues(alpha: 0.4),
        ),
      'Closed' => (
          AppColors.success.withValues(alpha: 0.08),
          AppColors.success,
          AppColors.success.withValues(alpha: 0.35),
        ),
      _ => (
          AppColors.ustpGold.withValues(alpha: 0.15),
          AppColors.ustpDark,
          AppColors.ustpGold,
        ),
    };

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: AppColors.ustpMuted.withValues(alpha: 0.2),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  item.code,
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpNavy,
                  ),
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: background,
                  border: Border.all(color: border),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  item.status,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: foreground,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Text(
            'STANDARD & Metric',
            style: TextStyle(fontSize: 10, color: AppColors.ustpMuted),
          ),
          Text(
            '${item.standard} · ${item.metric}',
            style: const TextStyle(fontSize: 12, color: AppColors.ustpDark),
          ),
          const SizedBox(height: 6),
          const Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('AUDITOR',
                  style: TextStyle(fontSize: 9, color: AppColors.ustpMuted)),
              Text('DUE DATE',
                  style: TextStyle(fontSize: 9, color: AppColors.ustpMuted)),
            ],
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  item.auditor,
                  style:
                      const TextStyle(fontSize: 11, color: AppColors.ustpDark),
                ),
              ),
              Text(
                item.dueDate,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ustpDark,
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Divider(
              height: 1,
              color: AppColors.ustpMuted.withValues(alpha: 0.2),
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              TextButton(
                onPressed: onViewStandard,
                style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  minimumSize: const Size(0, 28),
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  foregroundColor: AppColors.ustpNavy,
                ),
                child: const Text(
                  'View Standard',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    decoration: TextDecoration.underline,
                  ),
                ),
              ),
              if (item.canUpload)
                TextButton(
                  onPressed: onUpload,
                  style: TextButton.styleFrom(
                    padding: EdgeInsets.zero,
                    minimumSize: const Size(0, 28),
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    foregroundColor: AppColors.ustpNavy,
                  ),
                  child: const Text(
                    'Upload Evidence',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CheckItem {
  const _CheckItem({
    required this.id,
    required this.code,
    required this.status,
    required this.standard,
    required this.metric,
    required this.auditor,
    required this.dueDate,
    required this.statusHistory,
    this.canUpload = false,
    this.linkedEvidence = const [],
  });

  factory _CheckItem.fromModel(ComplianceCheckModel check) {
    return _CheckItem(
      id: check.id,
      code: check.checkCode,
      status: switch (check.correctiveStatus) {
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'closed' => 'Closed',
        _ => 'Compliant',
      },
      standard: check.referenceStandard,
      metric: check.remarks ?? check.departmentName ?? '',
      auditor: check.auditorName ?? '',
      dueDate: check.dueDate == null
          ? '—'
          : check.dueDate!.toLocal().toIso8601String().split('T').first,
      statusHistory: check.statusHistory,
      canUpload: check.result == 'non_compliant',
      linkedEvidence: check.linkedArchiveIds,
    );
  }

  final String id;
  final String code;
  final String status;
  final String standard;
  final String metric;
  final String auditor;
  final String dueDate;
  final List<Map<String, dynamic>> statusHistory;
  final bool canUpload;
  final List<String> linkedEvidence;
}
