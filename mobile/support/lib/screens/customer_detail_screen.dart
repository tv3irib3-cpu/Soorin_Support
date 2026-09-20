import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../api.dart';
import '../theme.dart';
import 'ticket_detail_screen.dart';
import 'invoice_detail_screen.dart';
import 'create_invoice_screen.dart';

/// جزئیاتِ مشتری برای پشتیبان: اطلاعاتِ تماس (تماس/پیامک/ایمیل)، وضعیتِ سرویس،
/// پروژه‌ها، و تیکت‌ها/فاکتورهای اخیر (با لمس به صفحهٔ مربوط می‌رود).
class CustomerDetailScreen extends StatefulWidget {
  final int id;
  final String name;
  final bool canInvoice;
  const CustomerDetailScreen({super.key, required this.id, required this.name, this.canInvoice = false});
  @override
  State<CustomerDetailScreen> createState() => _CustomerDetailScreenState();
}

class _CustomerDetailScreenState extends State<CustomerDetailScreen> {
  Map<String, dynamic>? _data;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d = await Api.customer(widget.id);
      if (mounted) setState(() { _data = d; _error = null; });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

  Future<void> _launch(String uri) async {
    final u = Uri.parse(uri);
    if (await canLaunchUrl(u)) {
      await launchUrl(u, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.name)),
      body: _error != null
          ? Center(child: Text(_error!))
          : _data == null
              ? const Center(child: CircularProgressIndicator())
              : _body(),
      bottomNavigationBar: (widget.canInvoice && _data != null)
          ? SafeArea(child: Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton.icon(
                onPressed: () async {
                  final done = await Navigator.push(context,
                      MaterialPageRoute(builder: (_) => CreateInvoiceScreen(customerId: widget.id)));
                  if (done == true) _load();
                },
                icon: const Icon(Icons.receipt_long),
                label: const Text('صدور فاکتور'),
              ),
            ))
          : null,
    );
  }

  Widget _body() {
    final c = (_data!['customer'] as Map).cast<String, dynamic>();
    final projects = _data!['projects'] as List;
    final tickets = _data!['tickets'] as List;
    final invoices = _data!['invoices'] as List;
    final active = c['is_active'] == true;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    _logoAvatar(c),
                    const SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(c['name'] ?? '', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      if (c['code'] != null) ...[
                        const SizedBox(height: 2),
                        Text('کد: ${c['code']}', style: const TextStyle(fontSize: 12, color: Colors.black54)),
                      ],
                    ])),
                    _badge(active ? 'فعال' : 'معلق', active ? AppTheme.success : AppTheme.danger),
                  ]),
                  if (!active && c['suspension_message'] != null) ...[
                    const SizedBox(height: 8),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.08), borderRadius: BorderRadius.circular(8)),
                      child: Text(c['suspension_message'], style: const TextStyle(color: AppTheme.danger, fontSize: 12)),
                    ),
                  ],
                  const Divider(height: 22),
                  if (c['mobile'] != null) _contactRow(Icons.smartphone, c['mobile'], tel: c['mobile']),
                  if (c['phone'] != null) _contactRow(Icons.phone, c['phone'], tel: c['phone']),
                  if (c['email'] != null) _contactRow(Icons.email_outlined, c['email'], mail: c['email']),
                  if (c['city'] != null || c['address'] != null)
                    _contactRow(Icons.location_on_outlined, [c['city'], c['address']].where((e) => e != null).join(' — ')),
                ],
              ),
            ),
          ),
          if (projects.isNotEmpty) ...[
            _section('پروژه‌ها'),
            Card(child: Padding(
              padding: const EdgeInsets.all(12),
              child: Wrap(spacing: 8, runSpacing: 8, children: projects.map<Widget>((p) => Chip(
                    label: Text((p as Map)['name'] ?? '', style: const TextStyle(fontSize: 12)),
                    avatar: const Icon(Icons.folder_outlined, size: 16),
                  )).toList()),
            )),
          ],
          _section('تیکت‌های اخیر'),
          if (tickets.isEmpty)
            const Card(child: ListTile(title: Text('تیکتی نیست', style: TextStyle(fontSize: 13))))
          else
            ...tickets.map((t) => _ticketTile(t as Map<String, dynamic>)),
          _section('فاکتورهای اخیر'),
          if (invoices.isEmpty)
            const Card(child: ListTile(title: Text('فاکتوری نیست', style: TextStyle(fontSize: 13))))
          else
            ...invoices.map((inv) => _invoiceTile(inv as Map<String, dynamic>)),
          const SizedBox(height: 12),
        ],
      ),
    );
  }

  Widget _contactRow(IconData icon, String text, {String? tel, String? mail}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(children: [
        Icon(icon, size: 18, color: Colors.black54),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 14))),
        if (tel != null) ...[
          IconButton(visualDensity: VisualDensity.compact, onPressed: () => _launch('tel:$tel'),
              icon: const Icon(Icons.call, size: 20, color: AppTheme.success)),
          IconButton(visualDensity: VisualDensity.compact, onPressed: () => _launch('sms:$tel'),
              icon: const Icon(Icons.sms_outlined, size: 20, color: AppTheme.info)),
        ],
        if (mail != null)
          IconButton(visualDensity: VisualDensity.compact, onPressed: () => _launch('mailto:$mail'),
              icon: const Icon(Icons.send, size: 20, color: AppTheme.info)),
      ]),
    );
  }

  Widget _ticketTile(Map<String, dynamic> t) => Card(
        child: ListTile(
          dense: true,
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TicketDetailScreen(id: t['id'] as int))),
          title: Text(t['subject'] ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
          subtitle: Text(t['number'] ?? '', style: const TextStyle(fontSize: 11)),
          trailing: _badge(t['status_label'] ?? '', AppTheme.statusColor(t['status'] ?? '')),
        ),
      );

  Widget _invoiceTile(Map<String, dynamic> inv) => Card(
        child: ListTile(
          dense: true,
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(id: inv['id'] as int))),
          title: Text('فاکتور ${inv['number'] ?? ''}'),
          subtitle: Text(inv['issue_date'] ?? '', style: const TextStyle(fontSize: 11)),
          trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
            _badge(inv['status_label'] ?? '', AppTheme.invoiceStatusColor(inv['status'] ?? '')),
            const SizedBox(height: 2),
            Text('${inv['payable_fa'] ?? ''}', style: const TextStyle(fontSize: 11, color: Colors.black54)),
          ]),
        ),
      );

  Widget _logoAvatar(Map<String, dynamic> c) {
    final color = _hex(c['color']) ?? AppTheme.accent;
    final logo = c['logo'];
    if (logo is String && logo.startsWith('data:image/') && !logo.contains('svg')) {
      try {
        final bytes = base64Decode(logo.substring(logo.indexOf(',') + 1));
        return ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Image.memory(bytes, width: 54, height: 54, fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => _initial(c, color)),
        );
      } catch (_) {}
    }
    return _initial(c, color);
  }

  Widget _initial(Map<String, dynamic> c, Color color) {
    final name = (c['name'] ?? '؟').toString().trim();
    return CircleAvatar(
      radius: 27,
      backgroundColor: color.withOpacity(0.15),
      child: Text(name.isEmpty ? '؟' : name.substring(0, 1),
          style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 20)),
    );
  }

  Color? _hex(dynamic v) {
    if (v is! String || !v.startsWith('#') || v.length < 7) return null;
    return Color(int.parse('FF${v.substring(1)}', radix: 16));
  }

  Widget _section(String title) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 14, 4, 6),
        child: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
      );

  Widget _badge(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
      );
}
