import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../providers/auth_provider.dart';
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
  bool _hideCurrentPassword = true;
  bool _hideNewPassword = true;
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
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
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
      hintText: '••••••••••••',
      hintStyle: const TextStyle(color: AppColors.ustpMuted, letterSpacing: 2),
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
    final fullName = _fullNameController.text;

    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        onProfileTap: () {},
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 24),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.ustpWhite,
              borderRadius: BorderRadius.circular(13),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 25,
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
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        fullName,
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
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Log out',
                  onPressed: _handleLogout,
                  icon: const Icon(AppIcons.logout, color: AppColors.ustpMuted),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
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
                const Text(
                  'Full Name',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ustpDark,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _fullNameController,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: fullName,
                    contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12, vertical: 12),
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
                  ),
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
                              () =>
                                  _hideCurrentPassword = !_hideCurrentPassword,
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
                          validator: (value) =>
                              value == null || value.length < 6
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
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        height: 40,
                        child: ElevatedButton.icon(
                          onPressed: _isUpdatingPassword
                              ? null
                              : _handleUpdatePassword,
                          icon: const Icon(AppIcons.lock, size: 17),
                          label: _isUpdatingPassword
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: AppColors.ustpDark,
                                  ),
                                )
                              : const Text('Update Password'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.ustpGold,
                            foregroundColor: AppColors.ustpDark,
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
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
      ),
    );
  }

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
