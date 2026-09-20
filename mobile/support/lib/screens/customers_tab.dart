import 'dart:async';
import 'package:flutter/material.dart';
import '../api.dart';
import '../theme.dart';
import 'customer_detail_screen.dart';

/// تبِ مشتریانِ اپِ پشتیبان — فهرست با جستجو (نام/کد/شهر/تلفن) و شمارندهٔ تیکتِ
/// باز و فاکتورِ پرداخت‌نشده. با لمس، صفحهٔ جزئیاتِ مشتری باز می‌شود.
class CustomersTab extends StatefulWidget {
  final bool canInvoice;
  const CustomersTab({super.key, this.canInvoice = false});
  @override
  State<CustomersTab> createState() => _CustomersTabState();
}

class _CustomersTabState extends State<CustomersTab> {
  List _items = [];
  bool _loading = true;
  String? _error;
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await Api.customers(search: _searchCtrl.text.trim());
      if (mounted) setState(() { _items = res['data'] as List; _error = null; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), _load);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 6),
          child: TextField(
            controller: _searchCtrl,
            onChanged: _onSearchChanged,
            decoration: InputDecoration(
              hintText: 'جستجوی مشتری (نام / کد / شهر / تلفن)',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _searchCtrl.text.isEmpty
                  ? null
                  : IconButton(icon: const Icon(Icons.clear), onPressed: () { _searchCtrl.clear(); _load(); }),
              isDense: true,
            ),
          ),
        ),
        Expanded(
          child: _error != null
              ? _ErrorView(_error!, _load)
              : _loading
                  ? const Center(child: CircularProgressIndicator())
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: _items.isEmpty
                          ? ListView(children: const [SizedBox(height: 120), Center(child: Text('مشتری‌ای نیست'))])
                          : ListView.separated(
                              padding: const EdgeInsets.all(12),
                              itemCount: _items.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (_, i) => _tile(_items[i] as Map<String, dynamic>),
                            ),
                    ),
        ),
      ],
    );
  }

  Widget _tile(Map<String, dynamic> c) {
    final color = _hex(c['color']) ?? AppTheme.accent;
    final open = (c['open_tickets'] ?? 0) as int;
    final unpaid = (c['unpaid_invoices'] ?? 0) as int;
    return Card(
      child: ListTile(
        onTap: () => Navigator.push(context,
            MaterialPageRoute(builder: (_) => CustomerDetailScreen(id: c['id'] as int, name: c['name'] ?? '', canInvoice: widget.canInvoice))),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        leading: CircleAvatar(
          backgroundColor: color.withOpacity(0.15),
          child: Text(_initial(c['name']),
              style: TextStyle(color: color, fontWeight: FontWeight.bold)),
        ),
        title: Text(c['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Wrap(spacing: 8, runSpacing: 4, children: [
            if (c['code'] != null) Text('کد: ${c['code']}', style: const TextStyle(fontSize: 11, color: Colors.black54)),
            if (c['city'] != null) Text(c['city'], style: const TextStyle(fontSize: 11, color: Colors.black54)),
            if (c['is_active'] != true) _badge('معلق', AppTheme.danger),
          ]),
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            if (open > 0) _badge('$open تیکتِ باز', AppTheme.warning),
            if (unpaid > 0) ...[
              const SizedBox(height: 4),
              _badge('$unpaid فاکتورِ باز', AppTheme.danger),
            ],
          ],
        ),
      ),
    );
  }

  Widget _badge(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
      );

  String _initial(dynamic name) {
    final s = (name ?? '').toString().trim();
    return s.isEmpty ? '؟' : s.substring(0, 1);
  }

  Color? _hex(dynamic v) {
    if (v is! String || !v.startsWith('#') || v.length < 7) return null;
    return Color(int.parse('FF${v.substring(1)}', radix: 16));
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const _ErrorView(this.message, this.onRetry);
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.redAccent),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton(onPressed: onRetry, child: const Text('تلاشِ دوباره')),
        ]),
      ),
    );
  }
}
