import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';
import '../../providers/auth_provider.dart';

Future<void> showNotificationPreview(BuildContext context) async {
  final role = context.read<AuthProvider>().roleId;
  final viewAll = await showGeneralDialog<bool>(context: context,
    barrierDismissible: true, barrierLabel: 'Dismiss notifications',
    barrierColor: const Color(0x100F172A), transitionDuration: const Duration(milliseconds: 160),
    pageBuilder: (context, _, _) => SafeArea(child: Align(alignment: Alignment.topRight,
      child: Padding(padding: const EdgeInsets.fromLTRB(12, 64, 12, 12), child: const NotificationPreview()))),
    transitionBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: child),
  );
  if (viewAll == true && context.mounted) {
    await context.push(switch (role) {
      0 => '/super-admin/notifications', 1 => '/admin/notifications', 2 => '/auditor/profile/notifications', _ => '/auditee/notifications',
    });
  }
}

class NotificationPreview extends StatefulWidget {
  const NotificationPreview({super.key, this.repository});
  final NotificationRepository? repository;
  @override State<NotificationPreview> createState() => _NotificationPreviewState();
}
class _NotificationPreviewState extends State<NotificationPreview> {
  late final _repository = widget.repository ?? NotificationRepository();
  int _page = 1;
  bool _busy = false;
  late Future<NotificationPage> _data = _repository.preview(page: 1);
  void _load(int page) { setState(() { _page = page; _data = _repository.preview(page: page); }); }
  Future<void> _act(Future<bool> Function() action, {bool reset = false}) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      if (!await action()) throw StateError('Notification update failed');
      if (mounted) _load(reset ? 1 : _page);
    } catch (_) {
      if (mounted) ScaffoldMessenger.maybeOf(context)?.showSnackBar(const SnackBar(content: Text('Unable to update notifications. Please retry.')));
    } finally { if (mounted) setState(() => _busy = false); }
  }
  @override Widget build(BuildContext context) => Material(
    color: Colors.white, elevation: 12, borderRadius: BorderRadius.circular(12), clipBehavior: Clip.antiAlias,
    child: ConstrainedBox(constraints: BoxConstraints(maxWidth: 420, maxHeight: MediaQuery.sizeOf(context).height * .68),
      child: FutureBuilder<NotificationPage>(future: _data, builder: (context, snapshot) {
        final data = snapshot.data;
        return Column(mainAxisSize: MainAxisSize.min, children: [
          Padding(padding: const EdgeInsets.fromLTRB(16, 8, 8, 4), child: Row(children: [
            const Expanded(child: Text('Notifications', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600))),
            IconButton(tooltip: 'Close notifications', onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close)),
          ])),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Row(children: [
            Expanded(child: Text('${data?.unreadCount ?? 0} unread', style: const TextStyle(color: AppColors.ustpMuted, fontSize: 12))),
            TextButton(onPressed: _busy || data == null || data.unreadCount == 0 ? null : () => _act(_repository.markAllAsRead), child: const Text('Read all')),
            TextButton(onPressed: _busy || data == null || data.total == 0 ? null : () => _act(_repository.clearAll, reset: true), child: const Text('Clear all')),
          ])),
          const Divider(height: 1),
          if (snapshot.hasError) Padding(padding: const EdgeInsets.all(20), child: TextButton(onPressed: () => _load(_page), child: const Text('Unable to load. Retry')))
          else if (snapshot.connectionState == ConnectionState.waiting || _busy) const Padding(padding: EdgeInsets.all(28), child: CircularProgressIndicator())
          else if (data == null || data.items.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Column(children: [Icon(Icons.check_circle_outline, color: AppColors.outcomeC, size: 32), SizedBox(height: 12), Text("You're all caught up.")]))
          else Flexible(child: ListView(shrinkWrap: true, padding: const EdgeInsets.symmetric(vertical: 4), children: [
            for (final NotificationModel item in data.items) ListTile(
              tileColor: item.isRead ? null : const Color(0xFFEAF0F8),
              leading: const Icon(Icons.notifications_none, color: AppColors.ustpNavy),
              title: Text(item.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
              subtitle: Text(item.message, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
              trailing: item.isRead ? null : const Icon(Icons.circle, size: 8, color: AppColors.ustpNavy),
              onTap: item.isRead || _busy ? null : () => _act(() => _repository.markAsRead(item.id)),
            ),
          ])),
          const Divider(height: 1),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            TextButton(onPressed: _busy || _page <= 1 ? null : () => _load(_page - 1), child: const Text('Previous')),
            Text('$_page / ${data?.lastPage ?? 1}'),
            TextButton(onPressed: _busy || _page >= (data?.lastPage ?? 1) ? null : () => _load(_page + 1), child: const Text('Next')),
          ]),
          Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 12), child: SizedBox(width: double.infinity, child: FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('View all notifications')))),
        ]);
      })),
  );
}
