import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';

class UstpLogo extends StatelessWidget {
  final bool showEmblem;
  final bool isLightMode;
  final double fontSize;
  final String? subtitle;

  const UstpLogo({
    super.key,
    this.showEmblem = true,
    this.isLightMode = false,
    this.fontSize = 20,
    this.subtitle = 'CLAVERIA CAMPUS / MOBILE',
  });

  @override
  Widget build(BuildContext context) {
    final textColor = isLightMode ? AppColors.ustpNavy : AppColors.ustpWhite;
    final subtitleColor = isLightMode ? AppColors.ustpMuted : AppColors.navigationMuted;

    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        if (showEmblem) ...[
          Container(
            width: fontSize * 2,
            height: fontSize * 2,
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(6)),
            child: Image.asset(
              'assets/images/ustp-logo-mark.png',
              fit: BoxFit.contain,
            ),
          ),
          SizedBox(width: fontSize * 0.45),
        ],

        // CompliTrack Wordmark & Subtitle Column
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            RichText(
              text: TextSpan(
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: fontSize,
                  fontWeight: FontWeight.bold,
                ),
                children: [
                  TextSpan(
                    text: 'Compli',
                    style: TextStyle(color: textColor),
                  ),
                  const TextSpan(
                    text: 'Track',
                    style: TextStyle(color: AppColors.ustpGold),
                  ),
                ],
              ),
            ),
            if (subtitle != null && subtitle!.isNotEmpty)
              Text(
                subtitle!,
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: fontSize * 0.42,
                  color: subtitleColor,
                  letterSpacing: 0.8,
                  fontWeight: FontWeight.w600,
                ),
              ),
          ],
        ),
      ],
    );
  }
}
