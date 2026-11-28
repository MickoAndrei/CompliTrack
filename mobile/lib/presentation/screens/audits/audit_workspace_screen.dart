import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/repositories/audit_workspace_repository.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/ustp_app_bar.dart';
import '../../widgets/v2/portal_dialog.dart';

class AuditWorkspaceScreen extends StatefulWidget {
  const AuditWorkspaceScreen({super.key, required this.auditId});
  final String auditId;
  @override
  State<AuditWorkspaceScreen> createState() => _AuditWorkspaceScreenState();
}

class _AuditWorkspaceScreenState extends State<AuditWorkspaceScreen> {
  final _repository = AuditWorkspaceRepository();
  late Future<Map<String, dynamic>> _record = _repository.detail(widget.auditId);
  bool _saving = false;
  Future<void> _reload() async {
    final future = _repository.detail(widget.auditId);
    setState(() => _record = future);
    await future;
  }

  Future<void> _save(Map<String, dynamic> data, String action, Map<String, dynamic> values) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      await _repository.update(widget.auditId, data['version'] as int, action, values);
      if (!mounted) return;
      await _reload();
    } on DioException catch (error) {
      if (!mounted) return;
      final message = error.response?.statusCode == 409 ? 'This audit changed. Refresh before saving.' : 'The server could not save this action. Check the required fields and permissions.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Could not save or refresh the audit.')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _form(Map<String, dynamic> data, String action, {int? item}) async {
    final result = await showPortalDialog<Map<String, dynamic>>(
      context: context, builder: (_) => _AuditActionDialog(action: action, item: item));
    if (result != null && mounted) await _save(data, action, result);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: const UstpAppBar(title: 'Audit workspace'),
    body: FutureBuilder<Map<String, dynamic>>(future: _record, builder: (context, snapshot) {
      if (snapshot.hasError) return Center(child: FilledButton(onPressed: _reload, child: const Text('Retry loading audit')));
      if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
      final data = snapshot.data!;
      final items = (data['items'] as List? ?? []).map((v) => Map<String, dynamic>.from(v as Map)).toList();
      final status = data['storedStatus'] as String? ?? '';
      final editable = data['canEdit'] == true;
      final readiness = Map<String, dynamic>.from(data['readiness'] as Map? ?? {});
      return RefreshIndicator(onRefresh: _reload, child: ListView(padding: const EdgeInsets.all(16), children: [
        Text('${data['code'] ?? ''} · ${data['department'] ?? ''}', style: Theme.of(context).textTheme.labelMedium),
        const SizedBox(height: 8),
        Text('${data['title'] ?? 'Audit'}', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 8),
        Wrap(spacing: 8, runSpacing: 4, children: [StatusBadge(status: '${data['label'] ?? status}'), Text('${data['scheduled'] ?? ''}')]),
        const SizedBox(height: 16),
        Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Scope and criteria', style: TextStyle(fontWeight: FontWeight.w600)),
          Text('${data['scope'] ?? 'Not recorded'}'), const SizedBox(height: 8), Text('${data['criteria'] ?? 'Not recorded'}'),
        ]))),
        const SizedBox(height: 16),
        Row(children: [const Expanded(child: Text('Audit checklist', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600))),
          if (editable && status == 'draft') TextButton.icon(onPressed: _saving ? null : () => _form(data, 'add'), icon: const Icon(Icons.add), label: const Text('Add item')),
        ]),
        if (items.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('No checklist items yet.'))),
        for (var i = 0; i < items.length; i++) Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [Expanded(child: Text('Check item ${i + 1}', style: Theme.of(context).textTheme.labelMedium)), StatusBadge(status: '${items[i]['result'] ?? 'Pending'}')]),
          const SizedBox(height: 8), Text('${items[i]['question'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600)),
          if ('${items[i]['reference'] ?? ''}'.isNotEmpty) Text('${items[i]['reference']}'),
          if ('${items[i]['remarks'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text('${items[i]['remarks']}')),
          if ('${items[i]['finding'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text('Finding: ${items[i]['finding']}')),
          const SizedBox(height: 8), Text('${(items[i]['evidence'] as List? ?? []).length} evidence review(s)'),
          if (editable && status != 'draft') Wrap(spacing: 8, children: [
            OutlinedButton(onPressed: _saving ? null : () => _form(data, 'evidence', item: i), child: const Text('Review evidence')),
            FilledButton(onPressed: _saving || (items[i]['evidence'] as List? ?? []).isEmpty ? null : () => _form(data, 'evaluate', item: i), child: const Text('Evaluate')),
          ]),
        ]))),
        const SizedBox(height: 16),
        if (editable && status == 'draft') FilledButton(onPressed: _saving || items.isEmpty ? null : () => _save(data, 'ready', {}), child: const Text('Mark checklist ready')),
        if (editable && status != 'draft') ...[
          Text(readiness['eligible'] == true ? 'Ready for submission' : 'Complete checklist decisions, remarks and evidence reviews first.'),
          const SizedBox(height: 8), FilledButton(onPressed: _saving || readiness['eligible'] != true ? null : () => _form(data, 'submit'), child: const Text('Submit audit results')),
        ],
        if (data['canReview'] == true) Wrap(spacing: 8, children: [OutlinedButton(onPressed: _saving ? null : () => _form(data, 'return'), child: const Text('Return for revision')), FilledButton(onPressed: _saving ? null : () => _form(data, 'accept'), child: const Text('Accept results'))]),
        if (_saving) const LinearProgressIndicator(color: AppColors.ustpNavy),
      ]));
    }),
  );
}

