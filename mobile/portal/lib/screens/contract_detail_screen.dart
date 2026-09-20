import 'package:flutter/material.dart';
import '../api.dart';
import '../theme.dart';

/// جزئیاتِ قرارداد برای مشتری — نوع، درصدهای پوشش، سقف/مانده.
class ContractDetailScreen extends StatefulWidget {
  final int id;
  final String number;
  const ContractDetailScreen({super.key, required this.id, required this.number});
  @override
  State<ContractDetailScreen> createState() => _ContractDetailScreenState();
}

class _ContractDetailScreenState extends State<ContractDetailScreen> {
  Map<String, dynamic>? _data;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d = await Api.contract(widget.id);
      if (mounted) setState(() { _data = d; _error = null; });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('قرارداد ${widget.number}')),
      body: _error != null
          ? Center(child: Text(_error!))
          : _data == null
              ? const Center(child: CircularProgressIndicator())
              : _body(),
    );
  }

  Widget _body() {
    final c = (_data!['contract'] as Map).cast<String, dynamic>();
    final coverage = (c['coverage'] as List?) ?? [];
    final statusColor = c['status'] == 'active' ? AppTheme.success : (c['status'] == 'expired' ? AppTheme.danger : Colors.grey);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          Card(child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text(c['plan'] ?? 'قرارداد', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold))),
                _badge(c['status_label'] ?? '', statusColor),
              ]),
              const SizedBox(height: 8),
              _kv(Icons.event, 'شروع', c['start_date'] ?? '—'),
              _kv(Icons.event_busy, 'پایان', c['end_date'] ?? '—'),
              _kv(Icons.account_balance_wallet_outlined, 'مبلغِ قرارداد', c['amount_fa'] ?? '—'),
              if (c['ceiling_fa'] != null) _kv(Icons.vertical_align_top, 'سقفِ پوشش', c['ceiling_fa']),
              if (c['remaining_ceiling_fa'] != null) _kv(Icons.savings_outlined, 'ماندهٔ سقف', c['remaining_ceiling_fa']),
              if (c['response_hours'] != null) _kv(Icons.timer_outlined, 'زمانِ پاسخ (ساعت)', '${c['response_hours']}'),
            ]),
          )),
          if (coverage.isNotEmpty) ...[
            const Padding(padding: EdgeInsets.fromLTRB(4, 14, 4, 6), child: Text('درصدهای پوشش', style: TextStyle(fontWeight: FontWeight.bold))),
            Card(child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(children: coverage.map<Widget>((cov) {
                final m = cov as Map;
                final pct = (m['percent'] ?? 0) as int;
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(children: [
                    Expanded(flex: 2, child: Text(m['label'] ?? '', style: const TextStyle(fontSize: 13))),
                    Expanded(flex: 3, child: ClipRRect(borderRadius: BorderRadius.circular(6),
                        child: LinearProgressIndicator(value: pct / 100, minHeight: 8,
                            backgroundColor: const Color(0xFFE5EDF0), color: AppTheme.accent))),
                    const SizedBox(width: 8),
                    Text('٪$pct', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                  ]),
                );
              }).toList()),
            )),
          ],
          if (c['notes'] != null) ...[
            const SizedBox(height: 12),
            Card(child: Padding(padding: const EdgeInsets.all(14), child: Text(c['notes'], style: const TextStyle(height: 1.6)))),
          ],
          const SizedBox(height: 12),
        ],
      ),
    );
  }

  Widget _kv(IconData icon, String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(children: [
          Icon(icon, size: 16, color: Colors.black45),
          const SizedBox(width: 8),
          Text('$k: ', style: const TextStyle(fontSize: 13, color: Colors.black54)),
          Expanded(child: Text(v, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500))),
        ]),
      );

  Widget _badge(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
      );
}
