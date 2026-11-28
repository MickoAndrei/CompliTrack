import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../data/repositories/notification_repository.dart';
import '../providers/auth_provider.dart';
import 'v2/notification_preview.dart';
import 'current_user_avatar.dart';
import 'ustp_logo.dart';

class UstpAppBar extends StatefulWidget implements PreferredSizeWidget {
  final String title;
  final bool showNotificationBadge;
  final FutureOr<void> Function()? onNotificationTap;
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
  State<UstpAppBar> createState() => _UstpAppBarState();
}

class _UstpAppBarState extends State<UstpAppBar> {
  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return AnnotatedRegion<SystemUiOverlayStyle>(value: SystemUiOverlayStyle.light, child: Container(
      color: AppColors.ustpNavy,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: SafeArea(
        child: Row(
          children: [
            Expanded(child: UstpLogo(
              fontSize: 16,
              subtitle: widget.title != 'CompliTrack'
                  ? widget.title
                  : 'CLAVERIA CAMPUS / MOBILE',
            )),
            if (widget.showNotificationBadge) UstpNotificationButton(
              onPressed: () => showNotificationPreview(context),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: widget.onProfileTap ??
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
      ),
    ));
  }
}

class UstpNotificationButton extends StatefulWidget {
  const UstpNotificationButton({
    super.key,
    required this.onPressed,
    this.showBadge = true,
  });

  final FutureOr<void> Function() onPressed;
  final bool showBadge;

  @override
  State<UstpNotificationButton> createState() => _UstpNotificationButtonState();
}

class _UstpNotificationButtonState extends State<UstpNotificationButton> {
  final NotificationRepository _repository = NotificationRepository();
  Timer? _refreshTimer;
  int _unreadCount = 0;

  @override
  void initState() {
    super.initState();
    _refreshUnread();
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _refreshUnread();
    });
  }

  @override
  void didUpdateWidget(covariant UstpNotificationButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.showBadge != widget.showBadge) _refreshUnread();
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _refreshUnread() async {
    try {
      final notifications = await _repository.preview(perPage: 1);
      if (!mounted) return;
      setState(() => _unreadCount = notifications.unreadCount);
    } catch (_) {
      if (mounted) setState(() => _unreadCount = 0);
    }
  }

  @override
  Widget build(BuildContext context) => Material(
        color: AppColors.ustpWhite.withValues(alpha: 0.12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        child: InkWell(
          borderRadius: BorderRadius.circular(8),
          onTap: () async {
            await widget.onPressed();
            await _refreshUnread();
          },
          child: SizedBox(
            width: 44,
            height: 44,
            child: Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                const Icon(Icons.notifications_none_rounded,
                    color: AppColors.ustpWhite, size: 19),
                if (widget.showBadge && _unreadCount > 0)
                  Positioned(
                    right: 5,
                    top: 5,
                    child: Container(
                      constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                      padding: const EdgeInsets.symmetric(horizontal: 3),
                      alignment: Alignment.center,
                      decoration: const BoxDecoration(
                        color: AppColors.error,
                        borderRadius: BorderRadius.all(Radius.circular(10)),
                      ),
                      child: Text(_unreadCount > 99 ? '99+' : '$_unreadCount', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
                    ),
                  ),
              ],
            ),
          ),
        ),
      );
}
