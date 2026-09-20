import 'package:flutter/material.dart';
import '../api.dart';
import '../theme.dart';

/// تاریخچهٔ تغییرات — چه کسی، کِی، چه کاری. فقط‌خواندنی.
class ActivityScreen extends StatefulWidget {
  const ActivityScreen({super.key});
  @override
  State<ActivityScreen> createState() => _ActivityScreenState();
}

class _ActivityScreenState extends State<ActivityScreen> {
  List _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await Api.activity();
      if (mounted) setState(() { _items = res['data'] as List; _error = null; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تاریخچهٔ تغییرات')),
      body: _error != null
          ? Center(child: Text(_error!))
          : _loading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _items.isEmpty
                      ? ListView(children: const [SizedBox(height: 120), Center(child: Text('رویدادی نیست'))])
                      : ListView.separated(
                          padding: const EdgeInsets.all(12),
                          itemCount: _items.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 6),
                          itemBuilder: (_, i) => _tile(_items[i] as Map<String, dynamic>),
                        ),
                ),
    );
  }

  Widget _tile(Map<String, dynamic> l) => Card(
        child: ListTile(
          dense: true,
          leading: CircleAvatar(radius: 18, backgroundColor: AppTheme.accent.withOpacity(0.12),
              child: const Icon(Icons.history, size: 18, color: AppTheme.accent)),
          title: Text(l['action_label'] ?? l['action'] ?? '', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
          subtitle: Text(
            [if (l['subject_type'] != null && l['subject_type'] != '') '${l['subject_type']} #${l['subject_id']}', l['user']]
                .where((e) => e != null && e != '').join(' • '),
            style: const TextStyle(fontSize: 11),
          ),
          trailing: Text(l['created_at_jalali'] ?? '', style: const TextStyle(fontSize: 10, color: Colors.black45)),
        ),
      );
}
