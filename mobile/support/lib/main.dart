import 'package:flutter/material.dart';
import 'api.dart';
import 'store.dart';
import 'theme.dart';
import 'screens/login_screen.dart';
import 'screens/home_screen.dart';
import 'update_checker.dart';

void main() => runApp(const SoorinApp());

class SoorinApp extends StatelessWidget {
  const SoorinApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'پشتیبانِ سورین',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      // کلِ اپ راست‌به‌چپ.
      builder: (context, child) => Directionality(
        textDirection: TextDirection.rtl,
        child: child!,
      ),
      home: const _Bootstrap(),
    );
  }
}

/// تصمیم‌گیریِ آغازین: توکنِ ذخیره‌شده هست → خانه؛ وگرنه ورود. + بررسیِ نسخهٔ اپ.
class _Bootstrap extends StatefulWidget {
  const _Bootstrap();
  @override
  State<_Bootstrap> createState() => _BootstrapState();
}

class _BootstrapState extends State<_Bootstrap> {
  @override
  void initState() {
    super.initState();
    _decide();
  }

  Future<void> _decide() async {
    final token = await Store.token();
    final cachedUser = await Store.user();

    if (!mounted) return;

    if (token != null && cachedUser != null) {
      _go(HomeScreen(user: cachedUser));
      // اعتبارسنجیِ توکن در پس‌زمینه — اگر باطل بود، به ورود برگرد.
      Api.me().then((u) => Store.setUser(u)).catchError((e) {
        if (e is ApiException && e.status == 401) _logoutToLogin();
        return <String, dynamic>{};
      });
    } else {
      _go(const LoginScreen());
    }

    // بررسیِ نسخهٔ اپ (بی‌صدا؛ اگر تازه‌تری بود پیام می‌دهد).
    UpdateChecker.check(context);
  }

  void _go(Widget screen) {
    Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => screen));
  }

  Future<void> _logoutToLogin() async {
    await Store.clearToken();
    await Store.clearUser();
    if (mounted) _go(const LoginScreen());
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
