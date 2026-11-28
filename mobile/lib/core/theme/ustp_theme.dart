import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import '../constants/app_colors.dart';

abstract final class UstpTheme {
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: AppColors.ustpLight,
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppColors.ustpNavy,
        primary: AppColors.ustpNavy,
        secondary: AppColors.ustpGold,
        surface: AppColors.ustpWhite,
        error: AppColors.error,
        onPrimary: AppColors.ustpWhite,
        onSecondary: AppColors.ustpDark,
        onSurface: AppColors.ustpDark,
      ),
      scrollbarTheme: ScrollbarThemeData(
        thickness: const WidgetStatePropertyAll(6),
        radius: const Radius.circular(8),
        thumbColor: WidgetStateProperty.resolveWith((states) =>
          states.contains(WidgetState.hovered) || states.contains(WidgetState.dragged)
            ? const Color(0xFF62666F) : const Color(0xFF858A94)),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.ustpWhite,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.ustpNavy,
        foregroundColor: AppColors.ustpWhite,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        systemOverlayStyle: SystemUiOverlayStyle.light,
      ),
      cardTheme: CardThemeData(
        color: AppColors.ustpWhite,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Color(0xFFE4E5E8)),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.ustpNavy,
          foregroundColor: AppColors.ustpWhite,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: GoogleFonts.inter(
            fontSize: 14,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.ustpLight,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.ustpNavy, width: 1.5),
        ),
      ),
    );
  }
}
