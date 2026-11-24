import 'dart:async';
import '../../../core/utils/car_status.dart';
import '../../../core/utils/date_formatter.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/ustp_app_bar.dart';

const _statuses = {
  'open': 'Open',
  'in_progress': 'In Progress',
  'awaiting_verification': 'Awaiting Verification',
  'returned_for_revision': 'Returned for Revision',
  'closed': 'Closed',
};
const _border = Color(0xFFDFE6EF);
const _muted = Color(0xFF64748B);

class CorrectiveBoardScreen extends StatefulWidget {
  const CorrectiveBoardScreen({super.key, this.repository});
  final ComplianceRepository? repository;

  @override
  State<CorrectiveBoardScreen> createState() => _CorrectiveBoardScreenState();
}

class _CorrectiveBoardScreenState extends State<CorrectiveBoardScreen> {
  late final _repository = widget.repository ?? ComplianceRepository();
  late Future<Map<String, dynamic>> _records;
  final _search = TextEditingController();
  Timer? _debounce;
  String _department = '';
  String _status = '';
  int _page = 1;
  bool _openingHistory = false;

  @override
  void initState() {
    super.initState();
    _records = _load();
  }

  Future<Map<String, dynamic>> _load() => _repository.getCorrectiveActions(
        page: _page,
        search: _search.text.trim(),
        departmentId: _department,
        status: _status,
      );

  Future<void> _refresh({int? page}) async {
    _debounce?.cancel();
    if (page != null) _page = page;
    final request = _load();
    setState(() {
      _records = request;
    });
    // FutureBuilder renders request failures and exposes Retry.
    try {
      await request;
    } catch (_) {}
  }

