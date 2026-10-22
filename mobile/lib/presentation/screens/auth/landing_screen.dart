import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';

class LandingScreen extends StatelessWidget {
  const LandingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpNavy,
      body: Stack(
        children: [
          const Positioned(
            top: -42,
            right: -38,
            child: _BackdropCircle(diameter: 138),
          ),
          const Positioned(
            left: -42,
            top: 465,
            child: _BackdropCircle(diameter: 184),
          ),
          SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxHeight < 650;
                return SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 48, 20, 16),
                  child: ConstrainedBox(
                    constraints:
                        BoxConstraints(minHeight: constraints.maxHeight - 64),
                    child: IntrinsicHeight(
                      child: Column(
                        children: [
                          const _CampusEmblem(),
                          const SizedBox(height: 12),
                          const Text.rich(
                            TextSpan(children: [
                              TextSpan(
                                  text: 'Compli',
                                  style: TextStyle(color: AppColors.ustpWhite)),
                              TextSpan(
                                  text: 'Track',
                                  style: TextStyle(color: AppColors.ustpGold)),
                            ]),
                            style: TextStyle(
                                fontFamily: 'Poppins',
                                fontSize: 24,
                                fontWeight: FontWeight.w700,
                                height: 1.2),
                          ),
                          const SizedBox(height: 3),
                          const Text(
                            'CLAVERIA CAMPUS',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 9,
                                color: Color(0xFFB8C6DC)),
                          ),
                          SizedBox(height: compact ? 24 : 29),
                          const Text(
                            'Regulatory Compliance &\nAccountability, in one place',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                                fontFamily: 'Poppins',
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                color: AppColors.ustpWhite,
                                height: 1.5),
                          ),
                          SizedBox(height: compact ? 22 : 25),
                          const _FeatureRow('Real-Time Audit Tracking',
                              'Instantly see active, open, and closed audit items.'),
                          const SizedBox(height: 12),
                          const _FeatureRow('Departmental Status Reports',
                              'Get detailed compliance overview maps of your departments.'),
                          const SizedBox(height: 12),
                          const _FeatureRow('End-to-End Encryption',
                              'Highly secure file vaults for critical government documentation.'),
                          const Spacer(),
                          const SizedBox(height: 26),
                          SizedBox(
                            width: double.infinity,
                            height: 40,
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.ustpGold,
                                foregroundColor: AppColors.ustpWhite,
                                elevation: 0,
                                padding: EdgeInsets.zero,
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(10)),
                              ),
                              onPressed: () => context.go('/login'),
                              child: const Text('Log In to Portal',
                                  style: TextStyle(
                                      fontFamily: 'Inter',
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700)),
                            ),
                          ),
                          const SizedBox(height: 9),
                          SizedBox(
                            width: double.infinity,
                            height: 40,
                            child: OutlinedButton(
                              style: OutlinedButton.styleFrom(
                                padding: EdgeInsets.zero,
                                side: const BorderSide(
                                    color: AppColors.ustpWhite, width: 1),
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(10)),
                              ),
                              onPressed: () =>
                                  ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                    content:
                                        Text('Waitlist request submitted')),
                              ),
                              child: const Text('Join Waitlist',
                                  style: TextStyle(
                                      fontFamily: 'Inter',
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.ustpWhite)),
                            ),
                          ),
                          const SizedBox(height: 84),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _BackdropCircle extends StatelessWidget {
  final double diameter;
  const _BackdropCircle({required this.diameter});

  @override
  Widget build(BuildContext context) => Container(
        width: diameter,
        height: diameter,
        decoration: const BoxDecoration(
            color: Color(0xFF102F68), shape: BoxShape.circle),
      );
}

class _CampusEmblem extends StatelessWidget {
  const _CampusEmblem();

  @override
  Widget build(BuildContext context) => SizedBox(
        width: 40,
        height: 40,
        child: Image.asset(
          'assets/images/ustp-logo-mark.png',
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
        ),
      );
}

class _FeatureRow extends StatelessWidget {
  final String title;
  final String description;
  const _FeatureRow(this.title, this.description);

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 1),
            width: 16,
            height: 16,
            decoration: const BoxDecoration(
                color: Color(0xFF17376D), shape: BoxShape.circle),
            child: const Icon(Icons.check, size: 11, color: AppColors.ustpGold),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ustpWhite,
                        height: 1.3)),
                const SizedBox(height: 1),
                Text(description,
                    style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 9,
                        color: Color(0xFF899BB8),
                        height: 1.3)),
              ],
            ),
          ),
        ],
      );
}
