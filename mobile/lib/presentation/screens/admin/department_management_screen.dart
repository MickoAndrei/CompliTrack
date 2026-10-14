import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/department_model.dart';
import '../../../data/repositories/department_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class DepartmentManagementScreen extends StatefulWidget {
  const DepartmentManagementScreen({super.key});

  @override
  State<DepartmentManagementScreen> createState() => _DepartmentManagementScreenState();
}

class _DepartmentManagementScreenState extends State<DepartmentManagementScreen> {
  final DepartmentRepository _repository = DepartmentRepository();
  String _selectedCampus = 'CET Claveria';
  bool _isLoading = true;
  List<DepartmentModel> _departments = [];

  @override
  void initState() {
    super.initState();
    _loadDepartments();
  }

  Future<void> _loadDepartments() async {
    setState(() => _isLoading = true);
    final results = await _repository.getDepartments(campus: _selectedCampus);
    if (mounted) {
      setState(() {
        _departments = results;
        _isLoading = false;
      });
    }
  }

  void _showAddDepartmentModal([DepartmentModel? department]) {
    final codeController = TextEditingController(text: department?.departmentCode ?? '');
    final nameController = TextEditingController(text: department?.departmentName ?? '');
    final collegeController = TextEditingController(
      text: department?.collegeName ?? 'College of Engineering and Technology',
    );
    final isEditing = department != null;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            top: 24,
            left: 24,
            right: 24,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                isEditing ? 'Edit Department' : 'Add Department',
                style: const TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppColors.ustpNavy,
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: codeController,
                decoration: InputDecoration(
                  labelText: 'Department Code (e.g., CET-IT)',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: nameController,
                decoration: InputDecoration(
                  labelText: 'Department Name',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: collegeController,
                decoration: InputDecoration(
                  labelText: 'College Name',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.ustpNavy,
                    foregroundColor: AppColors.ustpWhite,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () {
                    Navigator.pop(context);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          isEditing
                              ? 'Department updated successfully'
                              : 'Department added successfully',
                        ),
                      ),
                    );
                    _loadDepartments();
                  },
                  child: Text(
                    isEditing ? 'Update Department' : 'Add Department',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  void _showDeleteDialog(DepartmentModel department) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Delete Department'),
        content: Text('Are you sure you want to delete ${department.departmentName}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: AppColors.ustpWhite,
            ),
            onPressed: () async {
              Navigator.pop(context);
              await _repository.deleteDepartment(department.id);
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('${department.departmentName} deleted')),
                );
                _loadDepartments();
              }
            },
            child: const Text('Delete'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        onNotificationTap: () {},
        onProfileTap: () {},
      ),
      body: Column(
        children: [
          // Filter & Action Header Container
          Container(
            padding: const EdgeInsets.all(16),
            color: AppColors.ustpWhite,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Campus Filter Dropdown
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppColors.ustpLight,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      value: _selectedCampus,
                      icon: const Icon(AppIcons.chevronDown, size: 18, color: AppColors.ustpDark),
                      items: const [
                        DropdownMenuItem(value: 'CET Claveria', child: Text('CET Claveria')),
                        DropdownMenuItem(value: 'All Campuses', child: Text('All Campuses')),
                      ],
                      onChanged: (val) {
                        if (val != null) {
                          setState(() => _selectedCampus = val);
                          _loadDepartments();
                        }
                      },
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpDark,
                      ),
                    ),
                  ),
                ),

                // Add Dept CTA Button
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.ustpGold,
                    foregroundColor: AppColors.ustpDark,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  onPressed: () => _showAddDepartmentModal(),
                  icon: const Icon(AppIcons.plus, size: 18, color: AppColors.ustpDark),
                  label: const Text(
                    'Add Dept',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Department List Cards
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.ustpNavy))
                : _departments.isEmpty
                    ? const EmptyState(
                        title: 'No departments found',
                        subtitle: 'No departments found under the selected campus filter.',
                        icon: AppIcons.department,
                      )
                    : RefreshIndicator(
                        color: AppColors.ustpNavy,
                        onRefresh: _loadDepartments,
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _departments.length,
                          itemBuilder: (context, index) {
                            final dept = _departments[index];
                            return _buildDepartmentCard(dept);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildDepartmentCard(DepartmentModel dept) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: Dept Code Badge (Left) + Campus Name (Right)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF9C3),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.ustpGold.withValues(alpha: 0.4)),
                ),
                child: Text(
                  dept.departmentCode,
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
              ),
              Text(
                dept.campusName,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: AppColors.ustpMuted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Department Title
          Text(
            dept.departmentName,
            style: const TextStyle(
              fontFamily: 'Poppins',
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: AppColors.ustpDark,
            ),
          ),
          const SizedBox(height: 4),

          // College Name Subtitle
          Text(
            dept.collegeName,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 13,
              color: AppColors.ustpMuted,
            ),
          ),
          const SizedBox(height: 12),

          // Actions Row (Edit / Delete aligned right)
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton(
                onPressed: () => _showAddDepartmentModal(dept),
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: const Text(
                  'Edit',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: AppColors.info,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              TextButton(
                onPressed: () => _showDeleteDialog(dept),
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: const Text(
                  'Delete',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: AppColors.error,
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
