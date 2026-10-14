import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class ChecksListScreen extends StatefulWidget {
  const ChecksListScreen({super.key});

  @override
  State<ChecksListScreen> createState() => _ChecksListScreenState();
}

class _ChecksListScreenState extends State<ChecksListScreen> {
  final _repository = ComplianceRepository();
  late Future<List<ComplianceCheckModel>> _checks;

  @override
  void initState() {
    super.initState();
    _checks = _repository.getChecks();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: UstpAppBar(
        title: 'Compliance Checks',
        onNotificationTap: () => context.go('/auditor/notifications'),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/auditor/checks/create'),
        icon: const Icon(Icons.add),
        label: const Text('New check'),
      ),
      body: FutureBuilder<List<ComplianceCheckModel>>(
        future: _checks,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Text('Could not load checks.\n${snapshot.error}', textAlign: TextAlign.center));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final checks = snapshot.data!;
          if (checks.isEmpty) {
            return const EmptyState(
              title: 'No assigned checks',
              subtitle: 'Create a check to begin recording compliance results.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => setState(() => _checks = _repository.getChecks()),
            child: ListView.builder(
              padding: const EdgeInsets.only(bottom: 88),
              itemCount: checks.length,
              itemBuilder: (context, index) {
                final check = checks[index];
                return ListTile(
                  title: Text(check.checkCode),
                  subtitle: Text('${check.referenceStandard} · ${check.result.replaceAll('_', ' ')}'),
                  trailing: Text(check.correctiveStatus ?? 'Compliant'),
                  onTap: () => context.push('/auditor/checks/${check.id}'),
                );
              },
            ),
          );
        },
      ),
    );
  }
}