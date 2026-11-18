import 'dart:math' as math;
import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/department_model.dart';
import '../../../data/repositories/department_repository.dart';

class DepartmentEditForm extends StatefulWidget {
  const DepartmentEditForm({
    super.key,
    this.department,
    required this.onCancel,
    required this.onSaved,
    this.repository,
  });

  final DepartmentModel? department;
  final VoidCallback onCancel;
  final VoidCallback onSaved;
  final DepartmentRepository? repository;

  @override
  State<DepartmentEditForm> createState() => _DepartmentEditFormState();
}

class _DepartmentEditFormState extends State<DepartmentEditForm> {
  final _formKey = GlobalKey<FormState>();
  final _stackKey = GlobalKey();
  final _campusFieldKey = GlobalKey();
  final _collegeFieldKey = GlobalKey();

  late final TextEditingController _codeController;
  late final TextEditingController _nameController;

  late String _selectedCampus;
  late String _selectedCollege;
  late final String _initialCampus;
  late final String _initialCollege;

  bool _isSaving = false;
  String? _formError;
  List<_EditOption>? _openOptions;
  Object? _selectedOption;
  bool _choosingCampus = false;
  double _menuLeft = 16;
  double _menuTop = 0;
  double _menuWidth = 244;
  double _menuHeight = 0;

  static const List<String> _campuses = [
    'Alubijid',
    'Balubal',
    'Cagayan de Oro',
    'Claveria',
    'Jasaan',
    'Oroquieta',
    'Panaon',
    'Villanueva',
  ];

  static const Map<String, Map<String, String>> _campusColleges = {
    'Cagayan de Oro': {
      'College of Engineering and Architecture':
          'CEA - College of Engineering and Architecture',
      'College of Information Technology and Computing':
          'CITC - College of Information Technology and Computing',
      'College of Science and Mathematics':
          'CSM - College of Science and Mathematics',
      'College of Science and Technology Education':
          'CSTE - College of Science and Technology Education',
      'College of Technology': 'COT - College of Technology',
      'College of Medicine': 'COM - College of Medicine',
      'College of Nursing': 'CON - College of Nursing',
    },
    'Claveria': {
      'CET': 'CET - College of Engineering and Technology',
      'College of Agriculture': 'CA - College of Agriculture',
      'College of Arts and Sciences': 'CAS - College of Arts and Sciences',
    },
  };

  @override
  void initState() {
    super.initState();
    _codeController =
        TextEditingController(text: widget.department?.departmentCode);
    _nameController =
        TextEditingController(text: widget.department?.departmentName);
    final campus = widget.department?.campusName ?? 'Claveria';
    _selectedCampus = campus == 'CET Claveria'
        ? 'Claveria'
        : campus
            .replaceFirst(RegExp(r'^USTP '), '')
            .replaceFirst(RegExp(r' Campus$'), '');
    final college = widget.department?.collegeName ?? 'CET';
    _selectedCollege = _selectedCampus == 'Claveria' &&
            college == 'College of Engineering and Technology'
        ? 'CET'
        : college;
    _initialCampus = _selectedCampus;
    _initialCollege = _selectedCollege;
  }

  @override
  void dispose() {
    _codeController.dispose();
    _nameController.dispose();
    super.dispose();
  }

  List<_EditOption> get _campusOptions => [
        for (final campus in _campuses)
          _EditOption(value: campus, label: 'USTP $campus'),
      ];

  List<_EditOption> get _collegeOptions {
    final map = _campusColleges[_selectedCampus];
    if (map == null || map.isEmpty) {
      return [
        const _EditOption(value: '', label: 'No published college list'),
      ];
    }
    return [
      const _EditOption(value: '', label: 'Select a college'),
      for (final entry in map.entries)
        _EditOption(value: entry.key, label: entry.value),
    ];
  }

  bool get _hasCollegeOptions {
    final map = _campusColleges[_selectedCampus];
    return map != null && map.isNotEmpty;
  }

