import 'package:flutter/material.dart';
import '../api.dart';
import '../theme.dart';

/// صدور فاکتور — از روی یک تیکت یا مشتری. ردیف‌ها (خدمت/قطعه/سایر) با شرح و مبلغ.
/// سهمِ قرارداد و مبلغِ قابل‌پرداخت را سرور خودکار حساب می‌کند.
class CreateInvoiceScreen extends StatefulWidget {
  final int? ticketId;
  final int? customerId;
  const CreateInvoiceScreen({super.key, this.ticketId, this.customerId});
  @override
  State<CreateInvoiceScreen> createState() => _CreateInvoiceScreenState();
}

class _Line {
  String type = 'service';
  final title = TextEditingController();
  final qty = TextEditingController(text: '1');
  final price = TextEditingController();
}

class _CreateInvoiceScreenState extends State<CreateInvoiceScreen> {
  Map<String, dynamic>? _form;
  final List<_Line> _lines = [];
  final _discount = TextEditingController();
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final f = await Api.invoiceFormData(ticketId: widget.ticketId, customerId: widget.customerId);
      final line = _Line()..title.text = (f['default_title'] ?? '').toString();
      setState(() { _form = f; _lines.add(line); _loading = false; });
    } catch (e) {
      setState(() { _error = '$e'; _loading = false; });
    }
  }

  Map<String, String> get _types => (_form!['item_types'] as Map).cast<String, String>();

  Future<void> _save({required bool issue}) async {
    final items = <Map<String, dynamic>>[];
    for (final l in _lines) {
      final price = int.tryParse(l.price.text.trim()) ?? 0;
      if (l.title.text.trim().isEmpty || price <= 0) continue;
      items.add({
        'item_type': l.type,
        'title': l.title.text.trim(),
        'quantity': double.tryParse(l.qty.text.trim()) ?? 1,
        'unit_price': price,
      });
    }
    if (items.isEmpty) {
      setState(() => _error = 'حداقل یک ردیف با شرح و مبلغ لازم است.');
      return;
    }
    setState(() { _saving = true; _error = null; });
    try {
      final customer = (_form!['customer'] as Map);
      final res = await Api.createInvoice({
        'customer_id': customer['id'],
        if (widget.ticketId != null) 'ticket_id': widget.ticketId,
        if ((_discount.text.trim()).isNotEmpty) 'discount_amount': int.tryParse(_discount.text.trim()) ?? 0,
        'issue': issue,
        'items': items,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text('فاکتور ${res['number']} ثبت شد • قابلِ پرداخت: ${res['payable_fa']}')));
        Navigator.pop(context, true);
      }
    } catch (e) {
      setState(() { _error = '$e'; _saving = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('صدور فاکتور')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _form == null
              ? Center(child: Text(_error ?? 'خطا'))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (_error != null)
                      Container(
                        padding: const EdgeInsets.all(10), margin: const EdgeInsets.only(bottom: 12),
                        decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
                        child: Text(_error!, style: const TextStyle(color: AppTheme.danger)),
                      ),
                    Card(child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('مشتری: ${(_form!['customer'] as Map)['name']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                        if (_form!['ticket'] != null)
                          Padding(padding: const EdgeInsets.only(top: 4),
                              child: Text('تیکت: ${(_form!['ticket'] as Map)['number']} — ${(_form!['ticket'] as Map)['subject']}',
                                  style: const TextStyle(fontSize: 12, color: Colors.black54))),
                        if (_form!['contract'] != null)
                          Padding(padding: const EdgeInsets.only(top: 4),
                              child: Row(children: [
                                const Icon(Icons.verified_outlined, size: 15, color: AppTheme.success),
                                const SizedBox(width: 4),
                                Expanded(child: Text('قرارداد: ${(_form!['contract'] as Map)['plan'] ?? ''}'
                                    '${(_form!['contract'] as Map)['valid'] == true ? ' (معتبر)' : ' (نامعتبر)'}',
                                    style: const TextStyle(fontSize: 12, color: Colors.black54))),
                              ])),
                      ]),
                    )),
                    const SizedBox(height: 12),
                    const Text('ردیف‌های فاکتور', style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    ..._lines.asMap().entries.map((e) => _lineCard(e.key, e.value)),
                    OutlinedButton.icon(
                      onPressed: () => setState(() => _lines.add(_Line())),
                      icon: const Icon(Icons.add), label: const Text('افزودنِ ردیف'),
                    ),
                    const SizedBox(height: 12),
                    TextField(controller: _discount, keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'تخفیف (ریال، اختیاری)')),
                    const SizedBox(height: 20),
                    Row(children: [
                      Expanded(child: OutlinedButton(
                        onPressed: _saving ? null : () => _save(issue: false),
                        child: const Text('ذخیرهٔ پیش‌نویس'),
                      )),
                      const SizedBox(width: 10),
                      Expanded(child: FilledButton(
                        style: FilledButton.styleFrom(backgroundColor: AppTheme.success),
                        onPressed: _saving ? null : () => _save(issue: true),
                        child: _saving
                            ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Text('صدور فاکتور'),
                      )),
                    ]),
                    const SizedBox(height: 16),
                  ],
                ),
    );
  }

  Widget _lineCard(int i, _Line l) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(children: [
          Row(children: [
            Expanded(child: DropdownButtonFormField<String>(
              value: l.type,
              items: _types.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
              onChanged: (v) => setState(() => l.type = v ?? 'service'),
              decoration: const InputDecoration(labelText: 'نوع', isDense: true),
            )),
            if (_lines.length > 1)
              IconButton(onPressed: () => setState(() => _lines.removeAt(i)),
                  icon: const Icon(Icons.delete_outline, color: AppTheme.danger)),
          ]),
          const SizedBox(height: 8),
          TextField(controller: l.title, decoration: const InputDecoration(labelText: 'شرح', isDense: true)),
          const SizedBox(height: 8),
          Row(children: [
            Expanded(child: TextField(controller: l.qty, keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'تعداد', isDense: true))),
            const SizedBox(width: 8),
            Expanded(flex: 2, child: TextField(controller: l.price, keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'مبلغِ واحد (ریال)', isDense: true))),
          ]),
        ]),
      ),
    );
  }
}
