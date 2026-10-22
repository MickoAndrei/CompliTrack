import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';
import '../../widgets/auditor_page_header.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class AuditorNotificationsScreen extends StatefulWidget {
  const AuditorNotificationsScreen({super.key});

  @override
  State<AuditorNotificationsScreen> createState() =>
      _AuditorNotificationsScreenState();
}

class _AuditorNotificationsScreenState
    extends State<AuditorNotificationsScreen> {
  final _repository = NotificationRepository();
  late Future<List<NotificationModel>> _notifications;

  @override
  void initState() {
    super.initState();
    _notifications = _repository.getNotifications();
  }

  Future<void> _refresh() async {
    setState(() => _notifications = _repository.getNotifications());
    await _notifications;
  }

  Future<void> _openNotification(NotificationModel item) async {
    try {
      if (!item.isRead) await _repository.markAsRead(item.id);
      if (!mounted) return;
      if (item.relatedCheckId != null) {
        context.push('/auditor/checks/${item.relatedCheckId}');
      } else {
        await _refresh();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not update notification: $error')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: UstpAppBar(
        onNotificationTap: () async =>
            context.go('/auditor/profile/notifications'),
      ),
      body: FutureBuilder<List<NotificationModel>>(
        future: _notifications,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _NotificationsError(onRetry: _refresh);
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final notifications = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 20),
              children: [
                const AuditorPageHeader(
                  title: 'Notifications',
                  subtitle: 'Evidence upload inbox',
                ),
                Container(
                  padding: const EdgeInsets.fromLTRB(10, 10, 10, 8),
                  decoration: BoxDecoration(
                    color: AppColors.ustpWhite,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Padding(
                        padding: EdgeInsets.fromLTRB(1, 1, 1, 7),
                        child: Text(
                          'Recent Notifications',
                          style: TextStyle(
                            fontFamily: 'Poppins',
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: AppColors.ustpDark,
                          ),
                        ),
                      ),
                      if (notifications.isEmpty)
                        const SizedBox(
                          height: 190,
                          child: EmptyState(
                            title: 'You are all caught up',
                            subtitle: 'New updates will appear here.',
                            icon: AppIcons.notifications,
                          ),
                        )
                      else
                        for (final item in notifications)
                          _NotificationCard(
                            item: item,
                            onTap: () => _openNotification(item),
                          ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.item, required this.onTap});

  final NotificationModel item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: item.isRead ? AppColors.ustpLight : const Color(0xFFF3F5F8),
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(9),
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 27,
                height: 27,
                margin: const EdgeInsets.only(top: 2),
                decoration: const BoxDecoration(
                  color: AppColors.ustpWhite,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  AppIcons.upload,
                  color: AppColors.warning,
                  size: 15,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpDark,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      item.message,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 9,
                        color: AppColors.ustpDark,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _relativeTime(item.createdAt),
                      style: const TextStyle(
                        fontSize: 8,
                        color: AppColors.ustpMuted,
                      ),
                    ),
                  ],
                ),
              ),
              if (!item.isRead)
                const Padding(
                  padding: EdgeInsets.only(top: 5, left: 5),
                  child: Icon(
                    Icons.circle,
                    size: 7,
                    color: AppColors.info,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotificationsError extends StatelessWidget {
  const _NotificationsError({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('Could not load notifications.'),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(AppIcons.refresh),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}

String _relativeTime(DateTime date) {
  final difference = DateTime.now().difference(date.toLocal());
  if (difference.inMinutes < 1) return 'just now';
  if (difference.inMinutes < 60) return '${difference.inMinutes} min ago';
  if (difference.inHours < 24) return '${difference.inHours} hr ago';
  if (difference.inDays < 7) return '${difference.inDays} days ago';
  return DateFormat('yyyy-MM-dd').format(date.toLocal());
}
