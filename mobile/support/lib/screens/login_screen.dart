import 'package:flutter/material.dart';
import '../api.dart';
import '../store.dart';
import '../theme.dart';
import 'home_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _id = TextEditingController();
  final _pass = TextEditingController();
  final _url = TextEditingController();
  bool _loading = false;
  bool _showServer = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    Store.baseUrl().then((v) => _url.text = v);
  }

  Future<void> _login() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      if (_showServer && _url.text.trim().isNotEmpty) {
        await Store.setBaseUrl(_url.text.trim());
      }
      final res = await Api.login(_id.text.trim(), _pass.text, 'اپِ اندروید');
      await Store.setToken(res['token'] as String);
      await Store.setUser(res['user'] as Map<String, dynamic>);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => HomeScreen(user: res['user'] as Map<String, dynamic>)));
    } catch (e) {
      setState(() => _error = e is ApiException ? e.message : 'خطا در اتصال به سرور');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.nav,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.headset_mic, size: 64, color: Colors.white),
                const SizedBox(height: 12),
                const Text('پشتیبانِ سورین',
                    style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                const Text('ورود به پنلِ پشتیبانی', style: TextStyle(color: Colors.white70)),
                const SizedBox(height: 28),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      children: [
                        if (_error != null)
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(10),
                            margin: const EdgeInsets.only(bottom: 12),
                            decoration: BoxDecoration(
                                color: AppTheme.danger.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(10)),
                            child: Text(_error!, style: const TextStyle(color: AppTheme.danger)),
                          ),
                        TextField(
                          controller: _id,
                          textDirection: TextDirection.ltr,
                          decoration: const InputDecoration(
                              labelText: 'نام کاربری / ایمیل / موبایل', prefixIcon: Icon(Icons.person_outline)),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _pass,
                          obscureText: true,
                          onSubmitted: (_) => _login(),
                          decoration: const InputDecoration(
                              labelText: 'گذرواژه', prefixIcon: Icon(Icons.lock_outline)),
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _loading ? null : _login,
                          child: _loading
                              ? const SizedBox(
                                  height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Text('ورود'),
                        ),
                        TextButton(
                          onPressed: () => setState(() => _showServer = !_showServer),
                          child: Text(_showServer ? 'پنهان‌کردنِ آدرسِ سرور' : 'آدرسِ سرور',
                              style: const TextStyle(fontSize: 12)),
                        ),
                        if (_showServer)
                          TextField(
                            controller: _url,
                            textDirection: TextDirection.ltr,
                            decoration: const InputDecoration(labelText: 'آدرسِ سرور', hintText: 'https://…'),
                          ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
