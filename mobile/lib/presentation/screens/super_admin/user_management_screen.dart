import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/department_model.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/department_repository.dart';
import '../../../data/repositories/user_repository.dart';
import '../admin/admin_notifications_screen.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/role_badge.dart';
import '../../widgets/ustp_app_bar.dart';
import 'super_admin_user_edit_form.dart';

class UserManagementScreen extends StatefulWidget {
  const UserManagementScreen({super.key});

  @override
  State<UserManagementScreen> createState() => _UserManagementScreenState();
}

class _RoleFilterLabel extends StatelessWidget {
  const _RoleFilterLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.centerLeft,
        child: Text(
          label,
          maxLines: 1,
          softWrap: false,
          style: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 10,
            color: AppColors.ustpDark,
          ),
        ),
      ),
    );
  }
}

class _UserManagementScreenState extends State<UserManagementScreen> {
  final UserRepository _userRepository = UserRepository();
  final TextEditingController _searchController = TextEditingController();

  int? _selectedRoleId;
  bool _isLoading = true;
  List<UserModel> _users = [];
  UserModel? _editingUser;
  bool _isCreatingUser = false;
  List<DepartmentModel> _editDepartments = [];

  @override
  void initState() {
    super.initState();
    _loadUsers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadUsers() async {
    setState(() => _isLoading = true);
    final results = await _userRepository.getUsers(
      roleId: _selectedRoleId,
      search: _searchController.text.trim(),
    );
    if (mounted) {
      setState(() {
        _users = results;
        _isLoading = false;
      });
    }
  }

  Future<void> _showEditUser(UserModel user) async {
    try {
      final departments =
          await DepartmentRepository().getDepartments(campus: 'Claveria');
      if (!mounted) return;
      setState(() {
        _editingUser = user;
        _isCreatingUser = false;
        _editDepartments = departments;
      });
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not load departments: $error')),
      );
    }
  }

