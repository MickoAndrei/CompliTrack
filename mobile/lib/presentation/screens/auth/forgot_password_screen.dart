import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../widgets/auth_brand_header.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _emailController = TextEditingController();
  final _repository = AuthRepository();
  bool _isSubmitted = false;
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _requestReset() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });
    try {
      await _repository.requestPasswordReset(_emailController.text.trim());
      if (mounted) setState(() => _isSubmitted = true);
    } on DioException catch (error) {
      if (mounted) {
        final body = error.response?.data;
        setState(() {
          _errorMessage = body is Map && body['message'] is String
              ? body['message'] as String
              : 'Unable to request a password reset.';
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
                child: _isSubmitted
                    ? _buildInboxConfirmation(context)
                    : _buildForgotForm(context),
              ),
            ],
          ),
        ),
      );

  Widget _buildForgotForm(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) => SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 25, 20, 24),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight - 44),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                GestureDetector(
                  onTap: () => context.go('/login'),
                  child: const Text(
                    'Back to log in',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 13,
                      color: AppColors.ustpMuted,
                    ),
                  ),
                ),
                const SizedBox(height: 27),
                const Text(
                  'Forgot password?',
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
                  'Locked out happens. Getting back in shouldn\'t be hard. Enter your email.',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 12,
                    height: 1.45,
                    color: AppColors.ustpMuted,
                  ),
                ),
                const SizedBox(height: 22),
                const _AuthFieldLabel('Email'),
                const SizedBox(height: 6),
                TextField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _isLoading ? null : _requestReset(),
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 13,
                    color: AppColors.ustpDark,
                  ),
                  decoration: _authInputDecoration('user@ustp.edu.ph'),
                ),
                if (_errorMessage != null) ...[
                  const SizedBox(height: 10),
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
                      padding: EdgeInsets.zero,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(11),
                      ),
                    ),
                    onPressed: _isLoading ? null : _requestReset,
                    child: _isLoading
                        ? const SizedBox(
                            width: 17,
                            height: 17,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.ustpDark,
                            ),
                          )
                        : const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'Send Reset Link',
                                style: TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              SizedBox(width: 8),
                              Icon(Icons.error_outline_rounded, size: 16),
                            ],
                          ),
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Reset links are single-use and expire after 10 minutes.',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 10,
                    height: 1.4,
                    color: AppColors.ustpMuted,
                  ),
                ),
              ],
            ),
          ),
        ),
      );

  Widget _buildInboxConfirmation(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) => SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 88, 20, 24),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight - 112),
            child: Column(
              children: [
                Container(
                  width: 72,
                  height: 72,
                  decoration: const BoxDecoration(
                    color: Color(0xFF2563EB),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.mail_outline_rounded,
                      size: 32, color: AppColors.ustpNavy),
                ),
                const SizedBox(height: 30),
                const Text(
                  'Check your inbox',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 20,
                    height: 1.3,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ustpDark,
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  'We sent a secure link to your email. Click it to reset your password. The link will expire in 10 minutes.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 13,
                    height: 1.55,
                    color: AppColors.ustpMuted,
                  ),
                ),
                const SizedBox(height: 36),
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
                    onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text(
                            'Open your email app and follow the reset link.'),
                      ),
                    ),
                    child: const Text(
                      'Go to Email',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                TextButton(
                  onPressed: () => context.go('/login'),
                  child: const Text(
                    'Back to log in',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 13,
                      color: AppColors.ustpMuted,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
}

class _AuthFieldLabel extends StatelessWidget {
  const _AuthFieldLabel(this.text);
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

InputDecoration _authInputDecoration(String hint) => InputDecoration(
      hintText: hint,
      isDense: true,
      filled: true,
      fillColor: AppColors.ustpWhite,
      contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
      hintStyle: const TextStyle(
        fontFamily: 'Inter',
        fontSize: 13,
        color: AppColors.ustpDark,
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
    );
