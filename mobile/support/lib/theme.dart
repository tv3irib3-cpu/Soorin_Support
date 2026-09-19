import 'package:flutter/material.dart';

/// رنگ‌ها هم‌راستا با تمِ «ocean»ِ سامانه (فیروزه‌ای).
class AppTheme {
  static const accent = Color(0xFF14B8A6);
  static const nav = Color(0xFF0F2D4D);
  static const danger = Color(0xFFDC2626);
  static const warning = Color(0xFFB45309);
  static const success = Color(0xFF059669);
  static const info = Color(0xFF2563EB);

  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: accent, primary: accent),
      scaffoldBackgroundColor: const Color(0xFFEEF4F6),
      fontFamily: 'Vazirmatn',
    );
    return base.copyWith(
      appBarTheme: const AppBarTheme(
        backgroundColor: nav,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: Color(0xFFDDE8EC)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: accent,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(50),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
    );
  }

  /// رنگِ نشانِ وضعیتِ تیکت — یک‌دست با پنلِ وب.
  static Color statusColor(String status) {
    switch (status) {
      case 'new':
        return info;
      case 'in_progress':
      case 'waiting_support':
        return warning;
      case 'waiting_payment':
        return danger;
      case 'resolved':
        return success;
      default:
        return const Color(0xFF64748B);
    }
  }

  static Color priorityColor(String p) {
    switch (p) {
      case 'critical':
        return danger;
      case 'high':
        return warning;
      case 'normal':
        return info;
      default:
        return const Color(0xFF94A3B8);
    }
  }

  /// رنگِ نشانِ وضعیتِ فاکتور.
  static Color invoiceStatusColor(String status) {
    switch (status) {
      case 'paid':
        return success;
      case 'partially_paid':
        return warning;
      case 'cancelled':
        return const Color(0xFF64748B);
      case 'draft':
        return const Color(0xFF94A3B8);
      default: // issued …
        return danger;
    }
  }
}
