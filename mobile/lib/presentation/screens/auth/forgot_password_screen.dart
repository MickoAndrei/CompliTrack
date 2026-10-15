import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/repositories/auth_repository.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _emailController = TextEditingController();
  final _tokenController = TextEditingController();
  final _passwordController = TextEditingController();
  final _repository = AuthRepository();
  bool _isSubmitted = false;
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    _tokenController.dispose();
    _passwordController.dispose();
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

  Future<void> _resetPassword() async {
    final token = _tokenController.text.trim();
    final password = _passwordController.text;
    if (token.length != 64 || password.length < 12) {
      setState(() => _errorMessage = 'Enter the 64-character reset code and a password of at least 12 characters.');
      return;
    }
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });
    try {
      await _repository.resetPassword(
        email: _emailController.text.trim(),
        token: token,
        password: password,
      );
      if (mounted) context.go('/login');
    } on DioException catch (error) {
      if (mounted) {
        final body = error.response?.data;
        setState(() {
          _errorMessage = body is Map && body['message'] is String
              ? body['message'] as String
              : 'The reset code is invalid or expired.';
        });
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Dark Header Banner
            Container(
              padding: const EdgeInsets.only(top: 60, bottom: 32, left: 24, right: 24),
              color: AppColors.ustpNavy,
              child: Row(
                children: [
                  RichText(
                    text: const TextSpan(
                      style: TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                      ),
                      children: [
                        TextSpan(text: 'Compli', style: TextStyle(color: AppColors.ustpWhite)),
                        TextSpan(text: 'Track', style: TextStyle(color: AppColors.ustpGold)),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  const Text(
                    'CLAVERIA CAMPUS',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: AppColors.ustpMuted,
                      letterSpacing: 0.8,
                    ),
                  ),
                ],
              ),
            ),

            // Form Body
            Container(
              padding: const EdgeInsets.all(24.0),
              color: AppColors.ustpWhite,
              child: Column(
                children: [
                  _isSubmitted ? _buildInboxConfirmation(context) : _buildForgotForm(context),
                  if (_errorMessage != null) ...[
                    const SizedBox(height: 12),
                    Text(_errorMessage!, style: const TextStyle(color: AppColors.error)),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildForgotForm(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        GestureDetector(
          onTap: () => context.pop(),
          child: const Text(
            'Back to log in',
            style: TextStyle(
              fontFamily: 'Inter',
              fontSize: 13,
              color: AppColors.ustpMuted,
            ),
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          'Forgot password?',
          style: TextStyle(
            fontFamily: 'Poppins',
            fontSize: 22,
            fontWeight: FontWeight.bold,
            color: AppColors.ustpDark,
          ),
        ),
        const SizedBox(height: 6),
        const Text(
          'Locked out happens. Getting back in shouldn\'t be hard. Enter your email.',
          style: TextStyle(
            fontFamily: 'Inter',
            fontSize: 13,
            color: AppColors.ustpMuted,
            height: 1.4,
          ),
        ),
        const SizedBox(height: 28),

        // Email Input
        const Text(
          'Email',
          style: TextStyle(
            fontFamily: 'Inter',
            fontSize: 13,
            fontWeight: FontWeight.bold,
            color: AppColors.ustpDark,
          ),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _emailController,
          decoration: InputDecoration(
            hintText: 'user@ustp.edu.ph',
            fillColor: AppColors.ustpWhite,
            filled: true,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: AppColors.ustpMuted.withValues(alpha: 0.3)),
            ),
          ),
        ),
        const SizedBox(height: 24),

        // Send Reset Link Button
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.ustpGold,
              foregroundColor: AppColors.ustpDark,
              padding: const EdgeInsets.symmetric(vertical: 16),
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            onPressed: _isLoading ? null : _requestReset,
            icon: _isLoading
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.mail_outline, size: 18, color: AppColors.ustpDark),
            label: Text(
              _isLoading ? 'Sending…' : 'Send Reset Link',
              style: TextStyle(
                fontFamily: 'Inter',
                fontSize: 15,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          'Reset codes are single-use and expire after 60 minutes.',
          style: TextStyle(
            fontFamily: 'Inter',
            fontSize: 12,
            color: AppColors.ustpMuted,
          ),
        ),
      ],
    );
  }

  Widget _buildInboxConfirmation(BuildContext context) {
    return Column(
      children: [
        const SizedBox(height: 24),
        Container(
          width: 72,
          height: 72,
          decoration: const BoxDecoration(
            color: Color(0xFF2563EB),
            shape: BoxShape.circle,
          ),
          child: const Icon(
            Icons.mail_outline_rounded,
            size: 36,
            color: AppColors.ustpWhite,
          ),
        ),
        const SizedBox(height: 24),
        const Text(
          'Check your inbox',
          style: TextStyle(
            fontFamily: 'Poppins',
            fontSize: 22,
            fontWeight: FontWeight.bold,
            color: AppColors.ustpDark,
          ),
        ),
        const SizedBox(height: 12),
        Text(
          'If an account exists for ${_emailController.text.trim()}, a reset code has been sent. Enter that code below. It expires in 60 minutes.',
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 14,
            color: AppColors.ustpMuted,
            height: 1.4,
          ),
        ),
        const SizedBox(height: 20),
        TextField(
          controller: _tokenController,
          maxLength: 64,
          decoration: const InputDecoration(labelText: 'Reset code'),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _passwordController,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'New password (12+ characters)'),
        ),
        const SizedBox(height: 20),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _isLoading ? null : _resetPassword,
            child: Text(_isLoading ? 'Resetting…' : 'Reset password'),
          ),
        ),
        const SizedBox(height: 16),
        GestureDetector(
          onTap: () => context.pop(),
          child: const Text(
            'Back to log in',
            style: TextStyle(
              fontFamily: 'Inter',
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: AppColors.ustpNavy,
            ),
          ),
        ),
        const SizedBox(height: 24),
      ],
    );
  }
}
