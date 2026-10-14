import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';

class FindingDetailScreen extends StatelessWidget {
  const FindingDetailScreen({
    super.key,
    required this.checkCode,
    required this.referenceStandard,
    required this.auditor,
    required this.status,
    required this.statusHistory,
    this.linkedEvidence = const [],
  });

  final String checkCode;
  final String referenceStandard;
  final String auditor;
  final String status;
  final List<Map<String, dynamic>> statusHistory;
  final List<String> linkedEvidence;

  static Future<void> show(
    BuildContext context, {
    required String checkCode,
    required String referenceStandard,
    required String auditor,
    required String status,
    required List<Map<String, dynamic>> statusHistory,
    List<String> linkedEvidence = const [],
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => FractionallySizedBox(
        heightFactor: 0.86,
        child: FindingDetailScreen(
          checkCode: checkCode,
          referenceStandard: referenceStandard,
          auditor: auditor,
          status: status,
          statusHistory: statusHistory,
          linkedEvidence: linkedEvidence,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
      ),
      child: SafeArea(
        top: false,
        child: Column(
          children: [
            const SizedBox(height: 12),
            Container(
              width: 34,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.ustpMuted.withValues(alpha: 0.4),
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          checkCode,
                          style: const TextStyle(
                            fontFamily: 'Poppins',
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: AppColors.ustpDark,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        tooltip: 'Close detail',
                        style: IconButton.styleFrom(
                          backgroundColor: AppColors.ustpLight,
                          foregroundColor: AppColors.ustpMuted,
                          minimumSize: const Size(32, 32),
                        ),
                        icon: const Icon(AppIcons.close, size: 16),
                      ),
                    ],
                  ),
                  Text(
                    '$referenceStandard · Auditor: $auditor',
                    style: const TextStyle(
                      fontSize: 10,
                      color: AppColors.ustpMuted,
                    ),
                  ),
                  const SizedBox(height: 18),
                  if (statusHistory.isEmpty)
                    Text(
                      'Current status: $status',
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.ustpMuted,
                      ),
                    )
                  else
                    for (var index = 0;
                        index < statusHistory.length;
                        index++)
                      _TimelineStep(
                        title: _statusLabel(statusHistory[index]['status']),
                        date: _formatTimestamp(
                          statusHistory[index]['timestamp'],
                        ),
                        color: index == statusHistory.length - 1
                            ? AppColors.ustpGold
                            : AppColors.ustpMuted,
                        isLast: index == statusHistory.length - 1,
                      ),
                  const SizedBox(height: 12),
                  const Text(
                    'Linked Evidence',
                    style: TextStyle(
                      fontFamily: 'Poppins',
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: AppColors.ustpDark,
                    ),
                  ),
                  const SizedBox(height: 6),
                  if (linkedEvidence.isEmpty)
                    const Text(
                      'No evidence linked.',
                      style: TextStyle(
                        fontSize: 10,
                        color: AppColors.ustpMuted,
                      ),
                    )
                  else
                    for (final file in linkedEvidence)
                      Container(
                        margin: const EdgeInsets.only(bottom: 5),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.ustpLight,
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: AppColors.ustpMuted.withValues(alpha: 0.2),
                          ),
                        ),
                        child: Row(
                          children: [
                            const Icon(
                              AppIcons.document,
                              size: 14,
                              color: AppColors.ustpMuted,
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                'Archive $file',
                                style: const TextStyle(
                                  fontSize: 10,
                                  color: AppColors.ustpDark,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    height: 38,
                    child: ElevatedButton(
                      onPressed: () => Navigator.pop(context),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.ustpGold,
                        foregroundColor: AppColors.ustpDark,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: const Text(
                        'Close Detail',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static String _statusLabel(Object? value) => switch (value) {
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'closed' => 'Closed',
        final String status => status,
        _ => 'Status updated',
      };

  static String _formatTimestamp(Object? value) {
    if (value == null) return 'Time unavailable';
    final date = DateTime.tryParse(value.toString());
    if (date == null) return value.toString();
    return date.toLocal().toString().split('.').first;
  }
}

class _TimelineStep extends StatelessWidget {
  const _TimelineStep({
    required this.title,
    required this.date,
    required this.color,
    required this.isLast,
  });

  final String title;
  final String date;
  final Color color;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 18,
            child: Column(
              children: [
                Container(
                  width: 10,
                  height: 10,
                  margin: const EdgeInsets.only(top: 2),
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                  ),
                ),
                if (!isLast)
                  Expanded(
                    child: Container(
                      width: 1,
                      color: AppColors.ustpMuted.withValues(alpha: 0.2),
                      margin: const EdgeInsets.symmetric(vertical: 4),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  date,
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