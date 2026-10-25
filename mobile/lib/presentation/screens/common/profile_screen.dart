import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auditor_page_header.dart';
import '../../widgets/admin_page_header.dart';
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

  @override
  void initState() {
    super.initState();
    _fullNameController = TextEditingController(
      text: context.read<AuthProvider>().currentUser?.fullName ?? '',
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
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text('Are you sure you want to log out of CompliTrack?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: AppColors.ustpWhite,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Log Out'),
          ),
        ],
      ),
    );
    if (shouldLogout != true || !mounted) return;
    await context.read<AuthProvider>().logout();
    if (mounted) context.go('/login');
  }

  InputDecoration _passwordDecoration({
    required bool obscureText,
    required VoidCallback onToggle,
  }) {
    return InputDecoration(
      hintText: 'Password',
      hintStyle: const TextStyle(color: AppColors.ustpMuted),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      filled: true,
      fillColor: AppColors.ustpWhite,
      suffixIcon: IconButton(
        onPressed: onToggle,
        icon: Icon(
          obscureText ? AppIcons.visibilityOff : AppIcons.visibility,
          size: 18,
          color: AppColors.ustpMuted,
        ),
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(7),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(7),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(7),
        borderSide: const BorderSide(color: AppColors.ustpNavy),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final currentUser = auth.currentUser;
    final fullName = currentUser?.fullName ?? _fullNameController.text;
    final isAdmin = auth.roleId == 1;

    if (auth.roleId == 2) {
      return _buildAuditorProfile(fullName);
    }

    final profileContent = ListView(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 24),
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 14),
          decoration: BoxDecoration(
            color: AppColors.ustpWhite,
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              CircleAvatar(
                radius: 27,
                backgroundColor: AppColors.ustpGold,
                child: CircleAvatar(
                  radius: 24,
                  backgroundColor: AppColors.ustpLight,
                  child: Text(
                    _initials(fullName),
                    style: const TextStyle(
                      fontFamily: 'Poppins',
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.ustpNavy,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                fullName,
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: AppColors.ustpDark,
                ),
              ),
              const SizedBox(height: 5),
              RoleBadge(roleId: auth.roleId),
              Align(
                alignment: Alignment.centerRight,
                child: IconButton(
                  tooltip: 'Log out',
                  onPressed: _handleLogout,
                  icon: const Icon(
                    AppIcons.logout,
                    color: AppColors.ustpMuted,
                    size: 18,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.ustpWhite,
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _FieldLabel('Full Name'),
              const SizedBox(height: 6),
              TextFormField(
                controller: _fullNameController,
                readOnly: true,
                decoration: _accountDecoration(fullName, enabled: true),
              ),
              const SizedBox(height: 9),
              const _FieldLabel('Email Address'),
              const SizedBox(height: 6),
              TextFormField(
                enabled: false,
                initialValue: currentUser?.email ?? '',
                decoration: _accountDecoration(currentUser?.email ?? ''),
              ),
              const SizedBox(height: 9),
              const _FieldLabel('Department / Campus Source'),
              const SizedBox(height: 6),
              TextFormField(
                enabled: false,
                initialValue: [
                  currentUser?.departmentName,
                  currentUser?.officeName,
                ]
                    .whereType<String>()
                    .where((value) => value.isNotEmpty)
                    .join(' — '),
                decoration: _accountDecoration([
                  currentUser?.departmentName,
                  currentUser?.officeName,
                ]
                    .whereType<String>()
                    .where((value) => value.isNotEmpty)
                    .join(' — ')),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: Divider(height: 1, color: Color(0xFFE2E8F0)),
              ),
              const Text(
                'Change Password',
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: AppColors.ustpNavy,
                ),
              ),
              const SizedBox(height: 2),
              const Text(
                'Ensure your credentials remain secure.',
                style: TextStyle(fontSize: 10, color: AppColors.ustpMuted),
              ),
              const SizedBox(height: 13),
              Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const _FieldLabel('Current Password'),
                    const SizedBox(height: 5),
                    StatefulBuilder(
                      builder: (context, setFieldState) => TextFormField(
                        controller: _currentPasswordController,
                        obscureText: _hideCurrentPassword,
                        validator: (value) => value == null || value.isEmpty
                            ? 'Enter your current password'
                            : null,
                        decoration: _passwordDecoration(
                          obscureText: _hideCurrentPassword,
                          onToggle: () => setFieldState(
                            () => _hideCurrentPassword = !_hideCurrentPassword,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const _FieldLabel('New Password'),
                    const SizedBox(height: 5),
                    StatefulBuilder(
                      builder: (context, setFieldState) => TextFormField(
                        controller: _newPasswordController,
                        obscureText: _hideNewPassword,
                        validator: (value) => value == null || value.length < 12
                            ? 'Use at least 12 characters'
                            : null,
                        decoration: _passwordDecoration(
                          obscureText: _hideNewPassword,
                          onToggle: () => setFieldState(
                            () => _hideNewPassword = !_hideNewPassword,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const _FieldLabel('Confirm New Password'),
                    const SizedBox(height: 5),
                    StatefulBuilder(
                      builder: (context, setFieldState) => TextFormField(
                        controller: _confirmPasswordController,
                        obscureText: _hideConfirmPassword,
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
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      height: 40,
                      child: ElevatedButton(
                        onPressed:
                            _isUpdatingPassword ? null : _handleUpdatePassword,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.ustpGold,
                          foregroundColor: AppColors.ustpDark,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: _isUpdatingPassword
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: AppColors.ustpDark,
                                ),
                              )
                            : const Text('Update Password'),
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
      backgroundColor: AppColors.ustpLight,
      appBar: isAdmin
          ? null
          : UstpAppBar(
              onProfileTap: () {},
            ),
      body: isAdmin
          ? Column(
              children: [
                const AdminPageHeader(
                  title: 'My Profile',
                  subtitle: 'Account details & security',
                ),
                Expanded(child: profileContent),
              ],
            )
          : profileContent,
      bottomNavigationBar: isAdmin
          ? BottomNavigationBar(
              currentIndex: 4,
              onTap: (index) {
                const routes = [
                  '/admin',
                  '/admin/users',
                  '/admin/departments',
                  '/admin/archives',
                  '/admin/reports',
                ];
                context.go(routes[index]);
              },
              type: BottomNavigationBarType.fixed,
              backgroundColor: AppColors.ustpNavy,
              selectedItemColor: AppColors.ustpGold,
              unselectedItemColor: AppColors.ustpMuted,
              items: const [
                BottomNavigationBarItem(
                  icon: Icon(AppIcons.dashboard),
                  label: 'Dashboard',
                ),
                BottomNavigationBarItem(
                  icon: Icon(AppIcons.users),
                  label: 'Users',
                ),
                BottomNavigationBarItem(
                  icon: Icon(AppIcons.department),
                  label: 'Offices',
                ),
                BottomNavigationBarItem(
                  icon: Icon(AppIcons.archive),
                  label: 'Archive',
                ),
                BottomNavigationBarItem(
                  icon: Icon(AppIcons.compliance),
                  label: 'Reports',
                ),
              ],
            )
          : null,
    );
  }

  Widget _buildAuditorProfile(String fullName) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(onProfileTap: () {}),
      body: Column(
        children: [
          const AuditorPageHeader(
            title: 'My Profile',
            subtitle: 'Account details & security',
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(14, 0, 14, 18),
              children: [
                Container(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 14),
                  decoration: BoxDecoration(
                    color: AppColors.ustpWhite,
                    borderRadius: BorderRadius.circular(13),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      CircleAvatar(
                        radius: 25,
                        backgroundColor: AppColors.ustpGold,
                        child: CircleAvatar(
                          radius: 22,
                          backgroundColor: AppColors.ustpLight,
                          child: Text(
                            _initials(fullName),
                            style: const TextStyle(
                              fontFamily: 'Poppins',
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: AppColors.ustpNavy,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        fullName,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontFamily: 'Poppins',
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.ustpDark,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.success.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(9),
                        ),
                        child: const Text(
                          'Auditor',
                          style: TextStyle(
                            fontSize: 9,
                            color: AppColors.success,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 10),
                        child: Divider(height: 1, color: Color(0xFFE2E8F0)),
                      ),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          'FULL NAME',
                          style: TextStyle(
                            fontSize: 8,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ustpDark,
                          ),
                        ),
                      ),
                      const SizedBox(height: 4),
                      TextFormField(
                        controller: _fullNameController,
                        readOnly: true,
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                        ),
                        decoration: _accountDecoration(fullName, enabled: true),
                      ),
                      const SizedBox(height: 10),
                      const Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          'Change Password',
                          style: TextStyle(
                            fontFamily: 'Poppins',
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: AppColors.ustpDark,
                          ),
                        ),
                      ),
                      const SizedBox(height: 5),
                      Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const _FieldLabel('CURRENT PASSWORD'),
                            const SizedBox(height: 4),
                            StatefulBuilder(
                              builder: (context, setFieldState) =>
                                  TextFormField(
                                controller: _currentPasswordController,
                                obscureText: _hideCurrentPassword,
                                validator: (value) =>
                                    value == null || value.isEmpty
                                        ? 'Enter your current password'
                                        : null,
                                style: const TextStyle(fontSize: 10),
                                decoration: _passwordDecoration(
                                  obscureText: _hideCurrentPassword,
                                  onToggle: () => setFieldState(
                                    () => _hideCurrentPassword =
                                        !_hideCurrentPassword,
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 8),
                            const _FieldLabel('NEW PASSWORD'),
                            const SizedBox(height: 4),
                            StatefulBuilder(
                              builder: (context, setFieldState) =>
                                  TextFormField(
                                controller: _newPasswordController,
                                obscureText: _hideNewPassword,
                                validator: (value) =>
                                    value == null || value.length < 12
                                        ? 'Use at least 12 characters'
                                        : null,
                                style: const TextStyle(fontSize: 10),
                                decoration: _passwordDecoration(
                                  obscureText: _hideNewPassword,
                                  onToggle: () => setFieldState(
                                    () => _hideNewPassword = !_hideNewPassword,
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 8),
                            const SizedBox(height: 10),
                            SizedBox(
                              width: double.infinity,
                              height: 36,
                              child: ElevatedButton(
                                onPressed: _isUpdatingPassword
                                    ? null
                                    : _handleUpdatePassword,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppColors.ustpGold,
                                  foregroundColor: AppColors.ustpDark,
                                  elevation: 0,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(7),
                                  ),
                                ),
                                child: _isUpdatingPassword
                                    ? const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: AppColors.ustpDark,
                                        ),
                                      )
                                    : const Text(
                                        'Update Password',
                                        style: TextStyle(fontSize: 10),
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
            ),
          ),
        ],
      ),
    );
  }

  InputDecoration _accountDecoration(String value, {bool enabled = false}) =>
      InputDecoration(
        hintText: value.isEmpty ? 'Not provided' : null,
        enabled: enabled,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
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
      );

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return '?';
    return parts.length == 1
        ? parts.first.substring(0, 1).toUpperCase()
        : '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w600,
        color: AppColors.ustpDark,
      ),
    );
  }
}
