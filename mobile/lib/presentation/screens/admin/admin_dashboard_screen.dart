import 'package:flutter/material.dart';
import '../../../data/models/super_admin_dashboard_model.dart';
import '../../../data/repositories/super_admin_repository.dart';
import '../../widgets/ustp_app_bar.dart';

class AdminDashboardScreen extends StatefulWidget {
  const AdminDashboardScreen({super.key});

  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {
  final _repository = SuperAdminRepository();
  late Future<SuperAdminDashboardModel> _metrics;

  @override
  void initState() {
    super.initState();
    _metrics = _repository.getDashboardMetrics();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const UstpAppBar(title: 'Admin Dashboard'),
      body: FutureBuilder<SuperAdminDashboardModel>(
        future: _metrics,
        builder: (context, snapshot) {
          if (snapshot.hasError) return _LoadError(error: snapshot.error!);
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final data = snapshot.data!;
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              _MetricCard(label: 'Active users', value: '${data.totalUsers}'),
              _MetricCard(label: 'Departments', value: '${data.totalDepts}'),
              _MetricCard(label: 'Open corrective actions', value: '${data.activeAudits}'),
              _MetricCard(label: 'Archive storage', value: '${data.storageUsedGb} GB'),
            ],
          );
        },
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Card(
        child: ListTile(title: Text(label), trailing: Text(value)),
      );
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.error});

  final Object error;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text('Could not load dashboard data.\n$error', textAlign: TextAlign.center),
        ),
      );
}