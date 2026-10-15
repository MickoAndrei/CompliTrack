import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../widgets/ustp_app_bar.dart';

class CheckDetailScreen extends StatefulWidget {
  const CheckDetailScreen({super.key, required this.checkId});

  final String checkId;

  @override
  State<CheckDetailScreen> createState() => _CheckDetailScreenState();
}

class _CheckDetailScreenState extends State<CheckDetailScreen> {
  final _repository = ComplianceRepository();
  final _remarksController = TextEditingController();
  late Future<ComplianceCheckModel> _check;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _check = _repository.getCheck(widget.checkId);
  }

  Future<void> _changeStatus(String nextStatus) async {
    setState(() => _saving = true);
    try {
      await _repository.updateStatus(
        checkId: widget.checkId,
        correctiveStatus: nextStatus,
        remarks: _remarksController.text.trim(),
      );
      _remarksController.clear();
      setState(() => _check = _repository.getCheck(widget.checkId));
    } on DioException catch (error) {
      if (mounted) {
        final body = error.response?.data;
        final message = body is Map && body['message'] is String
            ? body['message'] as String
            : 'Unable to update the check status.';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _remarksController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const UstpAppBar(title: 'Check Details'),
      body: FutureBuilder<ComplianceCheckModel>(
        future: _check,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Text('Could not load this check.\n${snapshot.error}', textAlign: TextAlign.center));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final check = snapshot.data!;
          final nextStatus = switch (check.correctiveStatus) {
            'open' => 'in_progress',
            'in_progress' => 'closed',
            'closed' => 'open',
            _ => null,
          };
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Text(check.checkCode, style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              Text('Result: ${check.result.replaceAll('_', ' ')}'),
              Text('Standard: ${check.referenceStandard}'),
              Text('Corrective status: ${check.correctiveStatus ?? 'Not required'}'),
              if (check.remarks != null) ...[
                const SizedBox(height: 12),
                Text('Remarks: ${check.remarks}'),
              ],
              if (nextStatus != null) ...[
                const SizedBox(height: 24),
                TextField(
                  controller: _remarksController,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Status change remarks',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _saving ? null : () => _changeStatus(nextStatus),
                  child: Text(_saving ? 'Saving…' : 'Move to ${nextStatus.replaceAll('_', ' ')}'),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}