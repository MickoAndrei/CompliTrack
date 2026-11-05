import 'package:flutter/material.dart';
import '../../core/utils/role_helper.dart';

class RoleBadge extends StatelessWidget {
  final int roleId;

  const RoleBadge({
    super.key,
    required this.roleId,
  });

  @override
  Widget build(BuildContext context) {
    final (bgColor, textColor) = switch (roleId) {
      0 => (
          const Color(0xFFFEF3C7),
          const Color(0xFF1E293B)
        ), // Super Admin soft gold
      1 => (
          const Color(0xFFE0F2FE),
          const Color(0xFF0369A1)
        ), // Admin soft blue
      2 => (
          const Color(0xFFF3E8FF),
          const Color(0xFF7E22CE)
        ), // Auditor soft purple
      3 => (
          const Color(0xFFF1F5F9),
          const Color(0xFF334155)
        ), // Auditee soft gray
      _ => (const Color(0xFFF1F5F9), const Color(0xFF334155)),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        RoleHelper.getRoleName(roleId),
        style: TextStyle(
          fontFamily: 'Inter',
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
      ),
    );
  }
}
