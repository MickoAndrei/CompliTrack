import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/models/department_model.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../../data/repositories/department_repository.dart';
import '../../../data/repositories/user_repository.dart';

class CreateCheckScreen extends StatefulWidget {
  const CreateCheckScreen({super.key});

  @override
  State<CreateCheckScreen> createState() => _CreateCheckScreenState();
}

class _CreateCheckScreenState extends State<CreateCheckScreen> {
  final _departmentRepository = DepartmentRepository();
  final _userRepository = UserRepository();
  final _archiveRepository = ArchiveRepository();
  final _checkRepository = ComplianceRepository();
  final _remarksController = TextEditingController();
  late Future<
      ({
        List<DepartmentModel> departments,
        List<UserModel> auditees,
        List<ArchiveDocumentModel> archives
      })> _options;
  String? _departmentId;
  String? _auditeeId;
  String _standard = 'ISO9001-9.2';
  String _result = 'non_compliant';
  final Set<String> _selectedArchiveIds = {};
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _options = _loadOptions();
  }

  Future<
      ({
        List<DepartmentModel> departments,
        List<UserModel> auditees,
        List<ArchiveDocumentModel> archives
      })> _loadOptions() async {
    final departments =
        await _departmentRepository.getDepartments(campus: 'CET Claveria');
    final auditees = await _userRepository.getUsers(roleId: 3, perPage: 100);
    final archives = await _archiveRepository.getArchiveDocuments();
    return (
      departments: departments.where((item) => item.isActive).toList(),
      auditees: auditees.where((item) => item.isActive).toList(),
      archives: archives,
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
        linkedArchiveIds: _selectedArchiveIds.toList(),
      );
      if (mounted) context.go('/auditor/checks');
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
      backgroundColor: AppColors.ustpDark.withValues(alpha: 0.38),
      body: SafeArea(
        child: Center(
          child: FutureBuilder<
              ({
                List<DepartmentModel> departments,
                List<UserModel> auditees,
                List<ArchiveDocumentModel> archives
              })>(
            future: _options,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return _OptionsError(
                  error: snapshot.error!,
                  onRetry: () => setState(() => _options = _loadOptions()),
                  onClose: () => context.pop(),
                );
              }
              if (!snapshot.hasData) {
                return const CircularProgressIndicator(
                  color: AppColors.ustpWhite,
                );
              }
              return _buildForm(snapshot.data!);
            },
          ),
        ),
      ),
    );
  }

  Widget _buildForm(
    ({
      List<DepartmentModel> departments,
      List<UserModel> auditees,
      List<ArchiveDocumentModel> archives
    }) options,
  ) {
    final matchingDepartments = options.departments
        .where((department) => department.id == _departmentId)
        .toList();
    final selectedDepartment =
        matchingDepartments.isEmpty ? null : matchingDepartments.first;
    final auditees = options.auditees
        .where((user) =>
            _departmentId == null || user.departmentId == _departmentId)
        .toList();
    final eligibleArchives = options.archives.where((archive) {
      return selectedDepartment != null &&
          archive.departmentCode == selectedDepartment.departmentCode &&
          archive.referenceStandard == _standard;
    }).toList();

    return Dialog(
      backgroundColor: AppColors.ustpWhite,
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 430,
          maxHeight: MediaQuery.sizeOf(context).height * 0.88,
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(15, 12, 15, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'New Compliance Check',
                      style: TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpDark,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    visualDensity: VisualDensity.compact,
                    onPressed: () => context.pop(),
                    icon: const Icon(AppIcons.close, size: 17),
                  ),
                ],
              ),
              const SizedBox(height: 5),
              const _FormLabel('Department'),
              const SizedBox(height: 4),
              _DropdownField<String>(
                value: _departmentId,
                hint: 'Select a department',
                items: [
                  for (final department in options.departments)
                    DropdownMenuItem(
                      value: department.id,
                      child: Text(department.departmentName),
                    ),
                ],
                onChanged: (value) => setState(() {
                  _departmentId = value;
                  _auditeeId = null;
                  _selectedArchiveIds.clear();
                }),
              ),
              const SizedBox(height: 9),
              const _FormLabel('Auditee (Filtered by department)'),
              const SizedBox(height: 4),
              _DropdownField<String>(
                value: _auditeeId,
                hint: 'Select an auditee',
                items: [
                  for (final user in auditees)
                    DropdownMenuItem(
                        value: user.id, child: Text(user.fullName)),
                ],
                onChanged: (value) => setState(() => _auditeeId = value),
              ),
              const SizedBox(height: 9),
              const _FormLabel('Reference Standard'),
              const SizedBox(height: 4),
              _DropdownField<String>(
                value: _standard,
                items: const [
                  DropdownMenuItem(
                    value: 'ISO9001-9.2',
                    child: Text('ISO 9001'),
                  ),
                  DropdownMenuItem(
                    value: 'ARTA-3days',
                    child: Text('ARTA - 3 days'),
                  ),
                  DropdownMenuItem(
                    value: 'ARTA-7days',
                    child: Text('ARTA - 7 days'),
                  ),
                  DropdownMenuItem(
                    value: 'ARTA-20days',
                    child: Text('ARTA - 20 days'),
                  ),
                ],
                onChanged: (value) => setState(() {
                  _standard = value ?? _standard;
                  _selectedArchiveIds.clear();
                }),
              ),
              const SizedBox(height: 9),
              const _FormLabel('Result'),
              Row(
                children: [
                  _ResultOption(
                    value: 'compliant',
                    selected: _result,
                    label: 'Compliant',
                    onChanged: (value) => setState(() => _result = value),
                  ),
                  const SizedBox(width: 12),
                  _ResultOption(
                    value: 'non_compliant',
                    selected: _result,
                    label: 'Non-Compliant',
                    onChanged: (value) => setState(() => _result = value),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              const _FormLabel('Remarks'),
              const SizedBox(height: 4),
              TextField(
                controller: _remarksController,
                minLines: 2,
                maxLines: 3,
                decoration: const InputDecoration(
                  hintText: 'Finding in notes',
                  hintStyle:
                      TextStyle(fontSize: 11, color: AppColors.ustpMuted),
                  contentPadding: EdgeInsets.all(9),
                  filled: true,
                  fillColor: AppColors.ustpWhite,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.all(Radius.circular(8)),
                    borderSide: BorderSide(color: Color(0xFFE2E8F0)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.all(Radius.circular(8)),
                    borderSide: BorderSide(color: Color(0xFFE2E8F0)),
                  ),
                ),
              ),
              const SizedBox(height: 9),
              const _FormLabel('Link - Evidence (Multi-Select)'),
              const SizedBox(height: 4),
              if (eligibleArchives.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(9),
                  decoration: BoxDecoration(
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    borderRadius: BorderRadius.circular(7),
                  ),
                  child: const Text(
                    'No evidence matches this department and standard.',
                    style: TextStyle(fontSize: 10, color: AppColors.ustpMuted),
                  ),
                )
              else
                for (final archive in eligibleArchives)
                  _EvidenceOption(
                    archive: archive,
                    selected: _selectedArchiveIds.contains(archive.id),
                    onChanged: (selected) => setState(() {
                      if (selected) {
                        _selectedArchiveIds.add(archive.id);
                      } else {
                        _selectedArchiveIds.remove(archive.id);
                      }
                    }),
                  ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: _saving ? null : () => context.pop(),
                    style: TextButton.styleFrom(
                      backgroundColor: AppColors.ustpLight,
                      foregroundColor: AppColors.ustpDark,
                    ),
                    child: const Text('Cancel'),
                  ),
                  const SizedBox(width: 7),
                  FilledButton(
                    onPressed: _saving ? null : _submit,
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.ustpNavy,
                      foregroundColor: AppColors.ustpWhite,
                    ),
                    child: _saving
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.ustpWhite,
                            ),
                          )
                        : const Text('Save Check'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DropdownField<T> extends StatelessWidget {
  const _DropdownField({
    required this.value,
    required this.items,
    required this.onChanged,
    this.hint,
  });

  final T? value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;
  final String? hint;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<T>(
      // Keep `value` for compatibility with Flutter 3.24.
      // ignore: deprecated_member_use
      value: value,
      isExpanded: true,
      hint: hint == null ? null : Text(hint!),
      decoration: InputDecoration(
        contentPadding: const EdgeInsets.symmetric(horizontal: 9, vertical: 1),
        filled: true,
        fillColor: AppColors.ustpWhite,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(7),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(7),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
      ),
      style: const TextStyle(fontSize: 10, color: AppColors.ustpDark),
      items: items,
      onChanged: onChanged,
    );
  }
}

class _ResultOption extends StatelessWidget {
  const _ResultOption({
    required this.value,
    required this.selected,
    required this.label,
    required this.onChanged,
  });

  final String value;
  final String selected;
  final String label;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => onChanged(value),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 15,
            height: 15,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color:
                    selected == value ? AppColors.success : AppColors.ustpMuted,
                width: 1,
              ),
            ),
            child: selected == value
                ? Center(
                    child: Container(
                      width: 7,
                      height: 7,
                      decoration: const BoxDecoration(
                        color: AppColors.success,
                        shape: BoxShape.circle,
                      ),
                    ),
                  )
                : null,
          ),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(fontSize: 9)),
        ],
      ),
    );
  }
}

