import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../providers/auth_provider.dart';
import '../screens/admin/admin_notifications_screen.dart';
import 'ustp_app_bar.dart';
import 'ustp_logo.dart';
import 'current_user_avatar.dart';

class AdminPageHeader extends StatelessWidget {
  const AdminPageHeader({
    super.key,
    required this.title,
    required this.subtitle,
    this.onProfileTap,
  });

  final String title;
  final String subtitle;
  final VoidCallback? onProfileTap;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
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
                  UstpNotificationButton(
                    onPressed: () =>
                        AdminNotificationsScreen.showOverlay(context),
                  ),
                  const SizedBox(width: 8),
                  GestureDetector(
                    onTap: onProfileTap ??
                        () {
                          final route = switch (auth.roleId) {
                            2 => '/auditor/profile',
                            3 => '/auditee/profile',
                            _ => '/common/profile',
                          };
                          context.go(route);
                        },
                    child: CurrentUserAvatar(
                      key: ValueKey(auth.currentUser?.id),
                      radius: 16,
                      avatarVersion: auth.avatarVersion,
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
}
