import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import 'api.dart';

/// بررسیِ به‌روزرسانیِ اپ. نسخهٔ نصب‌شده را با نسخهٔ سرور مقایسه می‌کند.
/// اگر تازه‌تر بود، پیام می‌دهد؛ اگر پایین‌تر از «min_supported» بود، آپدیت اجباری.
class UpdateChecker {
  static Future<void> check(BuildContext context) async {
    try {
      final info = await Api.appVersion();
      final pkg = await PackageInfo.fromPlatform();
      final current = pkg.version;

      final latest = info['version'] as String? ?? current;
      final min = info['min_supported'] as String? ?? '0.0.0';
      final apkUrl = info['apk_url'] as String? ?? '';

      final mustUpdate = _cmp(current, min) < 0;
      final hasUpdate = _cmp(current, latest) < 0;

      if (!hasUpdate || !context.mounted) return;

      _showDialog(context, latest, apkUrl, mustUpdate, info['notes'] as String?);
    } catch (_) {
      // بی‌صدا — نبودِ اینترنت نباید بازکردنِ اپ را مختل کند.
    }
  }

  static void _showDialog(BuildContext context, String latest, String apkUrl, bool mustUpdate, String? notes) {
    showDialog(
      context: context,
      barrierDismissible: !mustUpdate,
      builder: (ctx) => PopScope(
        canPop: !mustUpdate,
        child: AlertDialog(
          title: const Text('نسخهٔ جدید آماده است'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('نسخهٔ $latest منتشر شده است.'),
              if (notes != null && notes.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(notes, style: const TextStyle(color: Colors.black54, fontSize: 13)),
              ],
              if (mustUpdate) ...[
                const SizedBox(height: 8),
                const Text('برای ادامه باید به‌روزرسانی کنید.',
                    style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold)),
              ],
            ],
          ),
          actions: [
            if (!mustUpdate)
              TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('بعداً')),
            FilledButton(
              onPressed: () async {
                final uri = Uri.parse(apkUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
              child: const Text('دانلود و نصب'),
            ),
          ],
        ),
      ),
    );
  }

  /// مقایسهٔ نسخهٔ «x.y.z» — منفی اگر a < b.
  static int _cmp(String a, String b) {
    final pa = a.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    final pb = b.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    for (var i = 0; i < 3; i++) {
      final x = i < pa.length ? pa[i] : 0;
      final y = i < pb.length ? pb[i] : 0;
      if (x != y) return x - y;
    }
    return 0;
  }
}
