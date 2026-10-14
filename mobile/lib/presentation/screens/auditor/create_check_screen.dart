import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../data/models/department_model.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../../data/repositories/department_repository.dart';
import '../../../data/repositories/user_repository.dart';
import '../../widgets/ustp_app_bar.dart';

class CreateCheckScreen extends StatefulWidget {
  const CreateCheckScreen({super.key});

  @override
  State<CreateCheckScreen> createState() => _CreateCheckScreenState();
}

class _CreateCheckScreenState extends State<CreateCheckScreen> {
  final _departmentRepository = DepartmentRepository();
  final _userRepository = UserRepository();
  final _checkRepository = ComplianceRepository();
  final _remarksController = TextEditingController();
  late Future<({List<DepartmentModel> departments, List<UserModel> auditees})>
      _options;
  String? _departmentId;
  String? _auditeeId;
  String _standard = 'ARTA-3days';
  String _result = 'non_compliant';
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _options = _loadOptions();
  }

  Future<({List<DepartmentModel> departments, List<UserModel> auditees})>
      _loadOptions() async {
    final departments = await _departmentRepository.getDepartments();
    final auditees = await _userRepository.getUsers(roleId: 3);
    return (
      departments: departments.where((item) => item.isActive).toList(),
      auditees: auditees.where((item) => item.isActive).toList(),
    );
  }

  Future<void> _submit() async {
    if (_departmentId == null || _auditeeId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a department and auditee.')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await _checkRepository.createCheck(
        departmentId: _departmentId!,
        auditeeId: _auditeeId!,
        referenceStandard: _standard,
        result: _result,
        remarks: _remarksController.text.trim(),
      );
      if (!mounted) return;
      context.go('/auditor/checks');
    } on DioException catch (error) {
      if (mounted) {
        final body = error.response?.data;
        final message = body is Map && body['message'] is String
            ? body['message'] as String
            : 'Unable to create the compliance check.';
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
      }
    } on FormatException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
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
      appBar: const UstpAppBar(title: 'Create Compliance Check'),
      body: FutureBuilder<
          ({List<DepartmentModel> departments, List<UserModel> auditees})>(
        future: _options,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(
                child: Text(
                    'Could not load departments and auditees.\n${snapshot.error}',
                    textAlign: TextAlign.center));
          }
          if (!snapshot.hasData)
            return const Center(child: CircularProgressIndicator());
          final options = snapshot.data!;
          final auditees = options.auditees
              .where((user) =>
                  _departmentId == null || user.departmentId == _departmentId)
              .toList();
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              DropdownButtonFormField<String>(
                initialValue: _departmentId,
                decoration: const InputDecoration(labelText: 'Department'),
                items: options.departments
                    .map((department) => DropdownMenuItem(
                          value: department.id,
                          child: Text(department.departmentName),
                        ))
                    .toList(),
                onChanged: (value) => setState(() {
                  _departmentId = value;
                  if (!auditees.any((user) => user.id == _auditeeId))
                    _auditeeId = null;
                }),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _auditeeId,
                decoration: const InputDecoration(labelText: 'Auditee'),
                items: auditees
                    .map((user) => DropdownMenuItem(
                          value: user.id,
                          child: Text(user.fullName),
                        ))
                    .toList(),
                onChanged: (value) => setState(() => _auditeeId = value),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _standard,
                decoration:
                    const InputDecoration(labelText: 'Reference standard'),
                items: const [
                  DropdownMenuItem(
                      value: 'ARTA-3days', child: Text('ARTA - 3 days')),
                  DropdownMenuItem(
                      value: 'ARTA-7days', child: Text('ARTA - 7 days')),
                  DropdownMenuItem(
                      value: 'ARTA-20days', child: Text('ARTA - 20 days')),
                  DropdownMenuItem(
                      value: 'ISO9001-9.2', child: Text('ISO 9001 - 9.2')),
                ],
                onChanged: (value) =>
                    setState(() => _standard = value ?? _standard),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _result,
                decoration: const InputDecoration(labelText: 'Result'),
                items: const [
                  DropdownMenuItem(
                      value: 'compliant', child: Text('Compliant')),
                  DropdownMenuItem(
                      value: 'non_compliant', child: Text('Non-compliant')),
                ],
                onChanged: (value) =>
                    setState(() => _result = value ?? _result),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _remarksController,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Remarks',
                  alignLabelWithHint: true,
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _saving ? null : _submit,
                child: Text(_saving ? 'Saving…' : 'Create check'),
              ),
            ],
          );
        },
      ),
    );
  }
}
