import 'dart:io';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:open_filex/open_filex.dart';
import '../api.dart';
import '../theme.dart';

/// جزئیاتِ فاکتور برای پشتیبان: سه عددِ کلیدی، فهرستِ پرداخت‌ها، ثبتِ پرداخت
/// (با مجوز) و نمایشِ PDF.
class InvoiceDetailScreen extends StatefulWidget {
  final int id;
  const InvoiceDetailScreen({super.key, required this.id});
  @override
  State<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends State<InvoiceDetailScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _openingPdf = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d = await Api.invoice(widget.id);
      if (mounted) setState(() { _data = d; _error = null; });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final inv = _data?['invoice'] as Map<String, dynamic>?;
    return Scaffold(
      appBar: AppBar(title: Text(inv == null ? 'فاکتور' : 'فاکتور ${inv['number'] ?? ''}')),
      body: _error != null
          ? Center(child: Text(_error!))
          : _data == null
              ? const Center(child: CircularProgressIndicator())
              : _body(inv!),
      bottomNavigationBar: (inv != null && inv['can_pay'] == true)
          ? SafeArea(child: Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton.icon(
                style: FilledButton.styleFrom(backgroundColor: AppTheme.success),
                onPressed: _addPayment,
                icon: const Icon(Icons.payments_outlined),
                label: const Text('ثبتِ پرداخت'),
              ),
            ))
          : null,
    );
  }

  Widget _body(Map<String, dynamic> inv) {
    final payments = _data!['payments'] as List;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          Card(child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('فاکتور ${inv['number'] ?? ''}', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold))),
                _badge(inv['status_label'] ?? '', AppTheme.invoiceStatusColor(inv['status'] ?? '')),
              ]),
              const SizedBox(height: 6),
              if (inv['customer'] != null) _kv(Icons.business, 'مشتری', inv['customer']),
              if (inv['ticket_number'] != null) _kv(Icons.confirmation_number_outlined, 'تیکت', inv['ticket_number']),
              _kv(Icons.event, 'تاریخِ صدور', inv['issue_date'] ?? '—'),
              const Divider(height: 22),
              _amount('ارزشِ خدمت', inv['service_fa']),
              _amount('سهمِ قرارداد', inv['contract_fa']),
              _amount('قابلِ پرداخت', inv['payable_fa'], bold: true),
              _amount('پرداخت‌شده', inv['paid_fa'], color: AppTheme.success),
              _amount('مانده', inv['remaining_fa'], color: AppTheme.danger, bold: true),
              if (inv['is_warranty'] == true) ...[
                const SizedBox(height: 8),
                _badge('تحتِ گارانتی', AppTheme.info),
              ],
            ]),
          )),
          if (inv['can_print'] == true) ...[
            const SizedBox(height: 4),
            OutlinedButton.icon(
              onPressed: _openingPdf ? null : _openPdf,
              icon: _openingPdf
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.picture_as_pdf_outlined, size: 18),
              label: const Text('نمایشِ PDF'),
            ),
          ],
          const Padding(padding: EdgeInsets.symmetric(vertical: 10), child: Text('پرداخت‌ها', style: TextStyle(fontWeight: FontWeight.bold))),
          if (payments.isEmpty)
            const Card(child: ListTile(title: Text('پرداختی ثبت نشده', style: TextStyle(fontSize: 13))))
          else
            ...payments.map((p) => _paymentTile(p as Map<String, dynamic>)),
          const SizedBox(height: 12),
        ],
      ),
    );
  }

  Widget _paymentTile(Map<String, dynamic> p) => Card(
        child: ListTile(
          dense: true,
          leading: const Icon(Icons.check_circle, color: AppTheme.success),
          title: Text('${p['amount_fa'] ?? ''} — ${p['method_label'] ?? ''}'),
          subtitle: Text([p['paid_at'], if (p['reference'] != null) 'کد: ${p['reference']}', if (p['registrar'] != null) p['registrar']]
              .where((e) => e != null).join(' • '), style: const TextStyle(fontSize: 11)),
        ),
      );

  Future<void> _addPayment() async {
    final inv = _data!['invoice'] as Map<String, dynamic>;
    final methods = (_data!['payment_methods'] as Map).cast<String, dynamic>();
    final amount = TextEditingController(text: '${inv['remaining'] ?? ''}');
    final reference = TextEditingController();
    String method = methods.containsKey('transfer') ? 'transfer' : methods.keys.first;

    final ok = await showModalBottomSheet<bool>(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setSheet) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('ثبتِ پرداخت', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          TextField(controller: amount, keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: 'مبلغ (ریال)', helperText: 'مانده: ${inv['remaining_fa'] ?? ''}')),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            value: method,
            items: methods.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
            onChanged: (v) => setSheet(() => method = v ?? method),
            decoration: const InputDecoration(labelText: 'روشِ پرداخت'),
          ),
          const SizedBox(height: 10),
          TextField(controller: reference, decoration: const InputDecoration(labelText: 'کدِ پیگیری (اختیاری)')),
          const SizedBox(height: 14),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppTheme.success),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('ثبت'),
          ),
          const SizedBox(height: 16),
        ]),
      )),
    );

    if (ok == true) {
      final amt = int.tryParse(amount.text.trim()) ?? 0;
      if (amt <= 0) return;
      try {
        await Api.payInvoice(widget.id, amt, method, reference: reference.text.trim());
        await _load();
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('پرداخت ثبت شد')));
      } catch (e) {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: AppTheme.danger));
      }
    }
  }

  Future<void> _openPdf() async {
    setState(() => _openingPdf = true);
    try {
      final bytes = await Api.invoicePdf(widget.id);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/invoice_${widget.id}.pdf');
      await file.writeAsBytes(bytes, flush: true);
      final res = await OpenFilex.open(file.path, type: 'application/pdf');
      if (res.type != ResultType.done && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('برای نمایشِ PDF یک برنامهٔ نمایش‌گر لازم است.')));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: AppTheme.danger));
    } finally {
      if (mounted) setState(() => _openingPdf = false);
    }
  }

  Widget _kv(IconData icon, String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Icon(icon, size: 16, color: Colors.black45),
          const SizedBox(width: 8),
          Text('$k: ', style: const TextStyle(fontSize: 13, color: Colors.black54)),
          Expanded(child: Text(v, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500))),
        ]),
      );

  Widget _amount(String label, dynamic value, {Color? color, bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Expanded(child: Text(label, style: const TextStyle(fontSize: 13, color: Colors.black54))),
          Text('${value ?? '—'}', style: TextStyle(
              fontSize: 14, color: color, fontWeight: bold ? FontWeight.bold : FontWeight.w500)),
        ]),
      );

  Widget _badge(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
      );
}
