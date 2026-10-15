import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/department_model.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/department_repository.dart';
import '../../../data/repositories/user_repository.dart';
import '../../widgets/admin_page_header.dart';
import '../../widgets/empty_state.dart';

class AdminUserManagementScreen extends StatefulWidget {
  const AdminUserManagementScreen({super.key});

  @override
  State<AdminUserManagementScreen> createState() =>
      _AdminUserManagementScreenState();
}

class _AdminUserManagementScreenState extends State<AdminUserManagementScreen> {
  final _repository = UserRepository();
  final _searchController = TextEditingController();
  late Future<List<UserModel>> _users;
  int? _roleFilter;

  @override
  void initState() {
    super.initState();
    _users = _loadUsers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<UserModel>> _loadUsers() => _repository.getUsers(perPage: 100);

  Future<void> _refresh() async {
    setState(() => _users = _loadUsers());
    await _users;
  }

  Future<void> _showUserForm([UserModel? user]) async {
    late final List<DepartmentModel> departments;
    try {
      departments =
          await DepartmentRepository().getDepartments(campus: 'CET Claveria');
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not load offices: $error')),
        );
      }
      return;
    }
    if (!mounted) return;

    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController(text: user?.fullName ?? '');
    final emailController = TextEditingController(text: user?.email ?? '');
    final officeController =
        TextEditingController(text: user?.officeName ?? '');
    var selectedRole = user?.roleId ?? 3;
    var selectedDepartment = user?.departmentId;
    var isActive = user?.isActive ?? true;
    var isSaving = false;
    String? formError;

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
                      user == null ? 'Create New Account' : 'Edit User',
                      style: const TextStyle(
                        color: AppColors.ustpNavy,
                        fontFamily: 'Poppins',
                        fontWeight: FontWeight.bold,
                        fontSize: 18,
                      ),
                    ),
                    const SizedBox(height: 16),
                    _FormField(
                      controller: nameController,
                      label: 'Full name',
                      validator: _requiredValidator,
                    ),
                    const SizedBox(height: 10),
                    _FormField(
                      controller: emailController,
                      label: 'Email',
                      keyboardType: TextInputType.emailAddress,
                      validator: (value) {
                        final requiredError = _requiredValidator(value);
                        if (requiredError != null) return requiredError;
                        if (!value!.contains('@') || !value.contains('.')) {
                          return 'Enter a valid email address.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 10),
                    if (user == null)
                      DropdownButtonFormField<int>(
                        // Keep `value` for compatibility with Flutter 3.24.
                        // ignore: deprecated_member_use
                        value: selectedRole,
                        decoration: const InputDecoration(labelText: 'Role'),
                        items: const [
                          DropdownMenuItem(value: 2, child: Text('Auditor')),
                          DropdownMenuItem(value: 3, child: Text('Auditee')),
                        ],
                        onChanged: (value) {
                          if (value != null) {
                            setSheetState(() => selectedRole = value);
                          }
                        },
                      )
                    else
                      InputDecorator(
                        decoration: const InputDecoration(labelText: 'Role'),
                        child: Text(_roleName(user.roleId)),
                      ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      // Keep `value` for compatibility with Flutter 3.24.
                      // ignore: deprecated_member_use
                      value: departments.any((department) =>
                              department.id == selectedDepartment)
                          ? selectedDepartment
                          : null,
                      decoration:
                          const InputDecoration(labelText: 'Department'),
                      items: [
                        for (final department in departments)
                          DropdownMenuItem(
                            value: department.id,
                            child: Text(
                              department.departmentName,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      validator: (value) =>
                          value == null ? 'Choose a department.' : null,
                      onChanged: (value) =>
                          setSheetState(() => selectedDepartment = value),
                    ),
                    const SizedBox(height: 10),
                    if (user != null) ...[
                      DropdownButtonFormField<bool>(
                        // Keep `value` for compatibility with Flutter 3.24.
                        // ignore: deprecated_member_use
                        value: isActive,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const [
                          DropdownMenuItem(
                            value: true,
                            child: Text('Active'),
                          ),
                          DropdownMenuItem(
                            value: false,
                            child: Text('Inactive'),
                          ),
                        ],
                        onChanged: (value) {
                          if (value != null) {
                            setSheetState(() => isActive = value);
                          }
                        },
                      ),
                      const SizedBox(height: 10),
                    ],
                    _FormField(
                      controller: officeController,
                      label: 'Office name',
                      validator: (value) {
                        if (selectedRole == 3 &&
                            (value?.trim().isEmpty ?? true)) {
                          return 'An office name is required for auditees.';
                        }
                        return null;
                      },
                    ),
                    if (user == null) ...[
                      const SizedBox(height: 8),
                      const Text(
                        'An account setup email will be sent to the new user.',
                        style: TextStyle(
                          color: AppColors.ustpMuted,
                          fontSize: 10,
                        ),
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
                                  if (user == null) {
                                    await _repository.createUser(
                                      fullName: nameController.text.trim(),
                                      email: emailController.text.trim(),
                                      roleId: selectedRole,
                                      departmentId: selectedDepartment!,
                                      officeName: officeController.text.trim(),
                                    );
                                  } else {
                                    await _repository.updateUser(
                                      user.id,
                                      fullName: nameController.text.trim(),
                                      email: emailController.text.trim(),
                                      departmentId: selectedDepartment!,
                                      officeName: officeController.text.trim(),
                                      isActive: isActive,
                                    );
                                  }
                                  if (sheetContext.mounted) {
                                    Navigator.pop(sheetContext, true);
                                  }
                                } catch (error) {
                                  setSheetState(() {
                                    isSaving = false;
                                    formError = 'Could not save user: $error';
                                  });
                                }
                              },
                        child: Text(isSaving ? 'Saving…' : 'Save User'),
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

    nameController.dispose();
    emailController.dispose();
    officeController.dispose();
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(user == null ? 'User created.' : 'User updated.'),
        ),
      );
      await _refresh();
    }
  }

  String? _requiredValidator(String? value) =>
      value == null || value.trim().isEmpty ? 'This field is required.' : null;

  Future<void> _deactivate(UserModel user) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Deactivate user?'),
        content: Text('${user.fullName} will no longer be able to sign in.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Deactivate'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _repository.deactivateUser(user.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('User deactivated.')),
        );
        await _refresh();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not deactivate user: $error')),
        );
      }
    }
  }

  Future<void> _resetPassword(UserModel user) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Reset Password'),
        content: Text(
          'A new password reset link will be sent to ${user.email}. They will need to set a new password before signing in.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.ustpGold,
              foregroundColor: AppColors.ustpDark,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm Reset'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _repository.resetPassword(user.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text('Password reset email sent to ${user.email}.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not reset password: $error')),
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
            title: 'User Administration',
            subtitle: 'Configure and deploy campus Auditor & Auditee nodes',
          ),
          Container(
            margin: const EdgeInsets.fromLTRB(12, 10, 12, 0),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFEAF3FF),
              border: const Border(
                left: BorderSide(color: AppColors.info, width: 3),
              ),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(AppIcons.open, color: AppColors.info, size: 15),
                SizedBox(width: 7),
                Expanded(
                  child: Text(
                    'Scope: Auditor & Auditee accounts only — Super Admin creation is not permitted here.',
                    style: TextStyle(
                      color: AppColors.info,
                      fontSize: 10,
                      height: 1.3,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
            child: Column(
              children: [
                TextField(
                  controller: _searchController,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: 'Search user or email...',
                    prefixIcon: const Icon(AppIcons.search, size: 17),
                    filled: true,
                    fillColor: AppColors.ustpWhite,
                    contentPadding: const EdgeInsets.symmetric(vertical: 10),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(9),
                      borderSide: const BorderSide(color: Color(0xFFE4EAF1)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(9),
                      borderSide: const BorderSide(color: Color(0xFFE4EAF1)),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<int?>(
                        // Keep `value` for compatibility with Flutter 3.24.
                        // ignore: deprecated_member_use
                        value: _roleFilter,
                        decoration: InputDecoration(
                          filled: true,
                          fillColor: AppColors.ustpWhite,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 9,
                          ),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(9),
                            borderSide:
                                const BorderSide(color: Color(0xFFE4EAF1)),
                          ),
                        ),
                        items: const [
                          DropdownMenuItem<int?>(
                            value: null,
                            child: Text('Filter by Role'),
                          ),
                          DropdownMenuItem<int?>(
                            value: 2,
                            child: Text('Auditor'),
                          ),
                          DropdownMenuItem<int?>(
                            value: 3,
                            child: Text('Auditee'),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _roleFilter = value),
                      ),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton.icon(
                      onPressed: () => _showUserForm(),
                      icon: const Icon(AppIcons.plus, size: 16),
                      label: const Text('Create User'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.ustpGold,
                        foregroundColor: AppColors.ustpDark,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 14,
                        ),
                        textStyle: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder<List<UserModel>>(
              future: _users,
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return _LoadError(error: snapshot.error!, onRetry: _refresh);
                }
                if (!snapshot.hasData) {
                  return const Center(
                    child: CircularProgressIndicator(
                      color: AppColors.ustpNavy,
                    ),
                  );
                }
                final search = _searchController.text.trim().toLowerCase();
                final users = snapshot.data!.where((user) {
                  final roleMatches =
                      _roleFilter == null || user.roleId == _roleFilter;
                  final searchMatches = search.isEmpty ||
                      user.fullName.toLowerCase().contains(search) ||
                      user.email.toLowerCase().contains(search) ||
                      user.userCode.toLowerCase().contains(search);
                  return roleMatches && searchMatches;
                }).toList();
                if (users.isEmpty) {
                  return RefreshIndicator(
                    onRefresh: _refresh,
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 80),
                        EmptyState(
                          title: 'No users found',
                          subtitle: 'Try changing your search or role filter.',
                        ),
                      ],
                    ),
                  );
                }
                return RefreshIndicator(
                  color: AppColors.ustpNavy,
                  onRefresh: _refresh,
                  child: ListView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
                    itemCount: users.length,
                    itemBuilder: (context, index) => _UserCard(
                      user: users[index],
                      onEdit: () => _showUserForm(users[index]),
                      onDeactivate: () => _deactivate(users[index]),
                      onReset: () => _resetPassword(users[index]),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _UserCard extends StatelessWidget {
  const _UserCard({
    required this.user,
    required this.onEdit,
    required this.onDeactivate,
    required this.onReset,
  });

  final UserModel user;
  final VoidCallback onEdit;
  final VoidCallback onDeactivate;
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    final active = user.isActive;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(11, 10, 11, 8),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        border: Border.all(color: const Color(0xFFE5EAF1)),
        borderRadius: BorderRadius.circular(11),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  user.userCode,
                  style: const TextStyle(
                    color: AppColors.ustpNavy,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              _Badge(
                label: active ? 'Active' : 'Inactive',
                color: active ? AppColors.success : AppColors.ustpMuted,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            user.fullName,
            style: const TextStyle(
              color: AppColors.ustpDark,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            user.email,
            style: const TextStyle(color: AppColors.ustpMuted, fontSize: 10),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              _RoleBadge(label: _roleName(user.roleId), roleId: user.roleId),
              const SizedBox(width: 7),
              const Icon(Icons.circle, size: 3, color: AppColors.ustpMuted),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  user.departmentName ??
                      (user.officeName.isNotEmpty
                          ? user.officeName
                          : 'Department assigned'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style:
                      const TextStyle(color: AppColors.ustpMuted, fontSize: 10),
                ),
              ),
            ],
          ),
          const Divider(height: 14, color: Color(0xFFE8EDF4)),
          Row(
            children: [
              _TextAction(
                  label: 'Edit', color: AppColors.info, onPressed: onEdit),
              const _ActionDivider(),
              _TextAction(
                label: active ? 'Deactivate' : 'Inactive',
                color: AppColors.error,
                onPressed: active ? onDeactivate : null,
              ),
              const _ActionDivider(),
              _TextAction(
                label: 'Reset',
                color: AppColors.ustpMuted,
                onPressed: onReset,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 8,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }
}

class _RoleBadge extends StatelessWidget {
  const _RoleBadge({required this.label, required this.roleId});

  final String label;
  final int roleId;

  @override
  Widget build(BuildContext context) {
    final color = roleId == 2 ? AppColors.ustpPurple : AppColors.ustpMuted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 8,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _TextAction extends StatelessWidget {
  const _TextAction({
    required this.label,
    required this.color,
    required this.onPressed,
  });

  final String label;
  final Color color;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      onPressed: onPressed,
      style: TextButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 6),
        minimumSize: Size.zero,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        foregroundColor: color,
      ),
      child: Text(label, style: const TextStyle(fontSize: 9)),
    );
  }
}

class _ActionDivider extends StatelessWidget {
  const _ActionDivider();

  @override
  Widget build(BuildContext context) =>
      Container(height: 12, width: 1, color: const Color(0xFFCBD5E1));
}

class _FormField extends StatelessWidget {
  const _FormField({
    required this.controller,
    required this.label,
    this.validator,
    this.keyboardType,
  });

  final TextEditingController controller;
  final String label;
  final String? Function(String?)? validator;
  final TextInputType? keyboardType;

  @override
  Widget build(BuildContext context) => TextFormField(
        controller: controller,
        validator: validator,
        keyboardType: keyboardType,
        decoration: InputDecoration(labelText: label),
      );
}

String _roleName(int roleId) => switch (roleId) {
      2 => 'Auditor',
      3 => 'Auditee',
      _ => 'Unknown',
    };

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
              Text(
                'Could not load users.\n$error',
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
