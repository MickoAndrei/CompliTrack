import 'dart:math' as math;
import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/department_model.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/user_repository.dart';

class SuperAdminUserEditForm extends StatefulWidget {
  const SuperAdminUserEditForm({
    super.key,
    required this.user,
    required this.departments,
    required this.onCancel,
    required this.onSaved,
  });

  final UserModel? user;
  final List<DepartmentModel> departments;
  final VoidCallback onCancel;
  final VoidCallback onSaved;

  @override
  State<SuperAdminUserEditForm> createState() => _SuperAdminUserEditFormState();
}

class _SuperAdminUserEditFormState extends State<SuperAdminUserEditForm> {
  final _formKey = GlobalKey<FormState>();
  final _stackKey = GlobalKey();
  final _roleFieldKey = GlobalKey();
  final _departmentFieldKey = GlobalKey();
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _officeController;
  late int _selectedRole;
  String? _selectedDepartment;
  late final TextEditingController _passwordController;
  bool _isSaving = false;
  String? _formError;
  List<_EditOption>? _openOptions;
  Object? _selectedOption;
  double _menuLeft = 16;
  double _menuTop = 0;
  double _menuWidth = 244;
  double _menuHeight = 0;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.user?.fullName);
    _emailController = TextEditingController(text: widget.user?.email);
    _officeController = TextEditingController(text: widget.user?.officeName);
    _passwordController = TextEditingController(text: _generatePassword());
    _selectedRole = widget.user?.roleId ?? 0;
    _selectedDepartment = widget.user?.departmentId ??
        (widget.user == null && widget.departments.isNotEmpty
            ? widget.departments.first.id
            : null);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _officeController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (!widget.departments
        .any((department) => department.id == _selectedDepartment)) {
      setState(() => _formError = 'Choose a department.');
      return;
    }

    setState(() {
      _isSaving = true;
      _formError = null;
    });
    try {
      if (widget.user == null) {
        await UserRepository().createUser(
          fullName: _nameController.text.trim(),
          email: _emailController.text.trim(),
          roleId: _selectedRole,
          departmentId: _selectedDepartment!,
          officeName: _officeController.text.trim(),
        );
      } else {
        await UserRepository().updateUser(
          widget.user!.id,
          fullName: _nameController.text.trim(),
          email: _emailController.text.trim(),
          roleId: _selectedRole,
          departmentId: _selectedDepartment,
          officeName: _officeController.text.trim(),
        );
      }
      if (mounted) widget.onSaved();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _isSaving = false;
        _formError = 'Could not save user: $error';
      });
    }
  }

  void _showOptions({
    required List<_EditOption> options,
    required Object selectedValue,
    required GlobalKey anchorKey,
    double maxWidth = 244,
  }) {
    final stackBox = _stackKey.currentContext!.findRenderObject()! as RenderBox;
    final anchorBox =
        anchorKey.currentContext!.findRenderObject()! as RenderBox;
    final anchor = stackBox.globalToLocal(
      anchorBox.localToGlobal(Offset.zero),
    );
    final height = math.min(options.length * 28.0, 240.0);
    final selectedIndex = options.indexWhere(
      (option) => option.value == selectedValue,
    );
    final selectedRow = selectedIndex < 0 ? 0 : selectedIndex;
    final top =
        (anchor.dy + anchorBox.size.height / 2 - (selectedRow * 28.0 + 14.0))
            .clamp(8.0, math.max(8.0, stackBox.size.height - height - 8.0))
            .toDouble();
    final width = math.min(maxWidth, stackBox.size.width - 32.0);
    final left = anchor.dx
        .clamp(8.0, math.max(8.0, stackBox.size.width - width - 8.0))
        .toDouble();

    setState(() {
      _openOptions = options;
      _selectedOption = selectedValue;
      _menuLeft = left;
      _menuTop = top;
      _menuWidth = width;
      _menuHeight = height;
    });
  }

  void _showDepartments() {
    final options = _departmentOptions;
    if (options.isEmpty) {
      setState(() => _formError = 'No departments are available.');
      return;
    }
    _showOptions(
      options: options,
      selectedValue: _selectedDepartment ?? options.first.value,
      anchorKey: _departmentFieldKey,
    );
  }

  String _roleName(int roleId) => switch (roleId) {
        0 => 'Super Admin',
        1 => 'Admin',
        2 => 'Auditor',
        3 => 'Auditee',
        _ => 'Unknown role',
      };

  List<_EditOption> get _roleOptions {
    final roles = widget.user?.roleId == 0 ? [0] : [0, 1, 2, 3];
    return [
      for (final role in roles)
        _EditOption(value: role, label: '$role - ${_roleName(role)}'),
    ];
  }

  List<_EditOption> get _departmentOptions => [
        for (final department in widget.departments)
          _EditOption(
            value: department.id,
            label: _departmentLabel(department),
          ),
      ];

  String _generatePassword() {
    const characters =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    final random = math.Random.secure();
    final suffix = List.generate(
      8,
      (_) => characters[random.nextInt(characters.length)],
    ).join();
    return 'uSTp_Cl@v_${DateTime.now().year}_$suffix';
  }

  String _departmentLabel(DepartmentModel department) =>
      department.collegeName == 'CET'
          ? 'CET - ${department.departmentName}'
          : department.departmentName;

  String? get _selectedDepartmentName {
    for (final department in widget.departments) {
      if (department.id == _selectedDepartment) {
        return _departmentLabel(department);
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) => Stack(
        key: _stackKey,
        fit: StackFit.expand,
        children: [
          Column(
            children: [
              Container(
                height: 56,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                color: AppColors.ustpWhite,
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        widget.user == null
                            ? 'Create New Account'
                            : 'Edit User',
                        style: TextStyle(
                          color: AppColors.ustpDark,
                          fontFamily: 'Poppins',
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: widget.onCancel,
                      padding: EdgeInsets.zero,
                      visualDensity: VisualDensity.compact,
                      constraints:
                          const BoxConstraints.tightFor(width: 32, height: 32),
                      icon: const Icon(
                        Icons.cancel_outlined,
                        color: AppColors.ustpMuted,
                        size: 19,
                      ),
                    ),
                  ],
                ),
              ),
              Flexible(
                fit: FlexFit.loose,
                child: Container(
                  color: AppColors.ustpLight,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _EditTextField(
                            controller: _nameController,
                            label: 'Full Name',
                            validator: _requiredValidator,
                          ),
                          const SizedBox(height: 14),
                          _EditTextField(
                            controller: _emailController,
                            label: 'Email Address',
                            keyboardType: TextInputType.emailAddress,
                            validator: (value) {
                              final required = _requiredValidator(value);
                              if (required != null) return required;
                              if (!value!.contains('@') ||
                                  !value.contains('.')) {
                                return 'Enter a valid email address.';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 14),
                          _EditSelectionField(
                            key: _roleFieldKey,
                            label: 'Role',
                            value: _roleName(_selectedRole),
                            onTap: () => _showOptions(
                              options: _roleOptions,
                              selectedValue: _selectedRole,
                              anchorKey: _roleFieldKey,
                              maxWidth: 220,
                            ),
                          ),
                          const SizedBox(height: 14),
                          _EditSelectionField(
                            key: _departmentFieldKey,
                            label: 'Department',
                            value: _selectedDepartmentName,
                            placeholder: 'Select Department',
                            onTap: _showDepartments,
                          ),
                          const SizedBox(height: 14),
                          _EditTextField(
                            controller: _officeController,
                            label: 'Office Name',
                            validator: (value) {
                              if (_selectedRole == 3 &&
                                  (value?.trim().isEmpty ?? true)) {
                                return 'An office name is required for auditees.';
                              }
                              return null;
                            },
                          ),
                          if (widget.user == null) ...[
                            const SizedBox(height: 14),
                            _EditTextField(
                              controller: _passwordController,
                              label: 'Generated Password',
                              readOnly: true,
                              suffixIcon: IconButton(
                                onPressed: () {
                                  setState(() {
                                    _passwordController.text =
                                        _generatePassword();
                                  });
                                },
                                padding: EdgeInsets.zero,
                                visualDensity: VisualDensity.compact,
                                constraints: const BoxConstraints.tightFor(
                                  width: 36,
                                  height: 36,
                                ),
                                icon: const Icon(
                                  Icons.refresh_rounded,
                                  color: AppColors.ustpNavy,
                                  size: 19,
                                ),
                              ),
                            ),
                          ],
                          if (_formError != null) ...[
                            const SizedBox(height: 12),
                            Text(
                              _formError!,
                              style: const TextStyle(
                                color: AppColors.error,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              Container(
                height: 68,
                color: AppColors.ustpWhite,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(
                      onPressed: _isSaving ? null : widget.onCancel,
                      style: TextButton.styleFrom(
                        backgroundColor: AppColors.ustpLight,
                        foregroundColor: AppColors.ustpMuted,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: const Text('Cancel'),
                    ),
                    const SizedBox(width: 10),
                    ElevatedButton(
                      onPressed: _isSaving ? null : _save,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.ustpGold,
                        foregroundColor: AppColors.ustpDark,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: Text(
                        _isSaving
                            ? (widget.user == null
                                ? 'Creating...'
                                : 'Saving...')
                            : (widget.user == null
                                ? 'Create Account'
                                : 'Save Changes'),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (_openOptions != null)
            Positioned.fill(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => setState(() => _openOptions = null),
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: BackdropFilter(
                        filter: ImageFilter.blur(sigmaX: 3.5, sigmaY: 3.5),
                        child: ColoredBox(
                          color: Colors.black.withValues(alpha: 0.28),
                        ),
                      ),
                    ),
                    Positioned(
                      left: _menuLeft,
                      top: _menuTop,
                      width: _menuWidth,
                      child: Material(
                        color: AppColors.ustpWhite,
                        elevation: 8,
                        borderRadius: BorderRadius.circular(7),
                        clipBehavior: Clip.antiAlias,
                        child: ConstrainedBox(
                          constraints: BoxConstraints(maxHeight: _menuHeight),
                          child: ListView.builder(
                            padding: EdgeInsets.zero,
                            shrinkWrap: true,
                            itemCount: _openOptions!.length,
                            itemBuilder: (context, index) {
                              final option = _openOptions![index];
                              final selected = option.value == _selectedOption;
                              return InkWell(
                                onTap: () {
                                  setState(() {
                                    if (option.value is int) {
                                      _selectedRole = option.value as int;
                                    } else {
                                      _selectedDepartment =
                                          option.value as String;
                                    }
                                    _openOptions = null;
                                    _formError = null;
                                  });
                                },
                                child: Container(
                                  constraints:
                                      const BoxConstraints(minHeight: 27),
                                  color: selected
                                      ? const Color(0xFF98AAB8)
                                      : AppColors.ustpWhite,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 6,
                                    vertical: 3,
                                  ),
                                  alignment: Alignment.centerLeft,
                                  child: Text(
                                    option.label,
                                    style: const TextStyle(
                                      color: AppColors.ustpDark,
                                      fontSize: 13,
                                      height: 1.15,
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  String? _requiredValidator(String? value) =>
      value == null || value.trim().isEmpty ? 'This field is required.' : null;
}

class _EditOption {
  const _EditOption({required this.value, required this.label});

  final Object value;
  final String label;
}

class _EditTextField extends StatelessWidget {
  const _EditTextField({
    required this.controller,
    required this.label,
    this.keyboardType,
    this.validator,
    this.readOnly = false,
    this.suffixIcon,
  });

  final TextEditingController controller;
  final String label;
  final TextInputType? keyboardType;
  final FormFieldValidator<String>? validator;
  final bool readOnly;
  final Widget? suffixIcon;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _EditFieldLabel(label),
        const SizedBox(height: 6),
        TextFormField(
          controller: controller,
          keyboardType: keyboardType,
          validator: validator,
          readOnly: readOnly,
          style: const TextStyle(
            color: AppColors.ustpDark,
            fontSize: 13,
          ),
          decoration: _editFieldDecoration().copyWith(suffixIcon: suffixIcon),
        ),
      ],
    );
  }
}

class _EditSelectionField extends StatelessWidget {
  const _EditSelectionField({
    super.key,
    required this.label,
    required this.value,
    required this.onTap,
    this.placeholder,
  });

  final String label;
  final String? value;
  final String? placeholder;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _EditFieldLabel(label),
        const SizedBox(height: 6),
        Material(
          color: AppColors.ustpWhite,
          borderRadius: BorderRadius.circular(8),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(8),
            child: Container(
              height: 41,
              padding: const EdgeInsets.symmetric(horizontal: 11),
              decoration: BoxDecoration(
                color: AppColors.ustpWhite,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFDCE4EF)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      value ?? placeholder ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: value == null
                            ? AppColors.ustpMuted
                            : AppColors.ustpDark,
                        fontSize: 13,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    color: AppColors.ustpNavy,
                    size: 21,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _EditFieldLabel extends StatelessWidget {
  const _EditFieldLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Text(
      label,
      style: const TextStyle(
        color: AppColors.ustpDark,
        fontSize: 12,
        fontWeight: FontWeight.w600,
      ),
    );
  }
}

InputDecoration _editFieldDecoration() => InputDecoration(
      isDense: true,
      filled: true,
      fillColor: AppColors.ustpWhite,
      contentPadding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
      errorStyle: const TextStyle(fontSize: 10, height: 1),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFDCE4EF)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFDCE4EF)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AppColors.ustpNavy),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AppColors.error),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AppColors.error),
      ),
    );
