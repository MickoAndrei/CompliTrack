import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_colors.dart';
import '../../core/services/socket_service.dart';
import '../../data/repositories/notification_repository.dart';
import '../providers/auth_provider.dart';
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
            UstpNotificationButton(
              showBadge: widget.showNotificationBadge,
              onPressed: () async {
                if (widget.onNotificationTap != null) {
                  await widget.onNotificationTap!.call();
                  return;
                }
                final route = switch (auth.roleId) {
                  0 => '/super-admin/notifications',
                  1 => '/admin/notifications',
                  2 => '/auditor/profile/notifications',
                  3 => '/auditee/notifications',
                  _ => '/login',
                };
                await context.push(route);
              },
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: () {
                widget.onProfileTap?.call();
                if (widget.onProfileTap == null) {
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
  StreamSubscription<Map<String, dynamic>>? _subscription;
  bool _hasUnread = false;

  @override
  void initState() {
    super.initState();
    _refreshUnread();
    _subscription =
        SocketService.instance.notifications.listen(_onNotification);
  }

  @override
  void didUpdateWidget(covariant UstpNotificationButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.showBadge != widget.showBadge) _refreshUnread();
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  void _onNotification(Map<String, dynamic> payload) {
    _refreshUnread();
    if (!mounted || !TickerMode.valuesOf(context).enabled) return;
    final lines = [
      payload['title']?.toString().trim(),
      payload['message']?.toString().trim(),
    ].whereType<String>().where((text) => text.isNotEmpty).toList();
    ScaffoldMessenger.maybeOf(context)?.showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        content: Text(
          lines.isEmpty ? 'You have a new notification.' : lines.join('\n'),
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    );
  }

  Future<void> _refreshUnread() async {
    try {
      final notifications = await _repository.getNotifications();
      if (!mounted) return;
      setState(() => _hasUnread = notifications.any((item) => !item.isRead));
    } catch (_) {
      if (mounted) setState(() => _hasUnread = false);
    }
  }

  @override
  Widget build(BuildContext context) => Material(
        color: AppColors.ustpWhite.withValues(alpha: 0.12),
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: () async {
            await widget.onPressed();
            await _refreshUnread();
          },
          child: SizedBox(
            width: 34,
            height: 34,
            child: Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                const Icon(Icons.notifications_none_rounded,
                    color: AppColors.ustpWhite, size: 19),
                if (widget.showBadge && _hasUnread)
                  Positioned(
                    right: 5,
                    top: 5,
                    child: Container(
                      width: 7,
                      height: 7,
                      decoration: const BoxDecoration(
                        color: AppColors.error,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      );
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
