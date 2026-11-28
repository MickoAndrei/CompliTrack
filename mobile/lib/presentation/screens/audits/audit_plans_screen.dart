import 'package:flutter/material.dart';
import '../../../data/repositories/audit_workspace_repository.dart';
import '../../widgets/ustp_app_bar.dart';
import '../../widgets/v2/portal_dialog.dart';

class AuditPlansScreen extends StatefulWidget {
  const AuditPlansScreen({super.key});
  @override
  State<AuditPlansScreen> createState() => _AuditPlansScreenState();
}
class _AuditPlansScreenState extends State<AuditPlansScreen> {
  final _repository = AuditWorkspaceRepository();
  late Future<List<Map<String, dynamic>>> _plans = _repository.plans();
  Future<void> _reload() async { final next = _repository.plans(); setState(() => _plans = next); await next; }
  Future<void> _details(Map<String, dynamic> row) async {
    final plan = Map<String, dynamic>.from(row['plan'] as Map? ?? {});
    final header = Map<String, dynamic>.from(plan['header'] as Map? ?? {});
    final result = await showPortalDialog<({String action, String remarks})>(context: context, builder: (_) => _PlanDetails(row: row, plan: plan, header: header));
    if (result == null || !mounted) return;
    try { await _repository.decidePlan(row['id'] as String, plan['version'] as int, result.action, result.remarks); if (mounted) await _reload(); }
    catch (_) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Plan decision could not be saved. Refresh and check permission.'))); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: const UstpAppBar(title: 'Audit plans'), body: FutureBuilder<List<Map<String,dynamic>>>(future: _plans, builder: (context, snapshot) {
    if (snapshot.hasError) return Center(child: FilledButton(onPressed: _reload, child: const Text('Retry loading plans')));
    if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(onRefresh: _reload, child: ListView(padding: const EdgeInsets.all(16), children: [
      Text('Audit plans', style: Theme.of(context).textTheme.headlineSmall), const SizedBox(height: 8), const Text('Controlled plan records. Prepare or revise a plan in the web workspace.'), const SizedBox(height: 12),
      if (snapshot.data!.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('No accessible plans.'))),
      ...snapshot.data!.map((row) => Card(child: ListTile(onTap: () => _details(row), leading: const Icon(Icons.calendar_month_outlined), title: Text('${row['title'] ?? ''}'), subtitle: Text('${row['reference'] ?? ''}\n${row['stateLabel'] ?? ''}'), isThreeLine: true, trailing: const Icon(Icons.chevron_right)))),
    ]));
  }));
}
class _PlanDetails extends StatefulWidget {
  const _PlanDetails({required this.row, required this.plan, required this.header});
  final Map<String,dynamic> row, plan, header;
  @override
  State<_PlanDetails> createState() => _PlanDetailsState();
}
class _PlanDetailsState extends State<_PlanDetails> {
  final _remarks = TextEditingController();
  @override void dispose() { _remarks.dispose(); super.dispose(); }
  @override Widget build(BuildContext context) => AlertDialog(
    title: Row(children: [const Expanded(child: Text('Plan details')), IconButton(tooltip: 'Close', onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close))]),
    content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('${widget.row['title'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600)), Text('${widget.row['stateLabel'] ?? ''} · Revision ${widget.plan['version'] ?? ''}'), const SizedBox(height: 12),
      Text('Schedule: ${widget.header['scheduleStart'] ?? ''} – ${widget.header['scheduleEnd'] ?? ''}'), const SizedBox(height: 8), Text('Scope: ${widget.header['scope'] ?? ''}'), Text('Criteria: ${widget.header['criteria'] ?? ''}'),
      if (widget.row['legacyWarning'] != null) Text('${widget.row['legacyWarning']}'),
      if (widget.row['canDecide'] == true) Padding(padding: const EdgeInsets.only(top: 12), child: TextField(controller: _remarks, onChanged: (_) => setState(() {}), maxLines: 3, maxLength: 3000, decoration: const InputDecoration(labelText: 'Decision remarks'))),
    ])),
    actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Close')),
      if (widget.row['canDecide'] == true) ...[
        OutlinedButton(onPressed: _remarks.text.trim().isEmpty ? null : () => Navigator.pop(context, (action: 'return', remarks: _remarks.text.trim())), child: const Text('Return')),
        FilledButton(onPressed: _remarks.text.trim().isEmpty ? null : () => Navigator.pop(context, (action: 'approve', remarks: _remarks.text.trim())), child: const Text('Approve')),
      ]],
  );
}
