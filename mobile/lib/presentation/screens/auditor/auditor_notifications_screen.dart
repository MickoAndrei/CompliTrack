import 'package:flutter/material.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class AuditorNotificationsScreen extends StatefulWidget {
  const AuditorNotificationsScreen({super.key});

  @override
  State<AuditorNotificationsScreen> createState() => _AuditorNotificationsScreenState();
}

class _AuditorNotificationsScreenState extends State<AuditorNotificationsScreen> {
  final _repository = NotificationRepository();
  late Future<List<NotificationModel>> _notifications;

  @override
  void initState() {
    super.initState();
    _notifications = _repository.getNotifications();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const UstpAppBar(title: 'Notifications'),
      body: FutureBuilder<List<NotificationModel>>(
        future: _notifications,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Text('Could not load notifications.\n${snapshot.error}', textAlign: TextAlign.center));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final notifications = snapshot.data!;
          if (notifications.isEmpty) {
            return const EmptyState(title: 'You are all caught up', subtitle: 'New updates will appear here.');
          }
          return RefreshIndicator(
            onRefresh: () async => setState(() => _notifications = _repository.getNotifications()),
            child: ListView.builder(
              itemCount: notifications.length,
              itemBuilder: (context, index) {
                final item = notifications[index];
                return ListTile(
                  leading: Icon(item.isRead ? Icons.notifications_none : Icons.notifications_active_outlined),
                  title: Text(item.title),
                  subtitle: Text(item.message),
                  onTap: () async {
                    if (!item.isRead) await _repository.markAsRead(item.id);
                    if (mounted) setState(() => _notifications = _repository.getNotifications());
                  },
                );
              },
            ),
          );
        },
      ),
    );
  }
}