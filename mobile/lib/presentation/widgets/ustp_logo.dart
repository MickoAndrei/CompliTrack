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
    final subtitleColor = AppColors.ustpMuted;

    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        if (showEmblem) ...[
          // USTP Crest Seal Badge
          Container(
            width: fontSize * 1.5,
            height: fontSize * 1.5,
            decoration: BoxDecoration(
              color: AppColors.ustpGold.withValues(alpha: 0.2),
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.ustpGold, width: 1.5),
            ),
            child: Center(
              child: Text(
                'USTP',
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: fontSize * 0.38,
                  fontWeight: FontWeight.w900,
                  color: AppColors.ustpGold,
                  letterSpacing: -0.5,
                ),
              ),
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