  Future<void> _showCreateUserForm() async {
    try {
      final departments =
          await DepartmentRepository().getDepartments(campus: 'Claveria');
      if (!mounted) return;
      setState(() {
        _editingUser = null;
        _isCreatingUser = true;
        _editDepartments = departments;
      });
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not load departments: $error')),
      );
    }
  }

  void _showDeactivateDialog(UserModel user) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Deactivate User'),
        content: Text('Are you sure you want to deactivate ${user.fullName}?'),
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
              await _userRepository.deactivateUser(user.id);
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('${user.fullName} deactivated')),
                );
                _loadUsers();
              }
            },
            child: const Text('Deactivate'),
          ),
        ],
      ),
    );
  }

  void _showResetPasswordDialog(UserModel user) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Reset Password'),
        content: Text('Send a password reset link to ${user.email}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.ustpNavy,
              foregroundColor: AppColors.ustpWhite,
            ),
            onPressed: () async {
              Navigator.pop(context);
              await _userRepository.resetPassword(user.id);
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Reset link sent to ${user.email}')),
                );
              }
            },
            child: const Text('Send Reset Link'),
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
        onNotificationTap: () => AdminNotificationsScreen.showOverlay(context),
        onProfileTap: () {},
      ),
      body: _editingUser == null && !_isCreatingUser
          ? Column(
              children: [
                // Search & Filter Header Section
                Container(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
                  color: AppColors.ustpLight,
                  child: Column(
                    children: [
                      // Search Bar
                      TextField(
                        controller: _searchController,
                        onChanged: (_) => _loadUsers(),
                        decoration: InputDecoration(
                          hintText: 'Search users...',
                          hintStyle: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 14,
                            color: AppColors.ustpMuted,
                          ),
                          prefixIcon: const Icon(Icons.search_rounded,
                              color: AppColors.ustpMuted, size: 20),
                          contentPadding:
                              const EdgeInsets.symmetric(vertical: 9),
                          fillColor: AppColors.ustpWhite,
                          filled: true,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(9),
                            borderSide:
                                const BorderSide(color: Color(0xFFDCE4EF)),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(9),
                            borderSide:
                                const BorderSide(color: Color(0xFFDCE4EF)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(9),
                            borderSide:
                                const BorderSide(color: AppColors.ustpNavy),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Controls Row: Role Filter Dropdown + Create User CTA
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          // Role Filter Dropdown
                          SizedBox(
                            width: 94,
                            height: 30,
                            child: Container(
                              padding:
                                  const EdgeInsets.symmetric(horizontal: 8),
                              decoration: BoxDecoration(
                                color: AppColors.ustpWhite,
                                borderRadius: BorderRadius.circular(8),
                                border:
                                    Border.all(color: const Color(0xFFDCE4EF)),
                              ),
                              child: DropdownButtonHideUnderline(
                                child: DropdownButton<int?>(
                                  value: _selectedRoleId,
                                  isExpanded: true,
                                  isDense: true,
                                  menuWidth: 94,
                                  menuMaxHeight: 216,
                                  borderRadius: BorderRadius.circular(8),
                                  dropdownColor: AppColors.ustpWhite,
                                  elevation: 4,
                                  selectedItemBuilder: (context) => const [
                                    _RoleFilterLabel('Sort By'),
                                    _RoleFilterLabel('Super Admin'),
                                    _RoleFilterLabel('Admin'),
                                    _RoleFilterLabel('Auditor'),
                                    _RoleFilterLabel('Auditee'),
                                  ],
                                  hint: const Text(
                                    'Sort By',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      fontFamily: 'Inter',
                                      fontSize: 10,
                                      color: AppColors.ustpDark,
                                    ),
                                  ),
                                  icon: const Icon(
                                      Icons.keyboard_arrow_down_rounded,
                                      size: 16,
                                      color: AppColors.ustpMuted),
                                  items: const [
                                    DropdownMenuItem(
                                      value: null,
                                      child: _RoleFilterLabel('Sort By'),
                                    ),
                                    DropdownMenuItem(
                                      value: 0,
                                      child: _RoleFilterLabel('Super Admin'),
                                    ),
                                    DropdownMenuItem(
                                      value: 1,
                                      child: _RoleFilterLabel('Admin'),
                                    ),
                                    DropdownMenuItem(
                                      value: 2,
                                      child: _RoleFilterLabel('Auditor'),
                                    ),
                                    DropdownMenuItem(
                                      value: 3,
                                      child: _RoleFilterLabel('Auditee'),
                                    ),
                                  ],
                                  onChanged: (val) {
                                    setState(() => _selectedRoleId = val);
                                    _loadUsers();
                                  },
                                ),
                              ),
                            ),
                          ),

                          // Create User Button
                          SizedBox(
                            width: 94,
                            height: 30,
                            child: ElevatedButton.icon(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.ustpGold,
                                foregroundColor: AppColors.ustpDark,
                                elevation: 0,
                                minimumSize: const Size(94, 30),
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 5),
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                ),
                              ),
                              onPressed: _showCreateUserForm,
                              icon: const Icon(Icons.person_add_alt_1,
                                  size: 13, color: AppColors.ustpDark),
                              label: const Text(
                                'Create User',
                                maxLines: 1,
                                softWrap: false,
                                style: TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

                // Users Card List
                Expanded(
                  child: _isLoading
                      ? const Center(
                          child: CircularProgressIndicator(
                              color: AppColors.ustpNavy))
                      : _users.isEmpty
                          ? const EmptyState(
                              title: 'No users found',
                              subtitle:
                                  'No users match your filter or search query.',
                              icon: AppIcons.users,
                            )
                          : RefreshIndicator(
                              color: AppColors.ustpNavy,
                              onRefresh: _loadUsers,
                              child: ListView.builder(
                                padding:
                                    const EdgeInsets.fromLTRB(16, 0, 16, 12),
                                itemCount: _users.length,
                                itemBuilder: (context, index) {
                                  final user = _users[index];
                                  return _buildUserCard(user);
                                },
                              ),
                            ),
                ),
              ],
            )
          : SuperAdminUserEditForm(
              user: _editingUser,
              departments: _editDepartments,
              onCancel: () => setState(() {
                _editingUser = null;
                _isCreatingUser = false;
              }),
              onSaved: () {
                final wasCreating = _isCreatingUser;
                setState(() {
                  _editingUser = null;
                  _isCreatingUser = false;
                });
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      wasCreating ? 'User created.' : 'User updated.',
                    ),
                  ),
                );
                _loadUsers();
              },
            ),
    );
  }

  Widget _buildUserCard(UserModel user) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.fromLTRB(15, 13, 15, 11),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFDCE4EF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: Code & Status Indicator
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                user.userCode,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ustpMuted,
                ),
              ),
              Row(
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    decoration: BoxDecoration(
                      color: user.isActive
                          ? AppColors.success
                          : AppColors.ustpMuted,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    user.isActive ? 'Active' : 'Inactive',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: user.isActive
                          ? AppColors.success
                          : AppColors.ustpMuted,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 7),

          // Middle: Full Name
          Text(
            user.fullName,
            style: const TextStyle(
              fontFamily: 'Poppins',
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: AppColors.ustpDark,
            ),
          ),
          const SizedBox(height: 2),

          // Email
          Text(
            user.email,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 11,
              color: AppColors.ustpMuted,
            ),
          ),
          const SizedBox(height: 2),

          // Office / Department
          Text(
            user.officeName,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 11,
              fontWeight: FontWeight.w500,
              color: AppColors.ustpDark,
            ),
          ),
          const SizedBox(height: 9),

          const Divider(height: 1, color: Color(0xFFDCE4EF)),
          const SizedBox(height: 7),

          // Bottom Row: Role Badge (Left) + Actions (Right)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              RoleBadge(roleId: user.roleId),
              Row(
                children: [
                  TextButton(
                    onPressed: () => _showEditUser(user),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: const Text(
                      'Edit',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: AppColors.info,
                      ),
                    ),
                  ),
                  const Text('·', style: TextStyle(color: AppColors.ustpMuted)),
                  TextButton(
                    onPressed: () => _showDeactivateDialog(user),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: Text(
                      user.isActive ? 'Deactivate' : 'Activate',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color:
                            user.isActive ? AppColors.error : AppColors.success,
                      ),
                    ),
                  ),
                  const Text('·', style: TextStyle(color: AppColors.ustpMuted)),
                  TextButton(
                    onPressed: () => _showResetPasswordDialog(user),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: const Text(
                      'Reset',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpNavy,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}
