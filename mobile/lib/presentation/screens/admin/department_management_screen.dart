import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/department_model.dart';
import '../../../data/repositories/department_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';
import '../admin/admin_notifications_screen.dart';

class DepartmentManagementScreen extends StatefulWidget {
  const DepartmentManagementScreen({super.key});

  @override
  State<DepartmentManagementScreen> createState() =>
      _DepartmentManagementScreenState();
}

class _DepartmentManagementScreenState
    extends State<DepartmentManagementScreen> {
  final _departmentsRepository = DepartmentRepository();
  bool _isLoading = true;
  Object? _error;
  List<DepartmentModel> _departments = [];

  @override
  void initState() {
    super.initState();
    _loadDepartments();
  }

  Future<void> _loadDepartments() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final results = await _departmentsRepository.getDepartments();
      if (!mounted) return;
      setState(() {
        _departments = results;
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

  Future<void> _showDepartmentForm([DepartmentModel? department]) async {
    final isNew = department == null;
    final codeController =
        TextEditingController(text: department?.departmentCode ?? '');
    final nameController =
        TextEditingController(text: department?.departmentName ?? '');
    final collegeController = TextEditingController(
        text: department?.collegeName ?? 'College of Engineering and Technology');
    final campusController =
        TextEditingController(text: department?.campusName ?? 'CET Claveria');
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
                      isNew ? 'Create Department' : 'Edit Department',
                      style: const TextStyle(
                        color: Color(0xFF0F172A),
                        fontFamily: 'Poppins',
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (isNew) ...[
                      _FormField(
                        controller: codeController,
                        label: 'Department code (e.g., CET-IT)',
                      ),
                      const SizedBox(height: 12),
                    ],
                    _FormField(
                      controller: nameController,
                      label: 'Department name',
                    ),
                    const SizedBox(height: 12),
                    _FormField(
                      controller: collegeController,
                      label: 'College name',
                    ),
                    const SizedBox(height: 12),
                    _FormField(
                      controller: campusController,
                      label: 'Campus name',
                    ),
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
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFFBBF24),
                          foregroundColor: const Color(0xFF0F172A),
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
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
                                    );
                                  }
                                  if (sheetContext.mounted) {
                                    Navigator.pop(sheetContext, true);
                                  }
                                } catch (error) {
                                  setSheetState(() {
                                    isSaving = false;
                                    formError =
                                        'Could not save department: $error';
                                  });
                                }
                              },
                        child: Text(
                          isSaving ? 'Saving…' : 'Save Department',
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontWeight: FontWeight.bold,
                            fontSize: 15,
                          ),
                        ),
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
        SnackBar(
            content:
                Text(isNew ? 'Department created.' : 'Department updated.')),
      );
      await _loadDepartments();
    }
  }

  Future<void> _deleteDepartment(DepartmentModel department) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Delete Department'),
        content: Text(
            'Are you sure you want to delete ${department.departmentName}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFEF4444),
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _departmentsRepository.deleteDepartment(department.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Department deleted.')),
        );
        await _loadDepartments();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not delete department: $error')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: UstpAppBar(
        onNotificationTap: () => AdminNotificationsScreen.showOverlay(context),
      ),
      body: Column(
        children: [
          // Header Controls Row: Campus Dropdown + Add Dept CTA
          Container(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
            color: const Color(0xFFF8F9FA),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Campus / Scope Dropdown Badge
                Container(
                  height: 42,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'CET Claveria',
                        style: TextStyle(
                          fontFamily: 'Inter',
                          fontSize: 14,
                          fontWeight: FontWeight.w500,
                          color: Color(0xFF1E293B),
                        ),
                      ),
                      SizedBox(width: 8),
                      Icon(
                        Icons.arrow_downward,
                        size: 14,
                        color: Color(0xFF64748B),
                      ),
                    ],
                  ),
                ),

                // Add Dept CTA Button
                Material(
                  color: const Color(0xFFFBBF24),
                  borderRadius: BorderRadius.circular(10),
                  child: InkWell(
                    onTap: () => _showDepartmentForm(),
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      height: 42,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 8),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.add,
                            size: 18,
                            color: Color(0xFF0F172A),
                          ),
                          SizedBox(width: 6),
                          Text(
                            'Add Dept',
                            style: TextStyle(
                              fontFamily: 'Inter',
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Departments List
          Expanded(
            child: _isLoading
                ? const Center(
                    child:
                        CircularProgressIndicator(color: AppColors.ustpNavy),
                  )
                : _error != null
                    ? _LoadError(error: _error!, onRetry: _loadDepartments)
                    : _departments.isEmpty
                        ? RefreshIndicator(
                            color: AppColors.ustpNavy,
                            onRefresh: _loadDepartments,
                            child: ListView(
                              physics:
                                  const AlwaysScrollableScrollPhysics(),
                              children: const [
                                SizedBox(height: 80),
                                EmptyState(
                                  title: 'No departments found',
                                  subtitle:
                                      'No departments are available in CET Claveria.',
                                  icon: AppIcons.department,
                                ),
                              ],
                            ),
                          )
                        : RefreshIndicator(
                            color: AppColors.ustpNavy,
                            onRefresh: _loadDepartments,
                            child: ListView.builder(
                              physics:
                                  const AlwaysScrollableScrollPhysics(),
                              padding:
                                  const EdgeInsets.fromLTRB(16, 0, 16, 16),
                              itemCount: _departments.length,
                              itemBuilder: (context, index) {
                                final department = _departments[index];
                                return _DepartmentCard(
                                  department: department,
                                  onEdit: () =>
                                      _showDepartmentForm(department),
                                  onDelete: () =>
                                      _deleteDepartment(department),
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

class _DepartmentCard extends StatelessWidget {
  const _DepartmentCard({
    required this.department,
    required this.onEdit,
    required this.onDelete,
  });

  final DepartmentModel department;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: Code Badge (Left) & Campus (Right)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  department.departmentCode,
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 12.5,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ),
              Text(
                department.campusName,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 13,
                  color: Color(0xFF64748B),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Department Name
          Text(
            department.departmentName,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 17,
              fontWeight: FontWeight.bold,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 4),

          // College Name
          Text(
            department.collegeName,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 13.5,
              color: Color(0xFF64748B),
            ),
          ),

          // Divider Line
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Divider(
              height: 1,
              thickness: 1,
              color: Color(0xFFF1F5F9),
            ),
          ),

          // Bottom Actions Row (Right aligned: Edit · Delete)
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              GestureDetector(
                onTap: onEdit,
                child: const Text(
                  'Edit',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF2563EB),
                  ),
                ),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 8),
                child: Text(
                  '·',
                  style: TextStyle(
                    color: Color(0xFF94A3B8),
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              GestureDetector(
                onTap: onDelete,
                child: const Text(
                  'Delete',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFFEF4444),
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

class _FormField extends StatelessWidget {
  const _FormField({
    required this.controller,
    required this.label,
  });

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

class _LoadError extends StatelessWidget {
  const _LoadError({required this.error, required this.onRetry});

  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TopErrorDisplay(error: error, onRetry: onRetry),
            ],
          ),
        ),
      );
}

class TopErrorDisplay extends StatelessWidget {
  const TopErrorDisplay({required this.error, required this.onRetry});
  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            'Could not load departments.\n$error',
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFF64748B)),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(AppIcons.refresh, size: 16),
            label: const Text('Retry'),
          ),
        ],
      );
}
