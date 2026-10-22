import 'dart:async';
import 'dart:ui' show ImageFilter;
import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../core/services/socket_service.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';
import 'broadcast_notification_dialog.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class AdminNotificationsScreen extends StatefulWidget {
  const AdminNotificationsScreen({super.key});

  static Future<void> showOverlay(BuildContext context) {
    return showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Dismiss notifications',
      barrierColor: Colors.transparent,
      transitionDuration: const Duration(milliseconds: 160),
      pageBuilder: (context, animation, secondaryAnimation) => Stack(
        fit: StackFit.expand,
        children: [
          Positioned.fill(
            child: BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 3.5, sigmaY: 3.5),
              child: ColoredBox(color: Colors.black.withValues(alpha: 0.24)),
            ),
          ),
          Center(
            child: Dialog(
              insetPadding:
                  const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
              backgroundColor: Colors.transparent,
              child: const AdminNotificationsOverlay(),
            ),
          ),
        ],
      ),
      transitionBuilder: (context, animation, secondaryAnimation, child) =>
          FadeTransition(opacity: animation, child: child),
    );
  }

  @override
  State<AdminNotificationsScreen> createState() =>
      _AdminNotificationsScreenState();
}

class _AdminNotificationsScreenState extends State<AdminNotificationsScreen> {
  final NotificationRepository _repository = NotificationRepository();
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
      final results = await _repository.getNotifications();
      if (!mounted) return;
      setState(() {
        _notifications = results;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error =
            'Could not load notifications. Check your connection and retry.';
        _isLoading = false;
      });
    }
  }

  Future<void> _markAllAsRead() async {
    if (_isMarkingRead || !_notifications.any((item) => !item.isRead)) return;
    setState(() => _isMarkingRead = true);
    try {
      final success = await _repository.markAllAsRead();
      if (!mounted) return;
      if (!success) throw StateError('Mark all as read failed');
      setState(() {
        _notifications = _notifications.map(_asRead).toList();
        _isMarkingRead = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not mark notifications as read. Please try again.';
        _isMarkingRead = false;
      });
    }
  }

  NotificationModel _asRead(NotificationModel item) => NotificationModel(
        id: item.id,
        recipientUserId: item.recipientUserId,
        senderUserId: item.senderUserId,
        type: item.type,
        title: item.title,
        message: item.message,
        relatedCheckId: item.relatedCheckId,
        relatedUserCode: item.relatedUserCode,
        isRead: true,
        createdAt: item.createdAt,
      );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        showNotificationBadge: false,
        onNotificationTap: () async {},
        onProfileTap: () {},
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.ustpNavy))
          : _error != null && _notifications.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: _loadNotifications,
                        child: const Text('Retry'),
                      ),
                    ]),
                  ),
                )
              : _notifications.isEmpty
                  ? const EmptyState(
                      title: 'No notifications',
                      subtitle: 'You are all caught up!',
                      icon: AppIcons.notifications,
                    )
                  : RefreshIndicator(
                      color: AppColors.ustpNavy,
                      onRefresh: _loadNotifications,
                      child: ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Notifications',
                                style: TextStyle(
                                  fontFamily: 'Poppins',
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.ustpNavy,
                                ),
                              ),
                              TextButton(
                                onPressed:
                                    _isMarkingRead ? null : _markAllAsRead,
                                child: Text(
                                  _isMarkingRead
                                      ? 'Marking...'
                                      : 'Mark all as read',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.info,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          if (_error != null)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Text(
                                _error!,
                                style: const TextStyle(
                                  color: AppColors.error,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ..._notifications.map(
                              (item) => _buildNotificationCard(item, context)),
                        ],
                      ),
                    ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          await BroadcastNotificationDialog.show(context);
          if (mounted) _loadNotifications();
        },
        backgroundColor: AppColors.ustpNavy,
        foregroundColor: AppColors.ustpWhite,
        icon: const Icon(Icons.campaign_outlined),
        label: const Text('Broadcast'),
      ),
    );
  }

  Widget _buildNotificationCard(NotificationModel item, BuildContext context) {
    final timeText = _getTimeText(item.createdAt);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: item.isRead ? AppColors.ustpWhite : const Color(0xFFFEF9C3),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: item.isRead
              ? AppColors.ustpMuted.withValues(alpha: 0.15)
              : AppColors.ustpGold.withValues(alpha: 0.5),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: item.isRead
                  ? AppColors.ustpLight
                  : AppColors.ustpGold.withValues(alpha: 0.2),
              shape: BoxShape.circle,
            ),
            child: Icon(
              AppIcons.notifications,
              size: 20,
              color: item.isRead ? AppColors.ustpMuted : AppColors.ustpDark,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  item.message,
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 12,
                    color: AppColors.ustpDark,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '$timeText${item.relatedUserCode == null ? '' : ' - ${item.relatedUserCode}'}',
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 12,
                    color: AppColors.ustpMuted,
                  ),
                ),
              ],
            ),
          ),
          if (!item.isRead) ...[
            const SizedBox(width: 8),
            Container(
              width: 10,
              height: 10,
              decoration: const BoxDecoration(
                color: AppColors.ustpGold,
                shape: BoxShape.circle,
              ),
            ),
          ],
        ],
      ),
    );
  }

  String _getTimeText(DateTime createdAt) {
    final diff = DateTime.now().difference(createdAt);
    if (diff.inHours < 24) {
      return '${diff.inHours <= 0 ? 1 : diff.inHours} hrs ago';
    } else if (diff.inDays == 1) {
      return 'yesterday';
    } else {
      return '${diff.inDays} days ago';
    }
  }
}

