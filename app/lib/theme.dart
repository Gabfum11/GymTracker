import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// I colori del mockup "Sala pesi" (convertiti da OKLCH).
/// Regola: lime = azioni, arancione = solo record.
abstract final class AppColors {
  static const bg = Color(0xFF07090C);
  static const screen = Color(0xFF0D1013);
  static const surface = Color(0xFF15191D);
  static const surface2 = Color(0xFF20242A);
  static const rule = Color(0xFF2A2E34);
  static const ink = Color(0xFFF3F5F8);
  static const muted = Color(0xFF9A9FA6);
  static const faint = Color(0xFF70757C);
  static const lime = Color(0xFFC1EE66);
  static const limeSoft = Color(0xFF324114);
  static const onLime = Color(0xFF161E08);
  static const record = Color(0xFFFD923E);
  static const recordSoft = Color(0xFF3C220F);
  static const onRecord = Color(0xFF291507);
}

/// Barlow Condensed per titoli e numeri, Barlow per il testo.
TextStyle displayStyle({double size = 24, FontWeight weight = FontWeight.w700, Color color = AppColors.ink}) =>
    GoogleFonts.barlowCondensed(fontSize: size, fontWeight: weight, color: color);

ThemeData buildTheme() {
  final base = ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    fontFamily: GoogleFonts.barlow().fontFamily, // carattere di base di tutto il testo
    scaffoldBackgroundColor: AppColors.screen,
    colorScheme: const ColorScheme.dark(
      primary: AppColors.lime,
      onPrimary: AppColors.onLime,
      secondary: AppColors.record,
      onSecondary: AppColors.onRecord,
      surface: AppColors.surface,
      onSurface: AppColors.ink,
      outline: AppColors.rule,
    ),
  );

  return base.copyWith(
    textTheme: base.textTheme.apply(bodyColor: AppColors.ink, displayColor: AppColors.ink),
    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.screen,
      foregroundColor: AppColors.ink,
      elevation: 0,
      titleTextStyle: displayStyle(size: 30),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: AppColors.screen,
      indicatorColor: AppColors.limeSoft,
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(color: states.contains(WidgetState.selected) ? AppColors.lime : AppColors.faint),
      ),
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => GoogleFonts.barlow(
          fontSize: 13,
          fontWeight: states.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w600,
          color: states.contains(WidgetState.selected) ? AppColors.ink : AppColors.faint,
        ),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.lime,
        foregroundColor: AppColors.onLime,
        minimumSize: const Size(48, 48), // pulsanti grandi: si usano con le mani sudate
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.ink,
        backgroundColor: AppColors.surface2,
        side: const BorderSide(color: AppColors.rule),
        minimumSize: const Size(48, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
  );
}
