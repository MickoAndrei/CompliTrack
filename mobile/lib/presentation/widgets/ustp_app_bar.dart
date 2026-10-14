import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_icons.dart';
import '../screens/auditee/notifications_screen.dart';
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
                  icon: const Icon(AppIcons.notifications,
                      color: AppColors.ustpWhite, size: 22),
                  onPressed: () async {
                    onNotificationTap?.call();
                    await NotificationsScreen.showOverlay(context);
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
                context.go('/common/profile');
              },
              child: const CircleAvatar(
                radius: 18,
                backgroundColor: AppColors.ustpLight,
                child: Icon(AppIcons.user, color: AppColors.ustpNavy, size: 20),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