  String? get _selectedCollegeDisplayLabel {
    if (!_hasCollegeOptions) return 'No published college list';
    if (_selectedCollege.isEmpty) return 'Select a college';
    final map = _campusColleges[_selectedCampus];
    if (map != null && map.containsKey(_selectedCollege)) {
      return map[_selectedCollege];
    }
    return _selectedCollege;
  }

  Future<void> _save() async {
    if (_isSaving) return;
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_hasCollegeOptions && _selectedCollege.isEmpty) {
      setState(() => _formError = 'Select a college for this campus.');
      return;
    }

    setState(() {
      _isSaving = true;
      _formError = null;
    });

    try {
      final repo = widget.repository ?? DepartmentRepository();
      if (widget.department == null) {
        await repo.createDepartment(
          departmentCode: _codeController.text.trim(),
          departmentName: _nameController.text.trim(),
          collegeName: _selectedCollege,
          campusName: _selectedCampus,
        );
      } else {
        final assignmentChanged = _selectedCampus != _initialCampus ||
            _selectedCollege != _initialCollege;
        await repo.updateDepartment(
          widget.department!.id,
          departmentName: _nameController.text.trim(),
          collegeName: assignmentChanged ? _selectedCollege : null,
          campusName: assignmentChanged ? _selectedCampus : null,
        );
      }
      if (mounted) widget.onSaved();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _isSaving = false;
        final data = error is DioException ? error.response?.data : null;
        _formError = data is Map && data['message'] is String
            ? data['message'] as String
            : 'Could not save department. Please try again.';
      });
    }
  }

  void _showOptions({
    required List<_EditOption> options,
    required Object selectedValue,
    required GlobalKey anchorKey,
    double maxWidth = 280,
  }) {
    final stackBox = _stackKey.currentContext!.findRenderObject()! as RenderBox;
    final anchorBox =
        anchorKey.currentContext!.findRenderObject()! as RenderBox;
    final anchor = stackBox.globalToLocal(
      anchorBox.localToGlobal(Offset.zero),
    );
    final height = math.min(options.length * 36.0, 240.0);
    final selectedIndex = options.indexWhere(
      (option) => option.value == selectedValue,
    );
    final selectedRow = selectedIndex < 0 ? 0 : selectedIndex;
    final top =
        (anchor.dy + anchorBox.size.height / 2 - (selectedRow * 36.0 + 16.0))
            .clamp(8.0, math.max(8.0, stackBox.size.height - height - 8.0))
            .toDouble();
    final width = math.min(maxWidth, stackBox.size.width - 32.0);
    final left = anchor.dx
        .clamp(8.0, math.max(8.0, stackBox.size.width - width - 8.0))
        .toDouble();

    setState(() {
      _choosingCampus = anchorKey == _campusFieldKey;
      _openOptions = options;
      _selectedOption = selectedValue;
      _menuLeft = left;
      _menuTop = top;
      _menuWidth = width;
      _menuHeight = height;
    });
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop || _isSaving) return;
        if (_openOptions != null) {
          setState(() => _openOptions = null);
        } else {
          widget.onCancel();
        }
      },
      child: LayoutBuilder(
        builder: (context, constraints) => Stack(
          key: _stackKey,
          fit: StackFit.expand,
          children: [
            Column(
              children: [
                // Header
                Container(
                  height: 56,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  color: AppColors.ustpWhite,
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          widget.department == null
                              ? 'Add Department'
                              : 'Edit Department',
                          style: const TextStyle(
                            color: AppColors.ustpDark,
                            fontFamily: 'Poppins',
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: _isSaving ? null : widget.onCancel,
                        padding: EdgeInsets.zero,
                        visualDensity: VisualDensity.compact,
                        constraints: const BoxConstraints.tightFor(
                            width: 32, height: 32),
                        icon: const Icon(
                          Icons.cancel_outlined,
                          color: AppColors.ustpMuted,
                          size: 19,
                        ),
                      ),
                    ],
                  ),
                ),
                // Body
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
                            // Department Details
                            const Text(
                              'Department Details',
                              style: TextStyle(
                                color: AppColors.ustpNavy,
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 10),
                            _EditTextField(
                              controller: _codeController,
                              label: 'Department Code',
                              readOnly: widget.department != null,
                              validator: _requiredValidator,
                            ),
                            if (widget.department != null) ...[
                              const SizedBox(height: 4),
                              const Text(
                                'Department code cannot be changed once created.',
                                style: TextStyle(
                                  color: AppColors.ustpMuted,
                                  fontSize: 11.5,
                                ),
                              ),
                            ],
                            const SizedBox(height: 14),
                            _EditTextField(
                              controller: _nameController,
                              label: 'Department Name',
                              validator: _requiredValidator,
                            ),
                            const SizedBox(height: 20),

                            // Campus & College Assignment
                            const Text(
                              'Campus & College Assignment',
                              style: TextStyle(
                                color: AppColors.ustpNavy,
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 10),
                            _EditSelectionField(
                              key: _campusFieldKey,
                              label: 'Campus',
                              value: 'USTP $_selectedCampus',
                              onTap: () => _showOptions(
                                options: _campusOptions,
                                selectedValue: _selectedCampus,
                                anchorKey: _campusFieldKey,
                                maxWidth: 280,
                              ),
                            ),
                            const SizedBox(height: 14),
                            _EditSelectionField(
                              key: _collegeFieldKey,
                              label: 'College',
                              value: _selectedCollegeDisplayLabel,
                              placeholder: 'Select a college',
                              onTap: () {
                                if (!_hasCollegeOptions) return;
                                _showOptions(
                                  options: _collegeOptions,
                                  selectedValue: _selectedCollege,
                                  anchorKey: _collegeFieldKey,
                                  maxWidth: 280,
                                );
                              },
                            ),
                            if (!_hasCollegeOptions) ...[
                              const SizedBox(height: 6),
                              const Text(
                                'No named-college list is published on this campus\'s official page. College will remain unassigned.',
                                style: TextStyle(
                                  color: AppColors.ustpMuted,
                                  fontSize: 11.5,
                                  height: 1.35,
                                ),
                              ),
                            ],
                            if (_formError != null)
                              Padding(
                                padding: const EdgeInsets.only(top: 14),
                                child: Text(
                                  _formError!,
                                  style: const TextStyle(
                                    color: AppColors.error,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                // Footer
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
                              ? 'Saving…'
                              : (widget.department == null
                                  ? 'Add Department'
                                  : 'Save Changes'),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            // Blur backdrop & option dropdown menu overlay
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
                                final selected =
                                    option.value == _selectedOption;
                                return InkWell(
                                  onTap: () {
                                    setState(() {
                                      if (_choosingCampus) {
                                        if (_selectedCampus != option.value) {
                                          _selectedCollege = '';
                                        }
                                        _selectedCampus =
                                            option.value.toString();
                                      } else {
                                        _selectedCollege =
                                            option.value.toString();
                                      }
                                      _openOptions = null;
                                      _formError = null;
                                    });
                                  },
                                  child: Container(
                                    constraints:
                                        const BoxConstraints(minHeight: 36),
                                    color: selected
                                        ? const Color(0xFF98AAB8)
                                        : AppColors.ustpWhite,
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 10,
                                      vertical: 8,
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
    this.validator,
    this.readOnly = false,
  });

  final TextEditingController controller;
  final String label;
  final FormFieldValidator<String>? validator;
  final bool readOnly;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _EditFieldLabel(label),
        const SizedBox(height: 6),
        TextFormField(
          controller: controller,
          validator: validator,
          readOnly: readOnly,
          style: TextStyle(
            color: readOnly ? AppColors.ustpMuted : AppColors.ustpDark,
            fontSize: 13,
          ),
          decoration: _editFieldDecoration(),
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
        if (label.isNotEmpty) ...[
          _EditFieldLabel(label),
          const SizedBox(height: 6),
        ],
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
                        color: value == null || value!.isEmpty
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
