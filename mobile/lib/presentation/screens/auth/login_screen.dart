import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/role_helper.dart';
import '../../providers/auth_provider.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _rememberMe = true;
  bool _isLoading = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    setState(() => _isLoading = true);
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    var success = false;
    var errorMessage =
        'Unable to sign in. Check your connection and try again.';
    try {
      success = await authProvider.login(
        _emailController.text.trim(),
        _passwordController.text,
      );
    } on DioException catch (error) {
      if (error.response?.statusCode == 401 ||
          error.response?.statusCode == 422) {
        errorMessage = 'Invalid email or password.';
      }
    } on FormatException {
      errorMessage = 'The server returned an invalid login response.';
    }

    if (mounted) {
      setState(() => _isLoading = false);
      if (success) {
        context.go(RoleHelper.getDashboardRoute(authProvider.roleId));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(errorMessage)),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    const overlayStyle = SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
      statusBarBrightness: Brightness.dark,
      systemNavigationBarColor: AppColors.ustpWhite,
      systemNavigationBarIconBrightness: Brightness.dark,
    );

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: overlayStyle,
      child: Scaffold(
        backgroundColor: AppColors.ustpWhite,
        body: Column(
          children: [
            SizedBox(
              height: 188,
              width: double.infinity,
              child: Stack(
                clipBehavior: Clip.hardEdge,
                children: [
                  const Positioned.fill(
                    child: ColoredBox(color: AppColors.ustpNavy),
                  ),
                  const Positioned(
                    top: -22,
                    right: -24,
                    child: _LoginBackdropCircle(diameter: 104),
                  ),
                  const Positioned(
                    left: -34,
                    bottom: -8,
                    child: _LoginBackdropCircle(diameter: 112),
                  ),
                  Positioned(
                    left: 20,
                    bottom: 16,
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Image.asset(
                          'assets/images/ustp-logo-mark.png',
                          width: 22,
                          height: 27,
                          fit: BoxFit.contain,
                          filterQuality: FilterQuality.high,
                        ),
                        const SizedBox(width: 10),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Text.rich(
                              TextSpan(children: [
                                TextSpan(
                                  text: 'Compli',
                                  style: TextStyle(color: AppColors.ustpWhite),
                                ),
                                TextSpan(
                                  text: 'Track',
                                  style: TextStyle(color: AppColors.ustpGold),
                                ),
                              ]),
                              style: TextStyle(
                                fontFamily: 'Poppins',
                                fontSize: 14,
                                height: 1.2,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 2),
                            const Text(
                              'CLAVERIA CAMPUS',
                              style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 8,
                                height: 1.2,
                                color: Color(0xFFB8C6DC),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: LayoutBuilder(
                builder: (context, constraints) => SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(18, 16, 18, 20),
                  child: ConstrainedBox(
                    constraints:
                        BoxConstraints(minHeight: constraints.maxHeight - 36),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'USTP Claveria Campus',
                          style: TextStyle(
                            fontFamily: 'Poppins',
                            fontSize: 16,
                            height: 1.35,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ustpDark,
                          ),
                        ),
                        const SizedBox(height: 3),
                        const Text(
                          'Regulatory Compliance & Accountability in one place.',
                          style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 10,
                            height: 1.4,
                            color: AppColors.ustpMuted,
                          ),
                        ),
                        const SizedBox(height: 18),
                        const _LoginFieldLabel('Email'),
                        const SizedBox(height: 4),
                        TextField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 11,
                            color: AppColors.ustpDark,
                          ),
                          decoration: _loginInputDecoration(
                            hintText: 'username@ustp.edu.ph',
                          ),
                        ),
                        const SizedBox(height: 11),
                        const _LoginFieldLabel('Password'),
                        const SizedBox(height: 4),
                        TextField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          textInputAction: TextInputAction.done,
                          onSubmitted: (_) {
                            if (!_isLoading) _handleLogin();
                          },
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 11,
                            color: AppColors.ustpDark,
                          ),
                          decoration: _loginInputDecoration(
                            hintText: '••••••••••••',
                            suffixIcon: IconButton(
                              visualDensity: VisualDensity.compact,
                              padding: EdgeInsets.zero,
                              constraints: const BoxConstraints.tightFor(
                                width: 36,
                                height: 36,
                              ),
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                                color: AppColors.ustpMuted,
                                size: 16,
                              ),
                              onPressed: () => setState(
                                () => _obscurePassword = !_obscurePassword,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            SizedBox(
                              width: 20,
                              height: 22,
                              child: Checkbox(
                                value: _rememberMe,
                                activeColor: AppColors.ustpNavy,
                                materialTapTargetSize:
                                    MaterialTapTargetSize.shrinkWrap,
                                visualDensity: VisualDensity.compact,
                                onChanged: (value) => setState(
                                  () => _rememberMe = value ?? false,
                                ),
                              ),
                            ),
                            const SizedBox(width: 4),
                            const Text(
                              'Remember Me',
                              style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 9,
                                color: AppColors.ustpMuted,
                              ),
                            ),
                            const Spacer(),
                            GestureDetector(
                              onTap: () => context.push('/forgot-password'),
                              child: const Padding(
                                padding: EdgeInsets.symmetric(vertical: 4),
                                child: Text(
                                  'Forgot password?',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 9,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.ustpNavy,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 11),
                        SizedBox(
                          width: double.infinity,
                          height: 38,
                          child: ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.ustpGold,
                              foregroundColor: AppColors.ustpDark,
                              padding: EdgeInsets.zero,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                            ),
                            onPressed: _isLoading ? null : _handleLogin,
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
                                    'Log In',
                                    style: TextStyle(
                                      fontFamily: 'Inter',
                                      fontSize: 10,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                          ),
                        ),
                        const SizedBox(height: 11),
                        const Text(
                          'Accounts are provisioned by your Department Admin. Contact your Super Admin if you need access.',
                          style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 8,
                            height: 1.45,
                            color: AppColors.ustpMuted,
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
  }
}

class _LoginBackdropCircle extends StatelessWidget {
  final double diameter;
  const _LoginBackdropCircle({required this.diameter});

  @override
  Widget build(BuildContext context) => Container(
        width: diameter,
        height: diameter,
        decoration: const BoxDecoration(
          color: Color(0xFF102F68),
          shape: BoxShape.circle,
        ),
      );
}

class _LoginFieldLabel extends StatelessWidget {
  final String text;
  const _LoginFieldLabel(this.text);

  @override
  Widget build(BuildContext context) => Text(
        text,
        style: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 10,
          fontWeight: FontWeight.w600,
          color: AppColors.ustpDark,
        ),
      );
}

InputDecoration _loginInputDecoration({String? hintText, Widget? suffixIcon}) =>
    InputDecoration(
      hintText: hintText,
      suffixIcon: suffixIcon,
      isDense: true,
      filled: true,
      fillColor: AppColors.ustpWhite,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      hintStyle: const TextStyle(
        fontFamily: 'Inter',
        fontSize: 11,
        color: AppColors.ustpDark,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFDCE3EC)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFDCE3EC)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppColors.ustpNavy, width: 1),
      ),
    );
