import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';

class ManageOnWebBadge extends StatelessWidget {
  final String label;

  const ManageOnWebBadge({
    super.key,
    this.label = 'Read-only',
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.ustpLight,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: AppColors.ustpMuted,
        ),
      ),
    );
  }
}
