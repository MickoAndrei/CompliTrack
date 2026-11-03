import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../widgets/auth_brand_header.dart';

class ResetPasswordScreen extends StatefulWidget {
  const ResetPasswordScreen({
    super.key,
    required this.email,
    required this.token,
  });

  final String email;
  final String token;

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _repository = AuthRepository();
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final password = _passwordController.text;
    if (widget.email.isEmpty || widget.token.length != 64) {
      setState(() => _errorMessage =
          'This password reset link is incomplete or invalid. Request a new link.');
      return;
    }
    if (password.length < 12) {
      setState(() =>
          _errorMessage = 'Choose a password with at least 12 characters.');
      return;
    }
    if (password != _confirmController.text) {
      setState(() => _errorMessage = 'The passwords do not match.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });
    try {
      await _repository.resetPassword(
        email: widget.email,
        token: widget.token,
        password: password,
      );
      if (mounted) context.go('/login');
    } on DioException catch (error) {
      if (mounted) {
        final body = error.response?.data;
        setState(() {
          _errorMessage = body is Map && body['message'] is String
              ? body['message'] as String
              : 'This reset link is invalid or expired. Request a new one.';
        });
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
        value: const SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.dark,
          systemNavigationBarColor: AppColors.ustpWhite,
          systemNavigationBarIconBrightness: Brightness.dark,
        ),
        child: Scaffold(
          backgroundColor: AppColors.ustpWhite,
          body: Column(
            children: [
              const AuthBrandHeader(),
              Expanded(
                child: LayoutBuilder(
                  builder: (context, constraints) => SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(20, 25, 20, 24),
                    child: ConstrainedBox(
                      constraints:
                          BoxConstraints(minHeight: constraints.maxHeight - 44),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Create New Password',
                            style: TextStyle(
                              fontFamily: 'Poppins',
                              fontSize: 20,
                              height: 1.3,
                              fontWeight: FontWeight.w700,
                              color: AppColors.ustpDark,
                            ),
                          ),
                          const SizedBox(height: 5),
                          const Text(
                            'Please choose a secure password with at least 12 characters.',
                            style: TextStyle(
                              fontFamily: 'Inter',
                              fontSize: 12,
                              height: 1.45,
                              color: AppColors.ustpMuted,
                            ),
                          ),
                          const SizedBox(height: 24),
                          const _ResetFieldLabel('New Password'),
                          const SizedBox(height: 6),
                          _passwordField(
                            controller: _passwordController,
                            obscure: _obscurePassword,
                            onToggle: () => setState(
                              () => _obscurePassword = !_obscurePassword,
                            ),
                            onSubmitted: (_) => _submit(),
                          ),
                          const SizedBox(height: 18),
                          const _ResetFieldLabel('Confirm New Password'),
                          const SizedBox(height: 6),
                          _passwordField(
                            controller: _confirmController,
                            obscure: _obscureConfirm,
                            onToggle: () => setState(
                              () => _obscureConfirm = !_obscureConfirm,
                            ),
                            onSubmitted: (_) => _submit(),
                          ),
                          if (_errorMessage != null) ...[
                            const SizedBox(height: 12),
                            Text(
                              _errorMessage!,
                              style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 12,
                                color: AppColors.error,
                              ),
                            ),
                          ],
                          const SizedBox(height: 24),
                          SizedBox(
                            width: double.infinity,
                            height: 44,
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.ustpGold,
                                foregroundColor: AppColors.ustpDark,
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(11),
                                ),
                              ),
                              onPressed: _isLoading ? null : _submit,
                              child: _isLoading
                                  ? const SizedBox(
                                      width: 17,
                                      height: 17,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: AppColors.ustpDark,
                                      ),
                                    )
                                  : const Text(
                                      'Update Password',
                                      style: TextStyle(
                                        fontFamily: 'Inter',
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
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
            ],
          ),
        ),
      );

  Widget _passwordField({
    required TextEditingController controller,
    required bool obscure,
    required VoidCallback onToggle,
    required ValueChanged<String> onSubmitted,
  }) =>
      TextField(
        controller: controller,
        obscureText: obscure,
        textInputAction: TextInputAction.done,
        onSubmitted: onSubmitted,
        style: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 13,
          color: AppColors.ustpDark,
        ),
        decoration: InputDecoration(
          isDense: true,
          filled: true,
          fillColor: AppColors.ustpWhite,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
          suffixIcon: IconButton(
            onPressed: onToggle,
            icon: Icon(
              obscure
                  ? Icons.visibility_outlined
                  : Icons.visibility_off_outlined,
              size: 18,
              color: AppColors.ustpMuted,
            ),
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(11),
            borderSide: const BorderSide(color: Color(0xFFDCE3EC)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(11),
            borderSide: const BorderSide(color: Color(0xFFDCE3EC)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(11),
            borderSide: const BorderSide(color: AppColors.ustpNavy, width: 1),
          ),
        ),
      );
}

class _ResetFieldLabel extends StatelessWidget {
  const _ResetFieldLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Text(
        text,
        style: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.ustpDark,
        ),
      );
}