class _AuditActionDialog extends StatefulWidget {
  const _AuditActionDialog({required this.action, this.item});
  final String action;
  final int? item;
  @override
  State<_AuditActionDialog> createState() => _AuditActionDialogState();
}
class _AuditActionDialogState extends State<_AuditActionDialog> {
  final _key = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _second = TextEditingController();
  String _outcome = 'C';
  String _method = 'digital';
  String _signature = 'verified';
  @override
  void dispose() { _first.dispose(); _second.dispose(); super.dispose(); }
  String? _required(String? v) => v == null || v.trim().isEmpty ? 'Required' : null;
  Widget _field(TextEditingController c, String label, {bool required = true, int max = 5000}) => TextFormField(
    controller: c, maxLength: max, minLines: 1, maxLines: 4, decoration: InputDecoration(labelText: label), validator: required ? _required : null,
  );
  @override
  Widget build(BuildContext context) {
    final a = widget.action;
    return AlertDialog(
      title: Row(children: [Expanded(child: Text(switch (a) {'add' => 'Add check item', 'evaluate' => 'Compliance outcome', 'evidence' => 'Review evidence', 'submit' => 'Submit results', 'return' => 'Return for revision', _ => 'Accept audit results'})), IconButton(tooltip: 'Close', onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close))]),
      content: SingleChildScrollView(child: Form(key: _key, child: Column(mainAxisSize: MainAxisSize.min, children: [
        if (a == 'add') ...[_field(_first, 'Check item', max: 2000), _field(_second, 'Reference standard', required: false, max: 500)],
        if (a == 'evaluate') ...[
          DropdownButtonFormField<String>(initialValue: _outcome, decoration: const InputDecoration(labelText: 'Result'), items: const [DropdownMenuItem(value: 'N', child: StatusBadge(status: 'N')), DropdownMenuItem(value: 'C', child: StatusBadge(status: 'C')), DropdownMenuItem(value: 'O', child: StatusBadge(status: 'O'))], onChanged: (v) => setState(() => _outcome = v ?? 'C')),
          const SizedBox(height: 12), _field(_first, 'Audit remarks'), if (_outcome != 'C') _field(_second, 'Finding description', required: _outcome == 'N'),
        ],
        if (a == 'evidence') ...[
          DropdownButtonFormField<String>(initialValue: _method, decoration: const InputDecoration(labelText: 'Evidence method'), items: const [DropdownMenuItem(value: 'digital', child: Text('Digital reference')), DropdownMenuItem(value: 'physical-original', child: Text('Physical original')), DropdownMenuItem(value: 'restricted', child: Text('Restricted record')), DropdownMenuItem(value: 'interview-process', child: Text('Interview / process'))], onChanged: (v) => setState(() => _method = v ?? 'digital')),
          const SizedBox(height: 12), _field(_first, 'Permitted reference', required: false, max: 500), _field(_second, 'Verification notes'),
          if (_method == 'physical-original') DropdownButtonFormField<String>(initialValue: _signature, decoration: const InputDecoration(labelText: 'Signature'), items: const [DropdownMenuItem(value: 'verified', child: Text('Verified')), DropdownMenuItem(value: 'missing-incomplete', child: Text('Missing / incomplete')), DropdownMenuItem(value: 'not-verified', child: Text('Not verified')), DropdownMenuItem(value: 'not-required', child: Text('Not required'))], onChanged: (v) => setState(() => _signature = v ?? 'verified')),
        ],
        if (a == 'accept' || a == 'return') _field(_first, 'Review remarks', required: a == 'return'),
        if (a == 'submit') const Text('Submit this checklist to the assigned ATL? Editing will be locked until the reviewer returns it.'),
      ]))),
      actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')), FilledButton(onPressed: () {
        if (!_key.currentState!.validate()) return;
        final values = <String, dynamic>{if (widget.item != null) 'item': widget.item};
        switch (a) {
          case 'add': values.addAll({'question': _first.text.trim(), 'reference': _second.text.trim()});
          case 'evaluate': values.addAll({'result': _outcome, 'remarks': _first.text.trim(), 'finding': _second.text.trim()});
          case 'evidence': values.addAll({'method': _method, 'reference': _first.text.trim(), 'notes': _second.text.trim(), if (_method == 'physical-original') 'signature': _signature});
          case 'accept' || 'return': values['reviewNotes'] = _first.text.trim();
        }
        Navigator.pop(context, values);
      }, child: const Text('Confirm'))],
    );
  }
}
