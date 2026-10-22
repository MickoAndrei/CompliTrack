import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../providers/auth_provider.dart';
import 'ustp_logo.dart';

class UstpAppBar extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final bool showNotificationBadge;
  final VoidCallback? onNotificationTap;
  final VoidCallback? onProfileTap;

  const UstpAppBar({
    super.key,
    this.title = 'CompliTrack',
    this.showNotificationBadge = true,
    this.onNotificationTap,
    this.onProfileTap,
  });

  @override
  Size get preferredSize => const Size.fromHeight(64);

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Container(
      color: AppColors.ustpNavy,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: SafeArea(
        child: Row(
          children: [
            const UstpLogo(
              fontSize: 16,
              subtitle: 'CLAVERIA CAMPUS / MOBILE',
            ),
            const Spacer(),
            Stack(
              clipBehavior: Clip.none,
              children: [
                IconButton(
                  icon: const Icon(Icons.notifications_none_rounded,
                      color: AppColors.ustpWhite, size: 22),
                  onPressed: () {
                    if (onNotificationTap != null) {
                      onNotificationTap!.call();
                      return;
                    }
                    final route = switch (auth.roleId) {
                      0 => '/super-admin/notifications',
                      1 => '/admin/notifications',
                      2 => '/auditor/profile/notifications',
                      3 => '/auditee/notifications',
                      _ => '/login',
                    };
                    context.go(route);
                  },
                ),
                if (showNotificationBadge)
                  Positioned(
                    right: 8,
                    top: 8,
                    child: Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: AppColors.error,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 4),
            GestureDetector(
              onTap: () {
                onProfileTap?.call();
                if (onProfileTap == null) {
                  context.go(
                    auth.roleId == 2 ? '/auditor/profile' : '/common/profile',
                  );
                }
              },
              child: const CircleAvatar(
                radius: 18,
                backgroundColor: AppColors.ustpGold,
                child: CircleAvatar(
                  radius: 16,
                  backgroundColor: AppColors.ustpLight,
                  child: _ProfileInitials(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

}

class _ProfileInitials extends StatelessWidget {
  const _ProfileInitials();

  @override
  Widget build(BuildContext context) {
    final name = context.watch<AuthProvider>().currentUser?.fullName ?? '';
    final parts = name.trim().split(RegExp(r'\s+'));
    final initials = parts.isEmpty || parts.first.isEmpty
        ? '?'
        : parts.length == 1
            ? parts.first.substring(0, 1).toUpperCase()
            : '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    return Text(
      initials,
      style: const TextStyle(
        color: AppColors.ustpNavy,
        fontSize: 11,
        fontWeight: FontWeight.bold,
      ),
    );
  }
}