class _EvidenceOption extends StatelessWidget {
  const _EvidenceOption({
    required this.archive,
    required this.selected,
    required this.onChanged,
  });

  final ArchiveDocumentModel archive;
  final bool selected;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 4),
      decoration: BoxDecoration(
        border: Border.all(color: const Color(0xFFE2E8F0)),
        borderRadius: BorderRadius.circular(6),
      ),
      child: CheckboxListTile(
        dense: true,
        visualDensity: VisualDensity.compact,
        contentPadding: const EdgeInsets.symmetric(horizontal: 4),
        controlAffinity: ListTileControlAffinity.leading,
        value: selected,
        onChanged: (value) => onChanged(value ?? false),
        title: Text(
          archive.originalName,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 9),
        ),
      ),
    );
  }
}

class _FormLabel extends StatelessWidget {
  const _FormLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 10,
        fontWeight: FontWeight.w600,
        color: AppColors.ustpDark,
      ),
    );
  }
}

class _OptionsError extends StatelessWidget {
  const _OptionsError({
    required this.error,
    required this.onRetry,
    required this.onClose,
  });

  final Object error;
  final VoidCallback onRetry;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Could not load check options.\n$error',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(onPressed: onClose, child: const Text('Close')),
                FilledButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(AppIcons.refresh),
                  label: const Text('Retry'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