class AdminNotificationsOverlay extends StatefulWidget {
  const AdminNotificationsOverlay({super.key});

  @override
  State<AdminNotificationsOverlay> createState() =>
      _AdminNotificationsOverlayState();
}

class _AdminNotificationsOverlayState extends State<AdminNotificationsOverlay> {
  final NotificationRepository _repository = NotificationRepository();
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
      final results = await _repository.getNotifications();
      if (!mounted) return;
      setState(() {
        _notifications = results;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error =
            'Could not load notifications. Check your connection and retry.';
        _isLoading = false;
      });
    }
  }

  Future<void> _markAllAsRead() async {
    if (_isMarkingRead || !_notifications.any((item) => !item.isRead)) return;
    setState(() => _isMarkingRead = true);
    try {
      final success = await _repository.markAllAsRead();
      if (!mounted) return;
      if (!success) throw StateError('Mark all as read failed');
      setState(() {
        _notifications = _notifications.map(_asRead).toList();
        _isMarkingRead = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not mark notifications as read. Please try again.';
        _isMarkingRead = false;
      });
    }
  }

  Future<void> _markOneAsRead(NotificationModel item) async {
    if (item.isRead) return;
    try {
      final success = await _repository.markAsRead(item.id);
      if (!mounted) return;
      if (!success) throw StateError('Mark as read failed');
      setState(() {
        _notifications = _notifications
            .map((notification) => notification.id == item.id
                ? _asRead(notification)
                : notification)
            .toList();
        _error = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Could not update this notification. Try again.');
    }
  }

  NotificationModel _asRead(NotificationModel item) => NotificationModel(
        id: item.id,
        recipientUserId: item.recipientUserId,
        senderUserId: item.senderUserId,
        type: item.type,
        title: item.title,
        message: item.message,
        relatedCheckId: item.relatedCheckId,
        relatedUserCode: item.relatedUserCode,
        isRead: true,
        createdAt: item.createdAt,
      );

  @override
  Widget build(BuildContext context) {
    final maxHeight = MediaQuery.sizeOf(context).height * 0.72;
    return Container(
      constraints: BoxConstraints(maxHeight: maxHeight),
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.18),
            blurRadius: 22,
            offset: const Offset(0, 8),
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
                  'Notifications',
                  style: TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpNavy,
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Close notifications',
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.close_rounded, size: 18),
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.ustpLight,
                  foregroundColor: AppColors.ustpMuted,
                  minimumSize: const Size(30, 30),
                  padding: EdgeInsets.zero,
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
                    width: 13,
                    height: 13,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text(
                    'Mark all as read',
                    style: TextStyle(
                        fontSize: 9, decoration: TextDecoration.underline),
                  ),
          ),
          Divider(
              height: 10, color: AppColors.ustpMuted.withValues(alpha: 0.2)),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(_error!,
                  style: const TextStyle(fontSize: 9, color: AppColors.error)),
            ),
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                  child: CircularProgressIndicator(color: AppColors.ustpNavy)),
            )
          else if (_notifications.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 28),
              alignment: Alignment.center,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    _error == null
                        ? 'All caught up.'
                        : 'Notifications unavailable.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                        fontSize: 11, color: AppColors.ustpMuted),
                  ),
                  if (_error != null)
                    TextButton(
                        onPressed: _loadNotifications,
                        child: const Text('Retry')),
                ],
              ),
            )
          else
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: _notifications.length,
                separatorBuilder: (_, __) => const SizedBox(height: 6),
                itemBuilder: (context, index) {
                  final item = _notifications[index];
                  final unread = !item.isRead;
                  return Material(
                    color:
                        unread ? const Color(0xFFFFF8E5) : AppColors.ustpWhite,
                    borderRadius: BorderRadius.circular(8),
                    child: InkWell(
                      onTap: unread ? () => _markOneAsRead(item) : null,
                      borderRadius: BorderRadius.circular(8),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 8),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFE4EAF1)),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 28,
                              height: 28,
                              decoration: BoxDecoration(
                                color: unread
                                    ? AppColors.ustpGold.withValues(alpha: 0.18)
                                    : AppColors.ustpLight,
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                Icons.notifications_active_outlined,
                                size: 15,
                                color: unread
                                    ? AppColors.ustpGold
                                    : AppColors.ustpMuted,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(item.title,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                          fontFamily: 'Inter',
                                          fontSize: 9,
                                          fontWeight: FontWeight.w600,
                                          color: AppColors.ustpDark)),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${_timeAgo(item.createdAt)}${item.relatedUserCode == null ? '' : ' - ${item.relatedUserCode}'}',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        fontFamily: 'Inter',
                                        fontSize: 7,
                                        color: AppColors.ustpMuted),
                                  ),
                                ],
                              ),
                            ),
                            if (unread) ...[
                              const SizedBox(width: 6),
                              const Icon(Icons.circle,
                                  size: 6, color: AppColors.ustpGold),
                            ],
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  String _timeAgo(DateTime date) {
    final difference = DateTime.now().difference(date.toLocal());
    if (difference.isNegative || difference.inMinutes < 1) return 'Just now';
    if (difference.inHours < 1) return '${difference.inMinutes} min ago';
    if (difference.inHours < 24) return '${difference.inHours} hr ago';
    if (difference.inDays == 1) return 'Yesterday';
    return '${difference.inDays} days ago';
  }
}