  void _clearFilters() {
    _search.clear();
    _department = '';
    _status = '';
    _refresh(page: 1);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _history(String id) async {
    if (_openingHistory) return;
    setState(() => _openingHistory = true);
    try {
      final check = await _repository.getCheck(id);
      if (!mounted) return;
      await _showStatusHistory(context, check);
    } catch (_) {
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content:
                  Text('Could not load status history. Please try again.')),
        );
    } finally {
      if (mounted) setState(() => _openingHistory = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: const Color(0xFFF5F7FB),
        appBar: UstpAppBar(
            onNotificationTap: () =>
                context.go('/auditor/profile/notifications')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _records,
          builder: (context, snapshot) {
            final data = snapshot.data;
            final loading = snapshot.connectionState != ConnectionState.done;
            final summary = data?['summary'] as Map?;
            final meta = data?['meta'] as Map?;
            final departments =
                (data?['departments'] as List? ?? const []).cast<Map>();
            final rows = (data?['data'] as List? ?? const []).cast<Map>();
            return RefreshIndicator(
              onRefresh: () => _refresh(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
                children: [
                  const Text('Corrective Actions',
                      style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ustpNavy)),
                  const SizedBox(height: 6),
                  const Text(
                      'Monitor findings, submitted evidence, and verification progress.',
                      style:
                          TextStyle(fontSize: 13, color: _muted, height: 1.5)),
                  const SizedBox(height: 20),
                  LayoutBuilder(builder: (context, constraints) {
                    final columns = constraints.maxWidth >= 680 ? 4 : 2;
                    final width =
                        (constraints.maxWidth - (columns - 1) * 10) / columns;
                    return Wrap(spacing: 10, runSpacing: 10, children: [
                      for (final metric in const [
                        ('total', 'Total Actions', Icons.assignment_outlined),
                        ('inProgress', 'In Progress', Icons.schedule_outlined),
                        (
                          'awaitingVerification',
                          'Awaiting Verification',
                          Icons.fact_check_outlined
                        ),
                        ('closed', 'Closed', Icons.check_circle_outline),
                      ])
                        SizedBox(
                            width: width,
                            child: _Metric(
                                label: metric.$2,
                                value: loading || snapshot.hasError
                                    ? '—'
                                    : '${summary?[metric.$1] ?? 0}',
                                icon: metric.$3)),
                    ]);
                  }),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: _panelDecoration(),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Corrective Action Records',
                              style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.ustpNavy)),
                          const SizedBox(height: 4),
                          Text(
                              loading
                                  ? 'Loading records…'
                                  : snapshot.hasError
                                      ? 'Records unavailable'
                                      : '${meta?['total'] ?? 0} records',
                              style:
                                  const TextStyle(fontSize: 12, color: _muted)),
                          const SizedBox(height: 16),
                          TextField(
                            controller: _search,
                            maxLength: 255,
                            onChanged: (_) {
                              _debounce?.cancel();
                              _debounce = Timer(
                                  const Duration(milliseconds: 350),
                                  () => _refresh(page: 1));
                            },
                            decoration: _fieldDecoration(
                                    'Search audit item, auditee, reference…')
                                .copyWith(
                                    prefixIcon:
                                        const Icon(Icons.search, size: 20),
                                    counterText: ''),
                          ),
                          const SizedBox(height: 10),
                          DropdownButtonFormField<String>(
                            key: ValueKey('department:$_department'),
                            initialValue: departments
                                    .any((unit) => unit['id'] == _department)
                                ? _department
                                : '',
                            isExpanded: true,
                            decoration: _fieldDecoration('Department'),
                            items: [
                              const DropdownMenuItem(
                                  value: '', child: Text('All departments')),
                              for (final unit in departments)
                                DropdownMenuItem(
                                    value: unit['id'] as String,
                                    child: Text('${unit['name']}',
                                        overflow: TextOverflow.ellipsis)),
                            ],
                            onChanged: (value) {
                              _department = value ?? '';
                              _refresh(page: 1);
                            },
                          ),
                          const SizedBox(height: 10),
                          DropdownButtonFormField<String>(
                            key: ValueKey('status:$_status'),
                            initialValue: _status,
                            isExpanded: true,
                            decoration: _fieldDecoration('Status'),
                            items: [
                              const DropdownMenuItem(
                                  value: '', child: Text('All statuses')),
                              for (final entry in _statuses.entries)
                                DropdownMenuItem(
                                    value: entry.key, child: Text(entry.value)),
                            ],
                            onChanged: (value) {
                              _status = value ?? '';
                              _refresh(page: 1);
                            },
                          ),
                          const SizedBox(height: 12),
                          const Text(
                              'Summary totals follow your search and department filter across all pages.',
                              style: TextStyle(
                                  fontSize: 11, color: _muted, height: 1.5)),
                          if (_search.text.isNotEmpty ||
                              _department.isNotEmpty ||
                              _status.isNotEmpty)
                            TextButton(
                                onPressed: _clearFilters,
                                child: const Text('Clear filters')),
                          const SizedBox(height: 16),
                          if (loading)
                            const Padding(
                                padding: EdgeInsets.all(32),
                                child:
                                    Center(child: CircularProgressIndicator()))
                          else if (snapshot.hasError)
                            _BoardError(onRetry: () => _refresh())
                          else if (rows.isEmpty)
                            Center(
                                child: Padding(
                                    padding: const EdgeInsets.symmetric(
                                        vertical: 24),
                                    child: Column(children: [
                                      const Icon(Icons.fact_check_outlined,
                                          size: 36, color: Color(0xFFA07800)),
                                      const SizedBox(height: 12),
                                      const Text(
                                          'No corrective actions match these filters',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                              fontWeight: FontWeight.w600,
                                              color: AppColors.ustpNavy)),
                                      const SizedBox(height: 6),
                                      const Text(
                                          'Try another search, department, or status.',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                              color: _muted, fontSize: 12)),
                                      TextButton(
                                          onPressed: _clearFilters,
                                          child: const Text('Clear filters')),
                                    ])))
                          else ...[
                            for (final row in rows)
                              _ActionCard(
                                row: row,
                                onHistory: _openingHistory
                                    ? null
                                    : () => _history(row['_id'] as String),
                                onOpen: () async {
                                  await context
                                      .push('/auditor/checks/${row['_id']}');
                                  if (mounted) _refresh();
                                },
                              ),
                            const SizedBox(height: 6),
                            Center(
                                child: Text(
                                    'Page ${meta?['currentPage'] ?? 1} of ${meta?['lastPage'] ?? 1}',
                                    style: const TextStyle(
                                        color: _muted, fontSize: 12))),
                            Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  TextButton(
                                      onPressed:
                                          (meta?['currentPage'] as int? ?? 1) >
                                                  1
                                              ? () => _refresh(
                                                  page: (meta!['currentPage']
                                                          as int) -
                                                      1)
                                              : null,
                                      child: const Text('Previous')),
                                  TextButton(
                                      onPressed:
                                          (meta?['currentPage'] as int? ?? 1) <
                                                  (meta?['lastPage'] as int? ??
                                                      1)
                                              ? () => _refresh(
                                                  page: (meta!['currentPage']
                                                          as int) +
                                                      1)
                                              : null,
                                      child: const Text('Next')),
                                ]),
                          ],
                        ]),
                  ),
                ],
              ),
            );
          },
        ),
      );
}

