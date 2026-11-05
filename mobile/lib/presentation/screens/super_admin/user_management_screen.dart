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
    try {
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
    } catch (error) {
      if (mounted) {
        setState(() {
          _users = [];
          _isLoading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Could not load users from database: $error'),
          ),
        );
      }
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
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _editingUser = user;
        _isCreatingUser = false;
        _editDepartments = [];
      });
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
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _editingUser = null;
        _isCreatingUser = true;
        _editDepartments = [];
      });
    }
  }

  void _showDeactivateDialog(UserModel user) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(user.isActive ? 'Deactivate User' : 'Activate User'),
        content: Text(
          user.isActive
              ? 'Are you sure you want to deactivate ${user.fullName}?'
              : 'Are you sure you want to activate ${user.fullName}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor:
                  user.isActive ? AppColors.error : AppColors.success,
              foregroundColor: AppColors.ustpWhite,
            ),
            onPressed: () async {
              Navigator.pop(context);
              try {
                await _userRepository.deactivateUser(user.id);
              } catch (_) {}
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      user.isActive
                          ? '${user.fullName} deactivated'
                          : '${user.fullName} activated',
                    ),
                  ),
                );
                _loadUsers();
              }
            },
            child: Text(user.isActive ? 'Deactivate' : 'Activate'),
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
              try {
                await _userRepository.resetPassword(user.id);
              } catch (_) {}
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

  String _getRoleFilterText() {
    switch (_selectedRoleId) {
      case 0:
        return 'Super Admin';
      case 1:
        return 'Admin';
      case 2:
        return 'Auditor';
      case 3:
        return 'Auditee';
      default:
        return 'All Roles';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: UstpAppBar(
        onNotificationTap: () => AdminNotificationsScreen.showOverlay(context),
      ),
      body: _editingUser == null && !_isCreatingUser
          ? Column(
              children: [
                // Search & Filter Header Section
                Container(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
                  color: const Color(0xFFF8F9FA),
                  child: Column(
                    children: [
                      // Search Bar
                      Container(
                        height: 44,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: TextField(
                          controller: _searchController,
                          onChanged: (_) => _loadUsers(),
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 14,
                            color: Color(0xFF0F172A),
                          ),
                          decoration: const InputDecoration(
                            hintText: 'Search users...',
                            hintStyle: TextStyle(
                              fontFamily: 'Inter',
                              fontSize: 14,
                              color: Color(0xFF94A3B8),
                            ),
                            prefixIcon: Icon(
                              Icons.search_rounded,
                              color: Color(0xFF94A3B8),
                              size: 20,
                            ),
                            border: InputBorder.none,
                            contentPadding: EdgeInsets.symmetric(vertical: 10),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Controls Row: Role Filter Dropdown + Create User CTA
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          // Role Filter Dropdown Button
                          PopupMenuButton<int>(
                            onSelected: (val) {
                              setState(() => _selectedRoleId = val == -1 ? null : val);
                              _loadUsers();
                            },
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                            color: Colors.white,
                            elevation: 4,
                            itemBuilder: (context) => [
                              const PopupMenuItem<int>(
                                value: -1,
                                child: Text(
                                  'All Roles',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ),
                              const PopupMenuItem<int>(
                                value: 0,
                                child: Text(
                                  'Super Admin',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ),
                              const PopupMenuItem<int>(
                                value: 1,
                                child: Text(
                                  'Admin',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ),
                              const PopupMenuItem<int>(
                                value: 2,
                                child: Text(
                                  'Auditor',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ),
                              const PopupMenuItem<int>(
                                value: 3,
                                child: Text(
                                  'Auditee',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13.5,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ),
                            ],
                            child: Container(
                              height: 42,
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 14, vertical: 8),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    _getRoleFilterText(),
                                    style: const TextStyle(
                                      fontFamily: 'Inter',
                                      fontSize: 14,
                                      fontWeight: FontWeight.w500,
                                      color: Color(0xFF1E293B),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  const Icon(
                                    Icons.arrow_downward,
                                    size: 14,
                                    color: Color(0xFF64748B),
                                  ),
                                ],
                              ),
                            ),
                          ),

                          // Create User CTA Button
                          Material(
                            color: const Color(0xFFFBBF24),
                            borderRadius: BorderRadius.circular(10),
                            child: InkWell(
                              onTap: _showCreateUserForm,
                              borderRadius: BorderRadius.circular(10),
                              child: Container(
                                height: 42,
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 8),
                                child: const Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      Icons.person_add_alt_1_rounded,
                                      size: 18,
                                      color: Color(0xFF0F172A),
                                    ),
                                    SizedBox(width: 6),
                                    Text(
                                      'Create User',
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
                                    const EdgeInsets.fromLTRB(16, 0, 16, 16),
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
          // Top Row: Code & Status Indicator
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                user.userCode,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF64748B),
                ),
              ),
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: user.isActive
                          ? const Color(0xFF10B981)
                          : const Color(0xFF64748B),
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    user.isActive ? 'Active' : 'Inactive',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: user.isActive
                          ? const Color(0xFF10B981)
                          : const Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 6),

          // Middle: Full Name
          Text(
            user.fullName,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 17,
              fontWeight: FontWeight.bold,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 3),

          // Email
          Text(
            user.email,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 13.5,
              color: Color(0xFF64748B),
            ),
          ),
          const SizedBox(height: 3),

          // Office / Department
          Text(
            user.officeName,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 13.5,
              fontWeight: FontWeight.w500,
              color: Color(0xFF334155),
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

          // Bottom Row: Role Badge (Left) + Actions (Right)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              RoleBadge(roleId: user.roleId),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  GestureDetector(
                    onTap: () => _showEditUser(user),
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
                    padding: EdgeInsets.symmetric(horizontal: 6),
                    child: Text(
                      '·',
                      style: TextStyle(
                        color: Color(0xFF94A3B8),
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  GestureDetector(
                    onTap: () => _showDeactivateDialog(user),
                    child: Text(
                      user.isActive ? 'Deactivate' : 'Activate',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: user.isActive
                            ? const Color(0xFFEF4444)
                            : const Color(0xFF10B981),
                      ),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 6),
                    child: Text(
                      '·',
                      style: TextStyle(
                        color: Color(0xFF94A3B8),
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  GestureDetector(
                    onTap: () => _showResetPasswordDialog(user),
                    child: const Text(
                      'Reset',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF0F172A),
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
