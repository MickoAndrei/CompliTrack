import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/department_model.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/department_repository.dart';
import '../../../data/repositories/user_repository.dart';
import '../../widgets/admin_page_header.dart';
import '../../widgets/empty_state.dart';

class DepartmentManagementScreen extends StatefulWidget {
  const DepartmentManagementScreen({super.key});

  @override
  State<DepartmentManagementScreen> createState() =>
      _DepartmentManagementScreenState();
}

class _DepartmentManagementScreenState
    extends State<DepartmentManagementScreen> {
  final _departmentsRepository = DepartmentRepository();
  final _usersRepository = UserRepository();
  bool _isLoading = true;
  Object? _error;
  List<DepartmentModel> _departments = [];
  List<UserModel> _users = [];

  @override
  void initState() {
    super.initState();
    _loadOffices();
  }

  Future<void> _loadOffices() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        _departmentsRepository.getDepartments(campus: 'CET Claveria'),
        _usersRepository.getUsers(perPage: 100),
      ]);
      if (!mounted) return;
      setState(() {
        _departments = results[0] as List<DepartmentModel>;
        _users = results[1] as List<UserModel>;
        _isLoading = false;
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error;
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _showOfficeForm([DepartmentModel? department]) async {
    final isNew = department == null;
    final codeController =
        TextEditingController(text: department?.departmentCode ?? '');
    final nameController =
        TextEditingController(text: department?.departmentName ?? '');
    final collegeController =
        TextEditingController(text: department?.collegeName ?? 'CET');
    final campusController =
        TextEditingController(text: department?.campusName ?? 'CET Claveria');
    final assignedUsers = isNew
        ? <UserModel>[]
        : _users
            .where((user) =>
                user.departmentId == department.id &&
                (user.roleId == 2 || user.roleId == 3))
            .toList();
    var headUserId = department?.headUserId;
    var isSaving = false;
    String? formError;
    final formKey = GlobalKey<FormState>();

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.ustpWhite,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              MediaQuery.viewInsetsOf(context).bottom + 20,
            ),
            child: Form(
              key: formKey,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isNew ? 'Create Office' : 'Modify Office',
                      style: const TextStyle(
                        color: AppColors.ustpNavy,
                        fontFamily: 'Poppins',
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 14),
                    if (isNew) ...[
                      _OfficeFormField(
                        controller: codeController,
                        label: 'Department code',
                      ),
                      const SizedBox(height: 10),
                    ],
                    _OfficeFormField(
                      controller: nameController,
                      label: 'Office name',
                    ),
                    const SizedBox(height: 10),
                    _OfficeFormField(
                      controller: collegeController,
                      label: 'College',
                    ),
                    const SizedBox(height: 10),
                    _OfficeFormField(
                      controller: campusController,
                      label: 'Campus',
                    ),
                    if (!isNew) ...[
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String?>(
                        // Keep `value` for compatibility with Flutter 3.24.
                        // ignore: deprecated_member_use
                        value:
                            assignedUsers.any((user) => user.id == headUserId)
                                ? headUserId
                                : null,
                        decoration: const InputDecoration(
                          labelText: 'Office head',
                        ),
                        items: [
                          const DropdownMenuItem<String?>(
                            value: null,
                            child: Text('Unassigned'),
                          ),
                          for (final user in assignedUsers)
                            DropdownMenuItem(
                              value: user.id,
                              child: Text(user.fullName),
                            ),
                        ],
                        onChanged: (value) =>
                            setSheetState(() => headUserId = value),
                      ),
                    ],
                    if (formError != null) ...[
                      const SizedBox(height: 10),
                      Text(
                        formError!,
                        style: const TextStyle(
                          color: AppColors.error,
                          fontSize: 12,
                        ),
                      ),
                    ],
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.ustpGold,
                          foregroundColor: AppColors.ustpDark,
                        ),
                        onPressed: isSaving
                            ? null
                            : () async {
                                if (!(formKey.currentState?.validate() ??
                                    false)) {
                                  return;
                                }
                                setSheetState(() {
                                  isSaving = true;
                                  formError = null;
                                });
                                try {
                                  if (isNew) {
                                    await _departmentsRepository
                                        .createDepartment(
                                      departmentCode:
                                          codeController.text.trim(),
                                      departmentName:
                                          nameController.text.trim(),
                                      collegeName:
                                          collegeController.text.trim(),
                                      campusName: campusController.text.trim(),
                                    );
                                  } else {
                                    await _departmentsRepository
                                        .updateDepartment(
                                      department.id,
                                      departmentName:
                                          nameController.text.trim(),
                                      collegeName:
                                          collegeController.text.trim(),
                                      campusName: campusController.text.trim(),
                                      headUserId: headUserId,
                                      clearHead: headUserId == null,
                                    );
                                  }
                                  if (sheetContext.mounted) {
                                    Navigator.pop(sheetContext, true);
                                  }
                                } catch (error) {
                                  setSheetState(() {
                                    isSaving = false;
                                    formError = 'Could not save office: $error';
                                  });
                                }
                              },
                        child: Text(isSaving ? 'Saving…' : 'Save Office'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );

    codeController.dispose();
    nameController.dispose();
    collegeController.dispose();
    campusController.dispose();
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(isNew ? 'Office created.' : 'Office updated.')),
      );
      await _loadOffices();
    }
  }

  Future<void> _revokeHead(DepartmentModel department) async {
    final head =
        _users.where((user) => user.id == department.headUserId).firstOrNull;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Revoke office head?'),
        content: Text(
          head == null
              ? 'Remove the assigned office head for ${department.departmentName}?'
              : 'Remove ${head.fullName} as head of ${department.departmentName}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Revoke'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _departmentsRepository.updateDepartment(
        department.id,
        clearHead: true,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Office head assignment removed.')),
        );
        await _loadOffices();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not revoke office head: $error')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      body: Column(
        children: [
          const AdminPageHeader(
            title: 'Department Offices',
            subtitle: 'Manage specific academic facilities and coordinators',
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 2),
            child: Row(
              children: [
                Expanded(
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
                    decoration: BoxDecoration(
                      color: AppColors.ustpWhite,
                      border: Border.all(color: AppColors.info),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Row(
                      children: [
                        Icon(Icons.circle, size: 7, color: AppColors.info),
                        SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            'Scope: CET Claveria only',
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: AppColors.info,
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                ElevatedButton.icon(
                  onPressed: () => _showOfficeForm(),
                  icon: const Icon(AppIcons.plus, size: 15),
                  label: const Text('Create Office'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.ustpGold,
                    foregroundColor: AppColors.ustpDark,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 12,
                    ),
                    textStyle: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(
                    child: CircularProgressIndicator(
                      color: AppColors.ustpNavy,
                    ),
                  )
                : _error != null
                    ? _OfficeLoadError(
                        error: _error!,
                        onRetry: _loadOffices,
                      )
                    : _departments.isEmpty
                        ? RefreshIndicator(
                            onRefresh: _loadOffices,
                            child: ListView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              children: const [
                                SizedBox(height: 80),
                                EmptyState(
                                  title: 'No offices found',
                                  subtitle:
                                      'No departments are available in CET Claveria.',
                                  icon: AppIcons.department,
                                ),
                              ],
                            ),
                          )
                        : RefreshIndicator(
                            color: AppColors.ustpNavy,
                            onRefresh: _loadOffices,
                            child: ListView.builder(
                              physics: const AlwaysScrollableScrollPhysics(),
                              padding: const EdgeInsets.fromLTRB(12, 8, 12, 16),
                              itemCount: _departments.length,
                              itemBuilder: (context, index) {
                                final department = _departments[index];
                                final head = _users
                                    .where((user) =>
                                        user.id == department.headUserId)
                                    .firstOrNull;
                                return _OfficeCard(
                                  department: department,
                                  headName: head?.fullName,
                                  onModify: () => _showOfficeForm(department),
                                  onRevoke: department.headUserId == null
                                      ? null
                                      : () => _revokeHead(department),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

class _OfficeCard extends StatelessWidget {
  const _OfficeCard({
    required this.department,
    required this.headName,
    required this.onModify,
    required this.onRevoke,
  });

  final DepartmentModel department;
  final String? headName;
  final VoidCallback onModify;
  final VoidCallback? onRevoke;

  @override
  Widget build(BuildContext context) {
    final initials = headName == null || headName!.isEmpty
        ? '--'
        : headName!
            .trim()
            .split(RegExp(r'\s+'))
            .map((part) => part[0])
            .take(2)
            .join()
            .toUpperCase();
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(12, 11, 12, 8),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        border: Border.all(color: const Color(0xFFE5EAF1)),
        borderRadius: BorderRadius.circular(11),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            department.departmentName,
            style: const TextStyle(
              color: AppColors.ustpDark,
              fontFamily: 'Poppins',
              fontSize: 12,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            department.collegeName,
            style: const TextStyle(color: AppColors.ustpMuted, fontSize: 10),
          ),
          const SizedBox(height: 9),
          Row(
            children: [
              CircleAvatar(
                radius: 11,
                backgroundColor: AppColors.ustpNavy,
                child: Text(
                  initials,
                  style: const TextStyle(
                    color: AppColors.ustpGold,
                    fontSize: 7,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'OFFICE HEAD ASSIGNED',
                    style: TextStyle(
                      color: AppColors.ustpMuted,
                      fontSize: 8,
                      letterSpacing: 0.3,
                    ),
                  ),
                  Text(
                    headName ?? 'Not assigned',
                    style: const TextStyle(
                      color: AppColors.ustpDark,
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const Divider(height: 15, color: Color(0xFFE8EDF4)),
          Row(
            children: [
              TextButton(
                onPressed: onModify,
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.only(right: 10),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: const Text(
                  'Modify Office',
                  style: TextStyle(color: AppColors.info, fontSize: 9),
                ),
              ),
              Container(height: 12, width: 1, color: const Color(0xFFCBD5E1)),
              TextButton(
                onPressed: onRevoke,
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.only(left: 10),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: Text(
                  'Revoke Head',
                  style: TextStyle(
                    color: onRevoke == null
                        ? AppColors.ustpMuted
                        : AppColors.error,
                    fontSize: 9,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _OfficeFormField extends StatelessWidget {
  const _OfficeFormField({required this.controller, required this.label});

  final TextEditingController controller;
  final String label;

  @override
  Widget build(BuildContext context) => TextFormField(
        controller: controller,
        validator: (value) =>
            value == null || value.trim().isEmpty ? 'Required.' : null,
        decoration: InputDecoration(labelText: label),
      );
}

class _OfficeLoadError extends StatelessWidget {
  const _OfficeLoadError({required this.error, required this.onRetry});

  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Could not load offices.\n$error',
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.ustpMuted),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(AppIcons.refresh, size: 16),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
}
