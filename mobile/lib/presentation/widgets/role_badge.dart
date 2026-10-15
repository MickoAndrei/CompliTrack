import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_icons.dart';
import '../../core/utils/role_helper.dart';

class RoleBadge extends StatelessWidget {
  final int roleId;

  const RoleBadge({
    super.key,
    required this.roleId,
  });

  @override
  Widget build(BuildContext context) {
    final (bgColor, textColor, icon) = switch (roleId) {
      0 => (AppColors.ustpGold, AppColors.ustpDark, AppIcons.superAdmin),
      1 => (AppColors.info, AppColors.ustpWhite, null),
      2 => (AppColors.ustpPurple, AppColors.ustpWhite, null),
      3 => (AppColors.ustpMuted, AppColors.ustpWhite, null),
      _ => (AppColors.ustpMuted, AppColors.ustpWhite, null),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: bgColor.withValues(alpha: 0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: textColor),
            const SizedBox(width: 4),
          ],
          Text(
            RoleHelper.getRoleName(roleId),
            style: TextStyle(
              fontFamily: 'Inter',
              fontSize: 11,
              fontWeight: FontWeight.bold,
              color: textColor == AppColors.ustpWhite ? bgColor : textColor,
            ),
          ),
        ],
      ),
    );
  }
}
