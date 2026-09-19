import 'package:flutter/material.dart';
import '../api.dart';
import '../store.dart';
import '../theme.dart';

class TicketDetailScreen extends StatefulWidget {
  final int id;
  const TicketDetailScreen({super.key, required this.id});
  @override
  State<TicketDetailScreen> createState() => _TicketDetailScreenState();
}

class _TicketDetailScreenState extends State<TicketDetailScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  Map<String, String> _imgHeaders = {};
  Map<String, dynamic> _meta = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final t = await Store.token();
      _imgHeaders = t != null ? {'Authorization': 'Bearer $t'} : {};
      final d = await Api.ticket(widget.id);
      Map<String, dynamic> meta = {};
      try { meta = await Api.meta(); } catch (_) {}
      if (mounted) setState(() { _data = d; _meta = meta; _error = null; });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = _data?['ticket'] as Map<String, dynamic>?;
    return Scaffold(
      appBar: AppBar(title: Text(t?['number'] ?? 'تیکت')),
      body: _error != null
          ? Center(child: Text(_error!))
          : _data == null
              ? const Center(child: CircularProgressIndicator())
              : _body(t!),
      bottomNavigationBar: _data == null ? null : _actionBar(),
    );
  }

  Widget _body(Map<String, dynamic> t) {
    final messages = (_data!['messages'] as List);
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
                  Text(t['subject'] ?? '', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 10),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    _badge(t['status_label'] ?? '', AppTheme.statusColor(t['status'] ?? '')),
                    _badge(t['priority_label'] ?? '', AppTheme.priorityColor(t['priority'] ?? '')),
                    if (t['customer'] != null) _chip(Icons.business, t['customer']),
                    if (t['project'] != null) _chip(Icons.folder_outlined, t['project']),
                  ]),
                  const Divider(height: 24),
                  Text(t['description'] ?? '', style: const TextStyle(height: 1.7)),
                  ..._attachments(_data!['ticket_attachments'] as List),
                ],
              ),
            ),
          ),
          const SizedBox(height: 8),
          const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('گفتگو', style: TextStyle(fontWeight: FontWeight.bold))),
          ...messages.map((m) => _message(m as Map<String, dynamic>)),
          const SizedBox(height: 12),
        ],
      ),
    );
  }

  Widget _message(Map<String, dynamic> m) {
    final us = m['is_support'] == true; // در اپِ پشتیبان، پیامِ پشتیبان = «ما»
    final internal = m['is_internal'] == true;
    return Align(
      alignment: us ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.82),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: internal
              ? AppTheme.warning.withOpacity(0.12)
              : (us ? AppTheme.accent.withOpacity(0.12) : Colors.white),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFDDE8EC)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Text(m['author'] ?? '', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black54)),
              if (internal) ...[
                const SizedBox(width: 6),
                _badge('یادداشت داخلی', AppTheme.warning),
              ],
              const Spacer(),
              Text(m['created_at_jalali'] ?? '', style: const TextStyle(fontSize: 10, color: Colors.black45)),
            ]),
            const SizedBox(height: 6),
            Text(m['body'] ?? '', style: const TextStyle(height: 1.6)),
            ..._attachments(m['attachments'] as List),
          ],
        ),
      ),
    );
  }

  List<Widget> _attachments(List atts) {
    if (atts.isEmpty) return [];
    return [
      const SizedBox(height: 8),
      Wrap(
        spacing: 8, runSpacing: 8,
        children: atts.map<Widget>((a) {
          final att = a as Map<String, dynamic>;
          if (att['is_image'] == true) {
            return ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Image.network(att['url'], headers: _imgHeaders, width: 130, height: 110, fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => _fileChip(att['name'])),
            );
          }
          return _fileChip(att['name']);
        }).toList(),
      ),
    ];
  }

  Widget _fileChip(String name) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(color: const Color(0xFFEEF4F6), borderRadius: BorderRadius.circular(10)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.attach_file, size: 16),
          const SizedBox(width: 6),
          Text(name, style: const TextStyle(fontSize: 12)),
        ]),
      );

  Widget _actionBar() {
    final ab = (_data!['abilities'] as Map).cast<String, dynamic>();
    final buttons = <Widget>[];
    if (ab['reply'] == true) {
      buttons.add(Expanded(child: FilledButton.icon(onPressed: _reply, icon: const Icon(Icons.reply), label: const Text('پاسخ'))));
    }
    if (ab['resolve'] == true) {
      buttons.add(const SizedBox(width: 8));
      buttons.add(Expanded(child: FilledButton.icon(
        style: FilledButton.styleFrom(backgroundColor: AppTheme.success),
        onPressed: _resolve, icon: const Icon(Icons.check), label: const Text('حل شد'))));
    }
    if (ab['change_status'] == true) {
      buttons.add(const SizedBox(width: 8));
      buttons.add(IconButton.filledTonal(onPressed: _changeStatus, icon: const Icon(Icons.swap_horiz), tooltip: 'تغییر وضعیت'));
    }
    if (buttons.isEmpty) return const SizedBox.shrink();
    return SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: Row(children: buttons)));
  }

  // ---- اکشن‌ها ----

  Future<void> _reply() async {
    final ab = (_data!['abilities'] as Map).cast<String, dynamic>();
    final body = TextEditingController();
    final minutes = TextEditingController(text: '0');
    bool internal = false;

    final ok = await showModalBottomSheet<bool>(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setSheet) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('پاسخ به تیکت', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          TextField(controller: body, maxLines: 4, decoration: const InputDecoration(labelText: 'متنِ پاسخ')),
          const SizedBox(height: 10),
          TextField(controller: minutes, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'مدتِ کارکرد (دقیقه)')),
          if (ab['internal_note'] == true)
            SwitchListTile(value: internal, onChanged: (v) => setSheet(() => internal = v),
                title: const Text('یادداشت داخلی (مشتری نمی‌بیند)')),
          const SizedBox(height: 8),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('ارسال')),
          const SizedBox(height: 16),
        ]),
      )),
    );

    if (ok == true && body.text.trim().isNotEmpty) {
      await _run(() => Api.reply(widget.id, body.text.trim(), int.tryParse(minutes.text) ?? 0, internal: internal));
    }
  }

  Future<void> _resolve() async {
    final methods = (_meta['methods'] as Map?)?.cast<String, dynamic>() ?? {};
    final selected = <String>{};
    final resolution = TextEditingController();

    final ok = await showModalBottomSheet<bool>(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setSheet) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('مشکل حل شد', style: TextStyle(fontWeight: FontWeight.bold)),
          const Text('روشِ انجام (حداقل یکی)', style: TextStyle(fontSize: 12, color: Colors.black54)),
          ...methods.entries.map((e) => CheckboxListTile(
                dense: true, value: selected.contains(e.key), title: Text(e.value),
                onChanged: (v) => setSheet(() => v == true ? selected.add(e.key) : selected.remove(e.key)))),
          TextField(controller: resolution, maxLines: 2, decoration: const InputDecoration(labelText: 'شرحِ راه‌حل (اختیاری)')),
          const SizedBox(height: 10),
          FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppTheme.success),
              onPressed: selected.isEmpty ? null : () => Navigator.pop(ctx, true),
              child: const Text('ثبتِ حل‌شدن')),
          const SizedBox(height: 16),
        ]),
      )),
    );

    if (ok == true && selected.isNotEmpty) {
      await _run(() => Api.resolve(widget.id, selected.toList(), resolution.text.trim().isEmpty ? null : resolution.text.trim()));
    }
  }

  Future<void> _changeStatus() async {
    final statuses = (_meta['statuses'] as Map?)?.cast<String, dynamic>() ?? {};
    final current = (_data!['ticket'] as Map)['status'];
    final options = statuses.entries.where((e) => e.key != current && e.key != 'new').toList();
    final methods = (_meta['methods'] as Map?)?.cast<String, dynamic>() ?? {};
    String? chosen;
    final selectedMethods = <String>{};
    final resolution = TextEditingController();

    final ok = await showModalBottomSheet<bool>(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setSheet) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('تغییر وضعیت', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(
            value: chosen,
            items: options.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
            onChanged: (v) => setSheet(() => chosen = v),
            decoration: const InputDecoration(labelText: 'وضعیتِ جدید'),
          ),
          if (chosen == 'resolved') ...[
            const SizedBox(height: 8),
            const Align(alignment: Alignment.centerRight, child: Text('روشِ انجام', style: TextStyle(fontSize: 12, color: Colors.black54))),
            ...methods.entries.map((e) => CheckboxListTile(
                  dense: true, value: selectedMethods.contains(e.key), title: Text(e.value),
                  onChanged: (v) => setSheet(() => v == true ? selectedMethods.add(e.key) : selectedMethods.remove(e.key)))),
            TextField(controller: resolution, maxLines: 2, decoration: const InputDecoration(labelText: 'شرحِ راه‌حل')),
          ],
          const SizedBox(height: 10),
          FilledButton(onPressed: chosen == null ? null : () => Navigator.pop(ctx, true), child: const Text('ثبت')),
          const SizedBox(height: 16),
        ]),
      )),
    );

    if (ok == true && chosen != null) {
      await _run(() => Api.changeStatus(widget.id, chosen!,
          methods: chosen == 'resolved' ? selectedMethods.toList() : null,
          resolution: chosen == 'resolved' ? resolution.text.trim() : null));
    }
  }

  Future<void> _run(Future<void> Function() action) async {
    try {
      await action();
      await _load();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('ثبت شد')));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: AppTheme.danger));
    }
  }

  Widget _badge(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
      );

  Widget _chip(IconData icon, String text) => Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 15, color: Colors.black54),
        const SizedBox(width: 4),
        Text(text, style: const TextStyle(fontSize: 12, color: Colors.black87)),
      ]);
}
