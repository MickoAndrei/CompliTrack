import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../providers/auth_provider.dart';
import '../screens/admin/admin_notifications_screen.dart';
import 'ustp_logo.dart';

class AdminPageHeader extends StatelessWidget {
  const AdminPageHeader({
    super.key,
    required this.title,
    required this.subtitle,
  });

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final userName = context.watch<AuthProvider>().currentUser?.fullName;
    return Container(
      color: AppColors.ustpNavy,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Expanded(
                    child: UstpLogo(
                      fontSize: 16,
                      subtitle: 'CLAVERIA CAMPUS / MOBILE',
                    ),
                  ),
                  _HeaderAction(
                    icon: AppIcons.notifications,
                    onTap: () => AdminNotificationsScreen.showOverlay(context),
                  ),
                  const SizedBox(width: 8),
                  GestureDetector(
                    onTap: () => context.go('/common/profile'),
                    child: CircleAvatar(
                      radius: 17,
                      backgroundColor: AppColors.ustpWhite,
                      child: userName == null || userName.isEmpty
                          ? const Icon(AppIcons.user,
                              color: AppColors.ustpNavy, size: 18)
                          : Text(
                              _initials(userName),
                              style: const TextStyle(
                                color: AppColors.ustpNavy,
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                title,
                style: const TextStyle(
                  color: AppColors.ustpWhite,
                  fontFamily: 'Poppins',
                  fontSize: 19,
                  fontWeight: FontWeight.bold,
                  height: 1.2,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: const TextStyle(
                  color: Color(0xFFB7C7E0),
                  fontFamily: 'Inter',
                  fontSize: 11,
                  height: 1.3,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.length == 1) return parts.first.characters.first.toUpperCase();
    return '${parts.first.characters.first}${parts.last.characters.first}'
        .toUpperCase();
  }
}

class _HeaderAction extends StatelessWidget {
  const _HeaderAction({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.ustpWhite.withValues(alpha: 0.12),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 34,
          height: 34,
          child: Icon(icon, color: AppColors.ustpWhite, size: 18),
        ),
      ),
    );
  }
}
