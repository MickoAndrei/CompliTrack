import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class AdminNotificationsScreen extends StatefulWidget {
  const AdminNotificationsScreen({super.key});

  static Future<void> showOverlay(BuildContext context) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => const AdminNotificationsOverlay(),
    );
  }

  @override
  State<AdminNotificationsScreen> createState() => _AdminNotificationsScreenState();
}

class _AdminNotificationsScreenState extends State<AdminNotificationsScreen> {
  final NotificationRepository _repository = NotificationRepository();
  bool _isLoading = true;
  List<NotificationModel> _notifications = [];

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    setState(() => _isLoading = true);
    final results = await _repository.getNotifications();
    if (mounted) {
      setState(() {
        _notifications = results;
        _isLoading = false;
      });
    }
  }

  Future<void> _markAllAsRead() async {
    await _repository.markAllAsRead();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('All notifications marked as read')),
      );
      _loadNotifications();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        showNotificationBadge: false,
        onNotificationTap: () {},
        onProfileTap: () {},
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.ustpNavy))
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
                            onPressed: _markAllAsRead,
                            child: const Text(
                              'Mark all as read',
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
                      ..._notifications.map((item) => _buildNotificationCard(item, context)),
                    ],
                  ),
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
              color: item.isRead ? AppColors.ustpLight : AppColors.ustpGold.withValues(alpha: 0.2),
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
                  '$timeText${item.relatedUserCode != null ? " · ${item.relatedUserCode}" : ""}',
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
  State<AdminNotificationsOverlay> createState() => _AdminNotificationsOverlayState();
}

class _AdminNotificationsOverlayState extends State<AdminNotificationsOverlay> {
  final NotificationRepository _repository = NotificationRepository();
  bool _isLoading = true;
  List<NotificationModel> _notifications = [];

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    final results = await _repository.getNotifications();
    if (mounted) {
      setState(() {
        _notifications = results;
        _isLoading = false;
      });
    }
  }

  Future<void> _markAllAsRead() async {
    await _repository.markAllAsRead();
    if (mounted) {
      _loadNotifications();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 48),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(24),
        boxShadow: const [
          BoxShadow(
            color: Colors.black26,
            blurRadius: 20,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header Row
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
                IconButton(
                  icon: const Icon(AppIcons.settings, size: 20, color: AppColors.ustpMuted),
                  onPressed: () {},
                ),
              ],
            ),
            const SizedBox(height: 4),

            // Mark all as read button link
            GestureDetector(
              onTap: _markAllAsRead,
              child: const Text(
                'Mark all as read',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  color: AppColors.info,
                ),
              ),
            ),
            const SizedBox(height: 16),

            // List of Notification Cards
            if (_isLoading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(24.0),
                  child: CircularProgressIndicator(color: AppColors.ustpNavy),
                ),
              )
            else if (_notifications.isEmpty)
              const Padding(
                padding: EdgeInsets.all(24.0),
                child: Center(
                  child: Text(
                    'No notifications yet.',
                    style: TextStyle(color: AppColors.ustpMuted),
                  ),
                ),
              )
            else
              Flexible(
                child: SingleChildScrollView(
                  child: Column(
                    children: _notifications
                        .map((item) => _buildOverlayCard(item, context))
                        .toList(),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildOverlayCard(NotificationModel item, BuildContext context) {
    final timeText = _getTimeText(item.createdAt);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: item.isRead ? AppColors.ustpWhite : const Color(0xFFFEF9C3),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: item.isRead
              ? AppColors.ustpMuted.withValues(alpha: 0.15)
              : AppColors.ustpGold.withValues(alpha: 0.5),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: item.isRead ? AppColors.ustpLight : AppColors.ustpGold.withValues(alpha: 0.2),
              shape: BoxShape.circle,
            ),
            child: Icon(
              AppIcons.notifications,
              size: 18,
              color: item.isRead ? AppColors.ustpMuted : AppColors.ustpDark,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$timeText${item.relatedUserCode != null ? " · ${item.relatedUserCode}" : ""}',
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 11,
                    color: AppColors.ustpMuted,
                  ),
                ),
              ],
            ),
          ),
          if (!item.isRead) ...[
            const SizedBox(width: 6),
            Container(
              width: 8,
              height: 8,
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
