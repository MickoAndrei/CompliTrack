import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';

class StatusBadge extends StatelessWidget {
  final String status;

  const StatusBadge({
    super.key,
    required this.status,
  });

  @override
  Widget build(BuildContext context) {
    final normalized = status.toLowerCase().replaceAll(' ', '_');
    final (bgColor, textColor, label) = switch (normalized) {
      'open' || 'non_compliant' => (const Color(0xFFFEE2E2), AppColors.error, normalized == 'open' ? 'Open' : 'Non-Compliant'),
      'in_progress' => (const Color(0xFFFEF9C3), AppColors.ustpDark, 'In Progress'),
      'closed' || 'compliant' => (const Color(0xFFDCFCE7), AppColors.success, normalized == 'closed' ? 'Closed' : 'Compliant'),
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
