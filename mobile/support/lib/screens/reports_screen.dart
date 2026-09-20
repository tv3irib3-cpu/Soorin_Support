import 'package:flutter/material.dart';
import '../api.dart';
import '../theme.dart';

/// گزارش‌های مدیریتی — همان اعدادِ سایت. بازه با دکمه‌های ۷/۳۰/۹۰ روز و «امسال».
class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});
  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  int _days = 30;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _data = null; _error = null; });
    try {
      final now = DateTime.now();
      final from = now.subtract(Duration(days: _days));
      String d(DateTime x) => '${x.year}-${x.month.toString().padLeft(2, '0')}-${x.day.toString().padLeft(2, '0')}';
      final res = await Api.reports(from: d(from), to: d(now));
      if (mounted) setState(() => _data = res);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('گزارش‌ها')),
      body: Column(children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(children: {7: '۷ روز', 30: '۳۰ روز', 90: '۹۰ روز', 365: 'یک سال'}.entries.map((e) {
            return Padding(padding: const EdgeInsets.only(left: 8),
              child: ChoiceChip(label: Text(e.value), selected: _days == e.key,
                  onSelected: (_) { setState(() => _days = e.key); _load(); }));
          }).toList()),
        ),
        Expanded(
          child: _error != null
              ? Center(child: Text(_error!))
              : _data == null
                  ? const Center(child: CircularProgressIndicator())
                  : _body(),
        ),
      ]),
    );
  }

  Widget _body() {
    final s = (_data!['summary'] as Map).cast<String, dynamic>();
    final byCustomer = _data!['by_customer'] as List;
    final byStaff = _data!['by_staff'] as List;
    final byStatus = _data!['by_status'] as List;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          Text('از ${_data!['from']} تا ${_data!['to']}', style: const TextStyle(fontSize: 12, color: Colors.black54)),
          const SizedBox(height: 10),
          GridView.count(
            crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.7,
            children: [
              _metric('درآمد', s['revenue_fa'], AppTheme.success),
              _metric('بدهی', s['debt_fa'], AppTheme.danger),
              _metric('ارزشِ خدمات', s['service_value_fa'], AppTheme.info),
              _metric('پوششِ قرارداد', s['warranty_value_fa'], AppTheme.warning),
              _metric('تیکتِ ثبت‌شده', '${s['tickets_created']}', AppTheme.nav),
              _metric('تیکتِ حل‌شده', '${s['service_count']}', AppTheme.success),
              _metric('هنوز باز', '${s['tickets_still_open']}', AppTheme.warning),
              _metric('فاکتورها', '${s['invoice_count']}', AppTheme.info),
              _metric('میانگینِ حل (ساعت)', s['avg_resolution_hours'] == null ? '—' : '${s['avg_resolution_hours']}', AppTheme.nav),
              _metric('نقضِ SLA', '${s['sla_breaches']}', AppTheme.danger),
              _metric('میانگینِ امتیاز', s['avg_rating'] == null ? '—' : '${s['avg_rating']}', AppTheme.warning),
              _metric('کارکرد (دقیقه)', '${s['work_minutes']}', AppTheme.info),
            ],
          ),
          if (byStatus.isNotEmpty) ...[
            _section('تیکت به تفکیکِ وضعیت'),
            Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(
              children: byStatus.map<Widget>((r) => _rowKV((r as Map)['label'] ?? '', '${r['count']}')).toList()))),
          ],
          if (byCustomer.isNotEmpty) ...[
            _section('بیشترین تیکت به تفکیکِ مشتری'),
            Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(
              children: byCustomer.map<Widget>((r) {
                final m = r as Map;
                return _rowKV('${m['customer']}  (حل‌شده ${m['resolved']})', '${m['created']} تیکت');
              }).toList()))),
          ],
          if (byStaff.isNotEmpty) ...[
            _section('عملکردِ کارشناسان'),
            Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(
              children: byStaff.map<Widget>((r) {
                final m = r as Map;
                final avg = m['avg_response_hr'];
                return _rowKV(m['staff'] ?? '—', 'حل‌شده ${m['resolved']}${avg == null ? '' : ' • پاسخ ~${avg}س'}');
              }).toList()))),
          ],
          const SizedBox(height: 12),
        ],
      ),
    );
  }

  Widget _metric(String label, dynamic value, Color color) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFDDE8EC))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(label, style: const TextStyle(fontSize: 12, color: Colors.black54)),
          const SizedBox(height: 4),
          Text('${value ?? '—'}', maxLines: 1, overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: color)),
        ]),
      );

  Widget _rowKV(String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(children: [
          Expanded(child: Text(k, style: const TextStyle(fontSize: 12))),
          Text(v, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        ]),
      );

  Widget _section(String title) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 16, 4, 6),
        child: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
      );
}
