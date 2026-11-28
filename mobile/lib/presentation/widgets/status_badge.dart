import 'package:flutter/material.dart';
import '../../core/utils/car_status.dart';
import '../../core/constants/app_colors.dart';

class StatusBadge extends StatelessWidget {
  final String status;

  const StatusBadge({
    super.key,
    required this.status,
  });

  @override
  Widget build(BuildContext context) {
    final normalized = canonicalCarStatus(status)?.toLowerCase() ?? status.toLowerCase();
    final (bgColor, textColor, label) = switch (normalized) {
      'n' || 'non_compliant' => (AppColors.outcomeNBackground, AppColors.outcomeN, 'N — Nonconformity'),
      'c' || 'compliant' => (AppColors.outcomeCBackground, AppColors.outcomeC, 'C — Compliant'),
      'o' || 'observation' => (AppColors.outcomeOBackground, AppColors.outcomeO, 'O — Observation'),
      'open' => (AppColors.outcomeNBackground, AppColors.outcomeN, 'Open'),
      'awaiting_verification' => (const Color(0xFFFEF9C3), AppColors.ustpDark, 'Awaiting Verification'),
      'returned_for_revision' => (const Color(0xFFFEE2E2), AppColors.error, 'Returned for Revision'),
      'in_progress' => (const Color(0xFFFEF9C3), AppColors.ustpDark, 'In Progress'),
      'closed' => (AppColors.outcomeCBackground, AppColors.outcomeC, 'Closed'),
      _ => (AppColors.ustpLight, AppColors.ustpMuted, status),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontFamily: 'Inter',
          fontSize: 11,
          fontWeight: FontWeight.bold,
          color: textColor,
        ),
      ),
    );
  }
}
