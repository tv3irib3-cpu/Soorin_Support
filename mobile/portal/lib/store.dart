import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// نگه‌داریِ توکنِ ماندگار (امن) و تنظیماتِ اپ (آدرسِ سرور، کاربرِ کش‌شده).
/// عیناً مثلِ اپِ پشتیبان — با کلیدهای جدا (اپ مستقل است).
class Store {
  static const _secure = FlutterSecureStorage();
  static const _kToken = 'api_token';
  static const _kBaseUrl = 'base_url';
  static const _kUser = 'user_json';

  /// آدرسِ پیش‌فرضِ سرور — قابلِ تغییر در صفحهٔ ورود.
  static const defaultBaseUrl = 'https://crm.dpst.ir';

  static Future<String?> token() => _secure.read(key: _kToken);
  static Future<void> setToken(String value) => _secure.write(key: _kToken, value: value);
  static Future<void> clearToken() => _secure.delete(key: _kToken);

  static Future<String> baseUrl() async {
    final p = await SharedPreferences.getInstance();
    return p.getString(_kBaseUrl) ?? defaultBaseUrl;
  }

  static Future<void> setBaseUrl(String value) async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_kBaseUrl, value.trim());
  }

  static Future<Map<String, dynamic>?> user() async {
    final p = await SharedPreferences.getInstance();
    final raw = p.getString(_kUser);
    return raw == null ? null : jsonDecode(raw) as Map<String, dynamic>;
  }

  static Future<void> setUser(Map<String, dynamic> value) async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_kUser, jsonEncode(value));
  }

  static Future<void> clearUser() async {
    final p = await SharedPreferences.getInstance();
    await p.remove(_kUser);
  }
}
