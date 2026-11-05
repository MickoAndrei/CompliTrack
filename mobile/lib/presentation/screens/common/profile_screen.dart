import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/current_user_avatar.dart';
import '../../widgets/profile_picture_dialog.dart';
import '../../widgets/role_badge.dart';
import '../../widgets/ustp_app_bar.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _authRepository = AuthRepository();
  late final TextEditingController _fullNameController;
  final _currentPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _hideCurrentPassword = true;
  bool _hideNewPassword = true;
  bool _hideConfirmPassword = true;
  bool _isUpdatingPassword = false;
  bool _isLoggingOut = false;

  Future<void> _showProfilePictureDialog() async {
    final updated = await ProfilePictureDialog.show(context);
    if (updated == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profile photo updated.')),
      );
    }
  }

  @override
  void initState() {
    super.initState();
    _fullNameController = TextEditingController(
      text: context.read<AuthProvider>().currentUser?.fullName ??
          'Nico Dale B. Sevilla',
    );
  }

  @override
  void dispose() {
    _fullNameController.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _handleUpdatePassword() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _isUpdatingPassword = true);
    try {
      await _authRepository.changePassword(
        currentPassword: _currentPasswordController.text,
        newPassword: _newPasswordController.text,
      );
      _currentPasswordController.clear();
      _newPasswordController.clear();
      _confirmPasswordController.clear();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password updated successfully.')),
        );
      }
    } on DioException catch (error) {
      if (mounted) {
        final body = error.response?.data;
        final message = body is Map && body['message'] is String
            ? body['message'] as String
            : 'Unable to update password.';
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
      }
    } finally {
      if (mounted) setState(() => _isUpdatingPassword = false);
    }
  }

  Future<void> _handleLogout() async {
    setState(() => _isLoggingOut = true);
    try {
      await context.read<AuthProvider>().logout();
    } on DioException {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
                'You were signed out on this device, but the server could not be reached.'),
          ),
        );
      }
    } finally {
      if (mounted) {
        context.go('/login');
      }
    }
  }

  InputDecoration _accountDecoration(String text, {bool enabled = false}) {
    return InputDecoration(
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      filled: true,
      fillColor: const Color(0xFFF8F9FA),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppColors.ustpNavy),
      ),
    );
  }

  InputDecoration _passwordDecoration({
    required bool obscureText,
    required VoidCallback onToggle,
  }) {
    return InputDecoration(
      hintText: '',
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      filled: true,
      fillColor: const Color(0xFFF8F9FA),
      suffixIcon: IconButton(
        onPressed: onToggle,
        icon: Icon(
          obscureText ? Icons.visibility_off_rounded : Icons.visibility_rounded,
          size: 18,
          color: const Color(0xFF64748B),
        ),
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppColors.ustpNavy),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final currentUser = auth.currentUser;
    final fullName = currentUser?.fullName ?? _fullNameController.text;

    final profileContent = ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        // Profile Card Container matching mockup 1-to-1
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top Row: Avatar + Name & Role Badge + Settings Gear Icon
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  // Large circular profile avatar
                  InkWell(
                    onTap: _showProfilePictureDialog,
                    customBorder: const CircleBorder(),
                    child: CurrentUserAvatar(
                      radius: 32,
                      avatarVersion: auth.avatarVersion,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          fullName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        const SizedBox(height: 6),
                        RoleBadge(roleId: auth.roleId),
                      ],
                    ),
                  ),
                  // Settings Gear Icon
                  IconButton(
                    onPressed: () => context.push('/super-admin/settings'),
                    icon: const Icon(
                      Icons.settings_outlined,
                      color: Color(0xFF0F172A),
                      size: 24,
                    ),
                  ),
                ],
              ),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: Divider(
                  height: 1,
                  thickness: 1,
                  color: Color(0xFFE2E8F0),
                ),
              ),

              // Full Name Field
              const Text(
                'Full Name',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 6),
              TextFormField(
                controller: _fullNameController,
                readOnly: true,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 14,
                  color: Color(0xFF0F172A),
                ),
                decoration: _accountDecoration(fullName, enabled: true),
              ),
              const SizedBox(height: 14),

              // Email Address Field
              const Text(
                'Email Address',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 6),
              TextFormField(
                enabled: false,
                initialValue:
                    currentUser?.email ?? 'Sevvianicodale397@gmail.com',
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 14,
                  color: Color(0xFF64748B),
                ),
                decoration: _accountDecoration(currentUser?.email ?? ''),
              ),
              const SizedBox(height: 14),

              // Department Field
              const Text(
                'Department',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 6),
              TextFormField(
                enabled: false,
                initialValue: [
                  currentUser?.departmentName,
                  currentUser?.officeName,
                ]
                        .whereType<String>()
                        .where((value) => value.isNotEmpty)
                        .join(' — ')
                        .isNotEmpty
                    ? [
                        currentUser?.departmentName,
                        currentUser?.officeName,
                      ]
                        .whereType<String>()
                        .where((value) => value.isNotEmpty)
                        .join(' — ')
                    : 'CET — System-Wide',
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 14,
                  color: Color(0xFF64748B),
                ),
                decoration: _accountDecoration('CET — System-Wide'),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Divider(
                  height: 1,
                  thickness: 1,
                  color: Color(0xFFE2E8F0),
                ),
              ),

              // Change Password Heading
              const Text(
                'Change Password',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 14),

              // Form for Password Update
              Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Current Password
                    const Text(
                      'Current Password',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13.5,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 6),
                    StatefulBuilder(
                      builder: (context, setFieldState) => TextFormField(
                        controller: _currentPasswordController,
                        obscureText: _hideCurrentPassword,
                        style: const TextStyle(fontSize: 14),
                        validator: (value) => value == null || value.isEmpty
                            ? 'Enter current password'
                            : null,
                        decoration: _passwordDecoration(
                          obscureText: _hideCurrentPassword,
                          onToggle: () => setFieldState(
                            () => _hideCurrentPassword = !_hideCurrentPassword,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),

                    // New Password
                    const Text(
                      'New Password',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13.5,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 6),
                    StatefulBuilder(
                      builder: (context, setFieldState) => TextFormField(
                        controller: _newPasswordController,
                        obscureText: _hideNewPassword,
                        style: const TextStyle(fontSize: 14),
                        validator: (value) => value == null || value.length < 6
                            ? 'Use at least 6 characters'
                            : null,
                        decoration: _passwordDecoration(
                          obscureText: _hideNewPassword,
                          onToggle: () => setFieldState(
                            () => _hideNewPassword = !_hideNewPassword,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Confirm New Password
                    const Text(
                      'Confirm New Password',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13.5,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 6),
                    StatefulBuilder(
                      builder: (context, setFieldState) => TextFormField(
                        controller: _confirmPasswordController,
                        obscureText: _hideConfirmPassword,
                        style: const TextStyle(fontSize: 14),
                        validator: (value) =>
                            value != _newPasswordController.text
                                ? 'Passwords do not match'
                                : null,
                        decoration: _passwordDecoration(
                          obscureText: _hideConfirmPassword,
                          onToggle: () => setFieldState(
                            () => _hideConfirmPassword = !_hideConfirmPassword,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Update Password Button
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: ElevatedButton(
                        onPressed:
                            _isUpdatingPassword ? null : _handleUpdatePassword,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFFBBF24),
                          foregroundColor: const Color(0xFF0F172A),
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        child: _isUpdatingPassword
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Color(0xFF0F172A),
                                ),
                              )
                            : const Text(
                                'Update Password',
                                style: TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: OutlinedButton.icon(
                        onPressed: _isLoggingOut ? null : _handleLogout,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFFB91C1C),
                          side: const BorderSide(color: Color(0xFFB91C1C)),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        icon: _isLoggingOut
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Color(0xFFB91C1C),
                                ),
                              )
                            : const Icon(Icons.logout_rounded, size: 18),
                        label: const Text(
                          'Log Out',
                          style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: const UstpAppBar(),
      body: profileContent,
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: AppColors.ustpNavy,
        ),
        child: BottomNavigationBar(
          currentIndex: 0,
          onTap: (index) {
            final routes = switch (auth.roleId) {
              0 => [
                  '/super-admin',
                  '/super-admin/users',
                  '/super-admin/departments',
                  '/super-admin/logs',
                  '/super-admin/archives',
                ],
              1 => [
                  '/admin',
                  '/admin/users',
                  '/admin/departments',
                  '/admin/archives',
                  '/admin/reports',
                ],
              2 => [
                  '/auditor',
                  '/auditor/findings',
                  '/auditor/archives',
                  '/auditor/profile',
                  '/auditor/profile',
                ],
              _ => [
                  '/auditee',
                  '/auditee/my-findings',
                  '/auditee/archives',
                  '/auditee/notifications',
                  '/auditee/notifications',
                ],
            };
            context.go(routes[index]);
          },
          type: BottomNavigationBarType.fixed,
          backgroundColor: AppColors.ustpNavy,
          selectedItemColor: AppColors.ustpMuted,
          unselectedItemColor: AppColors.ustpMuted,
          iconSize: 18,
          selectedLabelStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 9,
            fontWeight: FontWeight.bold,
          ),
          unselectedLabelStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 9,
          ),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.grid_view_rounded),
              label: 'Dashboard',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.person_rounded),
              label: 'User',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.account_tree_outlined),
              label: 'Dept',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.find_in_page_outlined),
              label: 'Actions',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.inventory_2_outlined),
              label: 'Archive',
            ),
          ],
        ),
      ),
    );
  }
}