BoxDecoration _panelDecoration() => BoxDecoration(
    color: Colors.white,
    border: Border.all(color: _border),
    borderRadius: BorderRadius.circular(14));
InputDecoration _fieldDecoration(String label) => InputDecoration(
      hintText: label,
      labelText: label == 'Department' || label == 'Status' ? label : null,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: _border)),
      enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: _border)),
    );

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value, required this.icon});
  final String label;
  final String value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(15),
        decoration: _panelDecoration(),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, color: const Color(0xFFA07800), size: 20),
          const SizedBox(height: 10),
          Text(label, style: const TextStyle(fontSize: 12, color: _muted)),
          const SizedBox(height: 8),
          Text(value,
              style: const TextStyle(
                  fontSize: 27,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ustpNavy)),
        ]),
      );
}

class _ActionCard extends StatelessWidget {
  const _ActionCard(
      {required this.row, required this.onOpen, required this.onHistory});
  final Map row;
  final VoidCallback onOpen;
  final VoidCallback? onHistory;
  @override
  Widget build(BuildContext context) {
    final status = row['displayStatus']?.toString() ?? '';
    final (foreground, background) = switch (status) {
      'closed' => (const Color(0xFF166534), const Color(0xFFDCFCE7)),
      'in_progress' => (const Color(0xFF765500), const Color(0xFFFFF2BE)),
      'awaiting_verification' => (
          const Color(0xFF17458A),
          const Color(0xFFE7EFFF)
        ),
      'open' || 'returned_for_revision' => (
          const Color(0xFF9A450D),
          const Color(0xFFFFF0DF)
        ),
      _ => (_muted, const Color(0xFFF1F5F9)),
    };
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(15),
      decoration: _panelDecoration(),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('AUDIT ITEM',
            style: TextStyle(fontSize: 10, letterSpacing: 1, color: _muted)),
        const SizedBox(height: 5),
        Text('${row['auditItem'] ?? 'Not recorded'}',
            style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                height: 1.5,
                color: AppColors.ustpNavy)),
        const SizedBox(height: 5),
        Text('Reference: ${row['checkCode']}',
            style: const TextStyle(fontSize: 11, color: _muted)),
        const SizedBox(height: 10),
        Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
                color: background, borderRadius: BorderRadius.circular(20)),
            child: Text('${row['statusLabel'] ?? _statuses[status] ?? status}',
                style: TextStyle(
                    color: foreground,
                    fontWeight: FontWeight.w600,
                    fontSize: 11))),
        const SizedBox(height: 14),
        _Assignment(
            icon: Icons.apartment_outlined,
            text: '${row['departmentName'] ?? 'Not recorded'}'),
        const SizedBox(height: 7),
        _Assignment(
            icon: Icons.person_outline,
            text: '${row['auditeeName'] ?? 'Not recorded'}'),
        const SizedBox(height: 14),
        Text('${row['finding'] ?? 'Not recorded'}',
            style: const TextStyle(
                fontSize: 13, height: 1.6, color: AppColors.ustpDark)),
        const SizedBox(height: 16),
        Text(
            row['dueDate'] == null
                ? 'Due date not recorded'
                : 'Due ${_formatDate(row['dueDate'])}',
            style: const TextStyle(color: _muted, fontSize: 11)),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 4, children: [
          OutlinedButton.icon(
              onPressed: onOpen,
              icon: const Icon(Icons.arrow_forward, size: 16),
              label: const Text('View Details')),
          TextButton(onPressed: onHistory, child: const Text('View History')),
        ]),
      ]),
    );
  }
}

class _Assignment extends StatelessWidget {
  const _Assignment({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) =>
      Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 16, color: _muted),
        const SizedBox(width: 7),
        Expanded(
            child: Text(text,
                style:
                    const TextStyle(fontSize: 12, color: _muted, height: 1.4))),
      ]);
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
        constraints: BoxConstraints(
            maxWidth: 390, maxHeight: MediaQuery.sizeOf(context).height * .8),
        child: SingleChildScrollView(
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
              Text(
                  'Current state: ${_statuses[check.correctiveStatus] ?? check.correctiveStatus ?? 'Not recorded'}'),
              if (check.nextAction != null) Text(check.nextAction!),
              const Text('Recorded history'),
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
        )),
      ),
    ),
  );
}

class _HistoryItem extends StatelessWidget {
  const _HistoryItem({required this.entry});

  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final status = canonicalCarStatus(entry['status']?.toString()) ?? 'unknown';
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
  return parsed == null ? 'Date unavailable' : formatPortalDate(timestamp);
}
