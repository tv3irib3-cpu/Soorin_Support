import 'dart:async';
import 'package:flutter/material.dart';
import '../api.dart';
import '../theme.dart';
import 'contract_detail_screen.dart';

/// فهرستِ قراردادها با جستجو (شماره/نامِ مشتری).
class ContractsScreen extends StatefulWidget {
  const ContractsScreen({super.key});
  @override
  State<ContractsScreen> createState() => _ContractsScreenState();
}

class _ContractsScreenState extends State<ContractsScreen> {
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
      final res = await Api.contracts(search: _searchCtrl.text.trim());
      if (mounted) setState(() { _items = res['data'] as List; _error = null; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  void _onSearch(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), _load);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('قراردادها')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 6),
          child: TextField(
            controller: _searchCtrl,
            onChanged: _onSearch,
            decoration: InputDecoration(
              hintText: 'جستجو (شماره / مشتری)',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _searchCtrl.text.isEmpty ? null
                  : IconButton(icon: const Icon(Icons.clear), onPressed: () { _searchCtrl.clear(); _load(); }),
              isDense: true,
            ),
          ),
        ),
        Expanded(
          child: _error != null
              ? Center(child: Text(_error!))
              : _loading
                  ? const Center(child: CircularProgressIndicator())
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: _items.isEmpty
                          ? ListView(children: const [SizedBox(height: 120), Center(child: Text('قراردادی نیست'))])
                          : ListView.separated(
                              padding: const EdgeInsets.all(12),
                              itemCount: _items.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (_, i) => _tile(_items[i] as Map<String, dynamic>),
                            ),
                    ),
        ),
      ]),
    );
  }

  Widget _tile(Map<String, dynamic> c) {
    final statusColor = c['status'] == 'active' ? AppTheme.success : (c['status'] == 'expired' ? AppTheme.danger : Colors.grey);
    return Card(
      child: ListTile(
        onTap: () => Navigator.push(context, MaterialPageRoute(
            builder: (_) => ContractDetailScreen(id: c['id'] as int, number: c['number'] ?? ''))),
        title: Row(children: [
          Expanded(child: Text('قرارداد ${c['number'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600))),
          _badge(c['status_label'] ?? '', statusColor),
        ]),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text('${c['customer'] ?? '—'} • ${c['plan'] ?? '—'} • ${c['start_date']} تا ${c['end_date']}',
              style: const TextStyle(fontSize: 12)),
        ),
      ),
    );
  }

  Widget _badge(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
      );
}
