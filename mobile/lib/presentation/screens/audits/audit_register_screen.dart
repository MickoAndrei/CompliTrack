import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/repositories/audit_workspace_repository.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/ustp_app_bar.dart';

class AuditRegisterScreen extends StatefulWidget {
  const AuditRegisterScreen({super.key});
  @override
  State<AuditRegisterScreen> createState() => _AuditRegisterScreenState();
}
class _AuditRegisterScreenState extends State<AuditRegisterScreen> {
  final _repository = AuditWorkspaceRepository();
  final _search = TextEditingController();
  String _status = '';
  int _page = 1;
  late Future<Map<String, dynamic>> _records = _repository.list();
  @override
  void dispose() { _search.dispose(); super.dispose(); }
  Future<void> _load({int page = 1}) async {
    final future = _repository.list(search: _search.text.trim(), status: _status, page: page);
    setState(() { _page = page; _records = future; });
    await future;
  }
  @override
  Widget build(BuildContext context) {
    final atl = context.watch<AuthProvider>().roleId == 1;
    final prefix = atl ? '/admin' : '/auditor';
    return Scaffold(appBar: const UstpAppBar(title: 'Audits'), body: Column(children: [
      Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 8), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(atl ? 'Audit assignments' : 'My assigned audits', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 4), const Text('Review scheduled work and current progress.', style: TextStyle(color: AppColors.ustpMuted)),
        const SizedBox(height: 12),
        Wrap(spacing: 8, runSpacing: 4, children: atl ? [
          OutlinedButton(onPressed: () => context.push('/admin/plans'), child: const Text('Audit plans')),
          OutlinedButton(onPressed: () => context.push('/admin/reports'), child: const Text('Reports')),
          OutlinedButton(onPressed: () => context.push('/admin/users'), child: const Text('Users')),
          OutlinedButton(onPressed: () => context.push('/admin/departments'), child: const Text('Departments')),
        ] : [
          OutlinedButton(onPressed: () => context.push('/auditor/checks'), child: const Text('Compliance checks')),
          OutlinedButton(onPressed: () => context.push('/auditor/corrective'), child: const Text('CARs')),
        ]),
        const SizedBox(height: 12),
        TextField(controller: _search, textInputAction: TextInputAction.search, onSubmitted: (_) => _load(), decoration: InputDecoration(hintText: 'Search audits', prefixIcon: const Icon(Icons.search), suffixIcon: IconButton(tooltip: 'Search', onPressed: () => _load(), icon: const Icon(Icons.arrow_forward)))),
        const SizedBox(height: 8),
        DropdownButtonFormField<String>(initialValue: _status, decoration: const InputDecoration(labelText: 'Status'), items: const [
          DropdownMenuItem(value: '', child: Text('All statuses')), DropdownMenuItem(value: 'draft', child: Text('Assigned')), DropdownMenuItem(value: 'ready', child: Text('Ready')), DropdownMenuItem(value: 'in_progress', child: Text('In progress')), DropdownMenuItem(value: 'submitted', child: Text('Awaiting review')), DropdownMenuItem(value: 'returned', child: Text('Returned')), DropdownMenuItem(value: 'accepted', child: Text('Completed')),
        ], onChanged: (value) { _status = value ?? ''; _load(); }),
      ])),
      Expanded(child: FutureBuilder<Map<String, dynamic>>(future: _records, builder: (context, snapshot) {
        if (snapshot.hasError) return Center(child: FilledButton(onPressed: () => _load(page: _page), child: const Text('Retry')));
        if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
        final data = snapshot.data!;
        final rows = (data['data'] as List? ?? []).map((r) => Map<String, dynamic>.from(r as Map)).toList();
        final last = (data['lastPage'] as num?)?.toInt() ?? 1;
        return RefreshIndicator(onRefresh: () => _load(page: _page), child: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 16), children: [
          if (rows.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(24), child: Text('No audits match these filters.'))),
          ...rows.map((row) => Card(child: InkWell(borderRadius: BorderRadius.circular(12), onTap: () async { await context.push('$prefix/audit-workspaces/${row['id']}'); if (mounted) _load(page: _page); }, child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [Expanded(child: Text('${row['code'] ?? ''}', style: const TextStyle(color: AppColors.ustpMuted))), StatusBadge(status: '${row['label'] ?? row['status']}')]), const SizedBox(height: 8),
            Text('${row['title'] ?? ''}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)), const SizedBox(height: 4), Text('${row['department'] ?? ''} · ${row['scheduled'] ?? ''}'),
            const SizedBox(height: 12), LinearProgressIndicator(value: ((row['progress'] as Map?)?['percent'] as num? ?? 0).toDouble().clamp(0,100).toDouble()/100, borderRadius: BorderRadius.circular(8)),
            const SizedBox(height: 8), Row(children: [Expanded(child: Text('${row['action'] ?? 'View audit'}')), const Icon(Icons.chevron_right)]),
          ]))))),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [TextButton(onPressed: _page > 1 ? () => _load(page: _page - 1) : null, child: const Text('Previous')), Text('$_page / $last'), TextButton(onPressed: _page < last ? () => _load(page: _page + 1) : null, child: const Text('Next'))]),
        ]));
      })),
    ]));
  }
}
