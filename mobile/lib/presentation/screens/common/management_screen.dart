import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../widgets/ustp_app_bar.dart';

class ManagementScreen extends StatelessWidget {
  const ManagementScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: const UstpAppBar(title: 'Manage workspace'),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      Text('Organization & access', style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 12),
      for (final entry in const [
        (Icons.people_outline, 'User accounts', '/super-admin/users'),
        (Icons.account_tree_outlined, 'Departments & offices', '/super-admin/departments'),
        (Icons.history, 'System activity', '/super-admin/logs'),
        (Icons.settings_outlined, 'Settings', '/super-admin/settings'),
      ]) Card(child: ListTile(leading: Icon(entry.$1), title: Text(entry.$2), trailing: const Icon(Icons.chevron_right), onTap: () => context.push(entry.$3))),
    ]),
  );
}
