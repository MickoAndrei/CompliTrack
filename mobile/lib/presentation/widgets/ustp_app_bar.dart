import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_icons.dart';
import '../screens/admin/admin_notifications_screen.dart';

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
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                RichText(
                  text: const TextSpan(
                    style: TextStyle(
                      fontFamily: 'Poppins',
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                    children: [
                      TextSpan(
                        text: 'Compli',
                        style: TextStyle(color: AppColors.ustpWhite),
                      ),
                      TextSpan(
                        text: 'Track',
                        style: TextStyle(color: AppColors.ustpGold),
                      ),
                    ],
                  ),
                ),
                const Text(
                  'CLAVERIA CAMPUS / MOBILE',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 9,
                    color: AppColors.ustpMuted,
                    letterSpacing: 0.8,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
            const Spacer(),
            Stack(
              clipBehavior: Clip.none,
              children: [
                IconButton(
                  icon: const Icon(AppIcons.notifications, color: AppColors.ustpWhite, size: 22),
                  onPressed: onNotificationTap ??
                      () => AdminNotificationsScreen.showOverlay(context),
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
              onTap: onProfileTap,
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
