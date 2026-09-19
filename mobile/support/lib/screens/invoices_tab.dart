import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:open_filex/open_filex.dart';
import '../api.dart';
import '../theme.dart';

/// تبِ فاکتورهای اپِ پشتیبان — همهٔ فاکتورها با جستجو (شماره/نامِ مشتری) و
/// نمایشِ PDF (اگر مجوزِ چاپ باشد).
class InvoicesTab extends StatefulWidget {
  const InvoicesTab({super.key});
  @override
  State<InvoicesTab> createState() => _InvoicesTabState();
}

class _InvoicesTabState extends State<InvoicesTab> {
  List _items = [];
  bool _loading = true;
  String? _error;
  int? _opening;
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
      final res = await Api.invoices(search: _searchCtrl.text.trim());
      if (mounted) setState(() { _items = res['data'] as List; _error = null; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), _load);
  }

  Future<void> _openPdf(Map<String, dynamic> inv) async {
    setState(() => _opening = inv['id'] as int);
    try {
      final bytes = await Api.invoicePdf(inv['id'] as int);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/invoice_${inv['id']}.pdf');
      await file.writeAsBytes(bytes, flush: true);
      final res = await OpenFilex.open(file.path, type: 'application/pdf');
      if (res.type != ResultType.done && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('برای نمایشِ PDF یک برنامهٔ نمایش‌گر لازم است.')));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: AppTheme.danger));
    } finally {
      if (mounted) setState(() => _opening = null);
    }
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
              hintText: 'جستجو (شماره فاکتور یا نامِ مشتری)',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _searchCtrl.text.isEmpty
                  ? null
                  : IconButton(icon: const Icon(Icons.clear), onPressed: () { _searchCtrl.clear(); _load(); }),
              isDense: true,
            ),
          ),
        ),
        Expanded(child: _list()),
      ],
    );
  }

  Widget _list() {
    if (_error != null) return _ErrorView(_error!, _load);
    if (_loading) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: _load,
      child: _items.isEmpty
          ? ListView(children: const [SizedBox(height: 120), Center(child: Text('فاکتوری نیست'))])
          : ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: _items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (_, i) => _tile(_items[i] as Map<String, dynamic>),
            ),
    );
  }

  Widget _tile(Map<String, dynamic> inv) {
    final remaining = (inv['remaining'] ?? 0) as int;
    final canPrint = inv['can_print'] == true;
    final opening = _opening == inv['id'];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Expanded(child: Text('فاکتور ${inv['number'] ?? ''}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15))),
              _badge(inv['status_label'] ?? '', AppTheme.invoiceStatusColor(inv['status'] ?? '')),
            ]),
            const SizedBox(height: 6),
            Row(children: [
              const Icon(Icons.business, size: 15, color: Colors.black45),
              const SizedBox(width: 4),
              Expanded(child: Text(inv['customer'] ?? '—', style: const TextStyle(fontSize: 12, color: Colors.black54))),
              const Icon(Icons.event, size: 15, color: Colors.black45),
              const SizedBox(width: 4),
              Text(inv['issue_date'] ?? '', style: const TextStyle(fontSize: 12, color: Colors.black54)),
            ]),
            if (inv['ticket_number'] != null) ...[
              const SizedBox(height: 4),
              Row(children: [
                const Icon(Icons.confirmation_number_outlined, size: 15, color: Colors.black45),
                const SizedBox(width: 4),
                Text(inv['ticket_number'], style: const TextStyle(fontSize: 12, color: Colors.black54)),
              ]),
            ],
            const Divider(height: 20),
            Row(children: [
              const Text('قابلِ پرداخت: ', style: TextStyle(fontSize: 13, color: Colors.black54)),
              Text(inv['payable_fa'] ?? '${inv['payable'] ?? 0}', style: const TextStyle(fontWeight: FontWeight.w600)),
              const Spacer(),
              if (remaining > 0)
                Text('مانده: $remaining ریال', style: const TextStyle(fontSize: 12, color: AppTheme.danger)),
            ]),
            if (canPrint) ...[
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerLeft,
                child: OutlinedButton.icon(
                  onPressed: opening ? null : () => _openPdf(inv),
                  icon: opening
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.picture_as_pdf_outlined, size: 18),
                  label: const Text('نمایشِ PDF'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _badge(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
      );
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
