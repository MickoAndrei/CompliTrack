import 'package:flutter/material.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/user_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class AdminUserManagementScreen extends StatefulWidget {
  const AdminUserManagementScreen({super.key});

  @override
  State<AdminUserManagementScreen> createState() => _AdminUserManagementScreenState();
}

class _AdminUserManagementScreenState extends State<AdminUserManagementScreen> {
  final _repository = UserRepository();
  late Future<List<UserModel>> _users;

  @override
  void initState() {
    super.initState();
    _users = _repository.getUsers();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const UstpAppBar(title: 'Department Users'),
      body: FutureBuilder<List<UserModel>>(
        future: _users,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text('Could not load users.\n${snapshot.error}', textAlign: TextAlign.center),
            ));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final users = snapshot.data!;
          if (users.isEmpty) {
            return const EmptyState(
              title: 'No managed users',
              subtitle: 'Auditors and auditees in your department will appear here.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => setState(() => _users = _repository.getUsers()),
            child: ListView.builder(
              itemCount: users.length,
              itemBuilder: (context, index) {
                final user = users[index];
                return ListTile(
                  title: Text(user.fullName),
                  subtitle: Text('${user.userCode} · ${user.email}'),
                  trailing: Text(user.roleSlug),
                );
              },
            ),
          );
        },
      ),
    );
  }
}