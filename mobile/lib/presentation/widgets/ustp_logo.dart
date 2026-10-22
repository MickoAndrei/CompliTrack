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
          SizedBox(
            width: fontSize * 1.5,
            height: fontSize * 1.5,
            child: const CustomPaint(painter: _BrandEmblemPainter()),
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

class _BrandEmblemPainter extends CustomPainter {
  const _BrandEmblemPainter();

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / 40, size.height / 40);
    final outer = Paint()
      ..color = const Color(0xFFFAB005)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;
    final circle = Path()
      ..addOval(Rect.fromCircle(center: const Offset(20, 20), radius: 18.5));
    for (final metric in circle.computeMetrics()) {
      for (double distance = 0; distance < metric.length; distance += 4.5) {
        canvas.drawPath(metric.extractPath(distance, distance + 2.5), outer);
      }
    }
    canvas.drawCircle(
      const Offset(20, 20),
      14.5,
      Paint()
        ..color = const Color(0xFF00205B)
        ..style = PaintingStyle.fill,
    );
    canvas.drawCircle(
      const Offset(20, 20),
      14.5,
      Paint()
        ..color = const Color(0xFF3B5998)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 0.8,
    );
    final crest = Path()
      ..moveTo(20, 8)
      ..lineTo(24, 15)
      ..lineTo(16, 15)
      ..close();
    canvas.drawPath(crest, Paint()..color = Colors.white.withValues(alpha: .85));
    final label = TextPainter(
      text: const TextSpan(
        text: 'USTP',
        style: TextStyle(
          color: Color(0xFFFAB005),
          fontFamily: 'sans-serif',
          fontSize: 7.5,
          fontWeight: FontWeight.w800,
          letterSpacing: .4,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    label.paint(canvas, Offset(20 - label.width / 2, 22.25));
    final lowerArc = Path()
      ..moveTo(11, 30)
      ..cubicTo(15, 33, 25, 33, 29, 30);
    canvas.drawPath(
      lowerArc,
      Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.2
        ..strokeCap = StrokeCap.round,
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
