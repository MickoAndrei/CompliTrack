import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../core/services/socket_service.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  static Future<void> showOverlay(BuildContext context) {
    return showDialog<void>(
      context: context,
      builder: (context) => const Dialog(
        insetPadding: EdgeInsets.symmetric(horizontal: 20, vertical: 28),
        backgroundColor: Colors.transparent,
        child: NotificationsScreen(),
      ),
    );
  }

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _repository = NotificationRepository();
  bool _isLoading = true;
  bool _isMarkingRead = false;
  String? _error;
  List<NotificationModel> _notifications = [];
  StreamSubscription<Map<String, dynamic>>? _subscription;

  @override
  void initState() {
    super.initState();
    _loadNotifications();
    _subscription = SocketService.instance.notifications.listen((_) {
      if (mounted) _loadNotifications();
    });
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  Future<void> _loadNotifications() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final notifications = await _repository.getNotifications();
      if (!mounted) return;
      setState(() {
        _notifications = notifications;
        _isLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = 'Unable to load notifications: $error';
        _isLoading = false;
      });
    }
  }

  Future<void> _markAllAsRead() async {
    setState(() => _isMarkingRead = true);
    final success = await _repository.markAllAsRead();
    if (!mounted) return;
    setState(() {
      _isMarkingRead = false;
      if (success) {
        _notifications = _notifications.map(_asRead).toList();
      } else {
        _error = 'Could not mark notifications as read. Please try again.';
      }
    });
  }

  Future<void> _openNotification(NotificationModel notification) async {
    final router = GoRouter.of(context);
    if (!notification.isRead) {
      final success = await _repository.markAsRead(notification.id);
      if (!mounted) return;
      if (!success) {
        setState(
          () => _error = 'Could not mark this notification as read.',
        );
        return;
      }
      setState(() {
        _notifications = _notifications
            .map(
              (item) => item.id == notification.id ? _asRead(item) : item,
            )
            .toList();
      });
    }
    if (!mounted) return;
    Navigator.of(context).pop();
    if (notification.relatedCheckId != null) {
      router.go('/auditee/status');
    }
  }

  NotificationModel _asRead(NotificationModel notification) {
    return NotificationModel(
      id: notification.id,
      recipientUserId: notification.recipientUserId,
      senderUserId: notification.senderUserId,
      type: notification.type,
      title: notification.title,
      message: notification.message,
      relatedCheckId: notification.relatedCheckId,
      relatedUserCode: notification.relatedUserCode,
      isRead: true,
      createdAt: notification.createdAt,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.78,
      ),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: AppColors.ustpMuted.withValues(alpha: 0.2),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.16),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Compliance Alert Feed',
                  style: TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Close',
                onPressed: () => Navigator.pop(context),
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.ustpLight,
                  minimumSize: const Size(28, 28),
                  padding: EdgeInsets.zero,
                ),
                icon: const Icon(
                  AppIcons.close,
                  size: 14,
                  color: AppColors.ustpMuted,
                ),
              ),
            ],
          ),
          TextButton(
            onPressed: _isMarkingRead ? null : _markAllAsRead,
            style: TextButton.styleFrom(
              foregroundColor: AppColors.ustpNavy,
              padding: EdgeInsets.zero,
              minimumSize: const Size(0, 24),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: _isMarkingRead
                ? const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: AppColors.ustpNavy,
                    ),
                  )
                : const Text(
                    'Mark all as read',
                    style: TextStyle(
                      fontSize: 9,
                      decoration: TextDecoration.underline,
                    ),
                  ),
          ),
          Divider(
            height: 10,
            color: AppColors.ustpMuted.withValues(alpha: 0.2),
          ),
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: CircularProgressIndicator(color: AppColors.ustpNavy),
              ),
            )
          else if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 14),
              child: Column(
                children: [
                  Text(
                    _error!,
                    style: const TextStyle(
                      fontSize: 10,
                      color: AppColors.error,
                    ),
                  ),
                  TextButton(
                    onPressed: _loadNotifications,
                    child: const Text('Retry'),
                  ),
                ],
              ),
            )
          else if (_notifications.isEmpty)
            const Padding(
              padding: EdgeInsets.all(20),
              child: Center(
                child: Text(
                  'No notifications yet.',
                  style: TextStyle(
                    fontSize: 11,
                    color: AppColors.ustpMuted,
                  ),
                ),
              ),
            )
          else
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: _notifications.length,
                itemBuilder: (context, index) {
                  final notification = _notifications[index];
                  return _NotificationItem(
                    notification: notification,
                    onTap: () => _openNotification(notification),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}

class _NotificationItem extends StatelessWidget {
  const _NotificationItem({
    required this.notification,
    required this.onTap,
  });

  final NotificationModel notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isUnread = !notification.isRead;
    final relatedCode =
        notification.relatedCheckId ?? notification.relatedUserCode;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        margin: const EdgeInsets.only(bottom: 7),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        decoration: BoxDecoration(
          color: isUnread
              ? AppColors.ustpGold.withValues(alpha: 0.12)
              : AppColors.ustpWhite,
          border: Border.all(
            color: isUnread
                ? AppColors.ustpGold.withValues(alpha: 0.5)
                : AppColors.ustpMuted.withValues(alpha: 0.2),
          ),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 24,
              height: 24,
              margin: const EdgeInsets.only(top: 1),
              decoration: BoxDecoration(
                color: isUnread
                    ? AppColors.ustpGold.withValues(alpha: 0.2)
                    : AppColors.ustpLight,
                shape: BoxShape.circle,
              ),
              child: Icon(
                AppIcons.notifications,
                size: 13,
                color: isUnread ? AppColors.warning : AppColors.ustpMuted,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    notification.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 9,
                      height: 1.25,
                      fontWeight:
                          isUnread ? FontWeight.w600 : FontWeight.normal,
                      color:
                          isUnread ? AppColors.ustpDark : AppColors.ustpMuted,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${_timeAgo(notification.createdAt)}${relatedCode == null ? '' : ' · $relatedCode'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 8,
                      color: AppColors.ustpMuted,
                    ),
                  ),
                ],
              ),
            ),
            if (isUnread) ...[
              const SizedBox(width: 5),
              Container(
                width: 6,
                height: 6,
                margin: const EdgeInsets.only(top: 8),
                decoration: const BoxDecoration(
                  color: AppColors.ustpGold,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _timeAgo(DateTime createdAt) {
    final difference = DateTime.now().difference(createdAt);
    if (difference.inHours < 24) {
      final hours = difference.inHours <= 0 ? 1 : difference.inHours;
      return '$hours hrs ago';
    }
    if (difference.inDays == 1) return 'yesterday';
    return '${difference.inDays} days ago';
  }
}
