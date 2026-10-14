import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class CorrectiveBoardScreen extends StatefulWidget {
  const CorrectiveBoardScreen({super.key});

  @override
  State<CorrectiveBoardScreen> createState() => _CorrectiveBoardScreenState();
}

class _CorrectiveBoardScreenState extends State<CorrectiveBoardScreen> {
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
      appBar: const UstpAppBar(title: 'Corrective Actions'),
      body: FutureBuilder<List<ComplianceCheckModel>>(
        future: _checks,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Text('Could not load corrective actions.\n${snapshot.error}', textAlign: TextAlign.center));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final checks = snapshot.data!
              .where((check) => check.correctiveStatus != null)
              .toList();
          if (checks.isEmpty) {
            return const EmptyState(
              title: 'No open corrective actions',
              subtitle: 'Non-compliant findings will be shown here.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => setState(() => _checks = _repository.getChecks()),
            child: ListView.builder(
              itemCount: checks.length,
              itemBuilder: (context, index) {
                final check = checks[index];
                return ListTile(
                  title: Text(check.checkCode),
                  subtitle: Text(check.remarks ?? check.referenceStandard),
                  trailing: Text(check.correctiveStatus!),
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