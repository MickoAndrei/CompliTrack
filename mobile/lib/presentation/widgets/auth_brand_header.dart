import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';

class AuthBrandHeader extends StatelessWidget {
  const AuthBrandHeader({super.key});

  @override
  Widget build(BuildContext context) => SizedBox(
        height: 220,
        width: double.infinity,
        child: Stack(
          clipBehavior: Clip.hardEdge,
          children: [
            const Positioned.fill(
              child: ColoredBox(color: AppColors.ustpNavy),
            ),
            const Positioned(
              top: -48,
              right: -48,
              child: _HeaderCircle(diameter: 138),
            ),
            const Positioned(
              left: -30,
              top: 110,
              child: _HeaderCircle(diameter: 140),
            ),
            Positioned(
              left: 20,
              bottom: 20,
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
                  const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text.rich(
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
                          fontSize: 18,
                          height: 1.15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 2),
                      Text(
                        'CLAVERIA CAMPUS',
                        style: TextStyle(
                          fontFamily: 'Inter',
                          fontSize: 9,
                          height: 1.2,
                          letterSpacing: .3,
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
      );
}

class _HeaderCircle extends StatelessWidget {
  const _HeaderCircle({required this.diameter});
  final double diameter;

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
