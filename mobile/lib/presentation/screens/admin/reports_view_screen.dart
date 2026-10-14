import 'package:flutter/material.dart';
import '../../../data/repositories/report_repository.dart';
import '../../widgets/ustp_app_bar.dart';

class ReportsViewScreen extends StatefulWidget {
  const ReportsViewScreen({super.key});

  @override
  State<ReportsViewScreen> createState() => _ReportsViewScreenState();
}

class _ReportsViewScreenState extends State<ReportsViewScreen> {
  final _repository = ReportRepository();
  late Future<Map<String, dynamic>> _reports;

  @override
  void initState() {
    super.initState();
    _reports = _repository.getReports();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const UstpAppBar(title: 'Compliance Reports'),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _reports,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text('Could not load reports.\n${snapshot.error}', textAlign: TextAlign.center),
            ));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final report = snapshot.data!;
          return ListView(
            children: [
              _ReportValue(label: 'Compliance rate', value: '${report['complianceRate']}%'),
              _ReportValue(label: 'Open corrective actions', value: '${report['openCars']}'),
              _ReportValue(label: 'Open over 7 days', value: '${report['openOverSevenDays']}'),
              _ReportValue(label: 'Auditors', value: '${report['totalAuditors']}'),
              _ReportValue(label: 'Auditees', value: '${report['totalAuditees']}'),
            ],
          );
        },
      ),
    );
  }
}

class _ReportValue extends StatelessWidget {
  const _ReportValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => ListTile(title: Text(label), trailing: Text(value));
}