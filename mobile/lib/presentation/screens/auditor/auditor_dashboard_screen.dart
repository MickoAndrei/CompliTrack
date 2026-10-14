import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/ustp_app_bar.dart';

class AuditorDashboardScreen extends StatefulWidget {
  const AuditorDashboardScreen({super.key});

  @override
  State<AuditorDashboardScreen> createState() => _AuditorDashboardScreenState();
}

class _AuditorDashboardScreenState extends State<AuditorDashboardScreen> {
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
      appBar: const UstpAppBar(title: 'Auditor Dashboard'),
      body: FutureBuilder<List<ComplianceCheckModel>>(
        future: _checks,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text('Could not load assigned checks.\n${snapshot.error}', textAlign: TextAlign.center),
            ));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final checks = snapshot.data!;
          final open = checks.where((check) => check.correctiveStatus == 'open').length;
          final progress = checks.where((check) => check.correctiveStatus == 'in_progress').length;
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Text('Assigned checks: ${checks.length}', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 12),
              Text('Open corrective actions: $open'),
              Text('In progress: $progress'),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: () => context.go('/auditor/checks'),
                child: const Text('View assigned checks'),
              ),
              OutlinedButton(
                onPressed: () => context.push('/auditor/checks/create'),
                child: const Text('Create compliance check'),
              ),
            ],
          );
        },
      ),
    );
  }
}