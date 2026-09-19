import 'package:flutter/material.dart';
import '../api.dart';
import '../store.dart';
import '../theme.dart';

class TicketDetailScreen extends StatefulWidget {
  final int id;
  final bool isAdmin;
  const TicketDetailScreen({super.key, required this.id, this.isAdmin = false});
  @override
  State<TicketDetailScreen> createState() => _TicketDetailScreenState();
}

class _TicketDetailScreenState extends State<TicketDetailScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  Map<String, String> _imgHeaders = {};

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
      if (mounted) setState(() { _data = d; _error = null; });
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
    final assignee = _data!['assignee'] as String?;
    final rating = _data!['rating'];
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
                    if (t['project'] != null) _chip(Icons.folder_outlined, t['project']),
                    if (t['category'] != null) _chip(Icons.category_outlined, t['category']),
                    if (assignee != null) _chip(Icons.person_outline, assignee),
                  ]),
                  const Divider(height: 24),
                  Text(t['description'] ?? '', style: const TextStyle(height: 1.7)),
                  ..._attachments(_data!['ticket_attachments'] as List),
                  if (rating != null) ...[
                    const SizedBox(height: 12),
                    Row(children: [
                      const Text('امتیازِ شما: ', style: TextStyle(fontSize: 13, color: Colors.black54)),
                      ...List.generate(5, (i) => Icon(
                            i < (rating as int) ? Icons.star : Icons.star_border,
                            size: 20, color: AppTheme.warning)),
                    ]),
                  ],
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
    // در اپِ مشتری، پیامِ غیرِپشتیبان = «ما» (سمتِ راست).
    final us = m['is_support'] != true;
    return Align(
      alignment: us ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.82),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: us ? AppTheme.accent.withOpacity(0.12) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFDDE8EC)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Text(m['author'] ?? '', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black54)),
              if (m['is_support'] == true) ...[
                const SizedBox(width: 6),
                _badge('پشتیبانی', AppTheme.info),
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
          Flexible(child: Text(name, style: const TextStyle(fontSize: 12))),
        ]),
      );

  Widget _actionBar() {
    final ab = (_data!['abilities'] as Map).cast<String, dynamic>();
    final buttons = <Widget>[];
    if (ab['reply'] == true) {
      buttons.add(Expanded(child: FilledButton.icon(onPressed: _reply, icon: const Icon(Icons.reply), label: const Text('پاسخ'))));
    }
    if (ab['rate'] == true) {
      if (buttons.isNotEmpty) buttons.add(const SizedBox(width: 8));
      buttons.add(Expanded(child: FilledButton.icon(
        style: FilledButton.styleFrom(backgroundColor: AppTheme.warning),
        onPressed: _rate, icon: const Icon(Icons.star), label: const Text('ثبتِ امتیاز'))));
    }
    if (ab['assign'] == true && widget.isAdmin) {
      if (buttons.isNotEmpty) buttons.add(const SizedBox(width: 8));
      buttons.add(IconButton.filledTonal(onPressed: _assign, icon: const Icon(Icons.person_add_alt), tooltip: 'اختصاص به کارشناس'));
    }
    if (buttons.isEmpty) return const SizedBox.shrink();
    return SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: Row(children: buttons)));
  }

  // ---- اکشن‌ها ----

  Future<void> _reply() async {
    final body = TextEditingController();
    final ok = await showModalBottomSheet<bool>(
      context: context, isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('پاسخ به تیکت', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          TextField(controller: body, maxLines: 4, autofocus: true, decoration: const InputDecoration(labelText: 'متنِ پاسخ')),
          const SizedBox(height: 12),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('ارسال')),
          const SizedBox(height: 16),
        ]),
      ),
    );
    if (ok == true && body.text.trim().isNotEmpty) {
      await _run(() => Api.reply(widget.id, body.text.trim()));
    }
  }

  Future<void> _rate() async {
    int stars = 5;
    final comment = TextEditingController();
    final ok = await showModalBottomSheet<bool>(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setSheet) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('امتیاز به خدماتِ این تیکت', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          Row(mainAxisAlignment: MainAxisAlignment.center, children: List.generate(5, (i) => IconButton(
                iconSize: 36,
                onPressed: () => setSheet(() => stars = i + 1),
                icon: Icon(i < stars ? Icons.star : Icons.star_border, color: AppTheme.warning),
              ))),
          TextField(controller: comment, maxLines: 2, decoration: const InputDecoration(labelText: 'توضیح (اختیاری)')),
          const SizedBox(height: 12),
          FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppTheme.warning),
              onPressed: () => Navigator.pop(ctx, true), child: const Text('ثبتِ امتیاز')),
          const SizedBox(height: 16),
        ]),
      )),
    );
    if (ok == true) {
      await _run(() => Api.rate(widget.id, stars, comment.text.trim().isEmpty ? null : comment.text.trim()));
    }
  }

  Future<void> _assign() async {
    List staff;
    try {
      staff = await Api.staff();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: AppTheme.danger));
      return;
    }
    if (!mounted) return;
    int? chosen;
    final ok = await showModalBottomSheet<bool>(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setSheet) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('اختصاص به کارشناس', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            value: chosen,
            isExpanded: true,
            items: [
              const DropdownMenuItem<int?>(value: null, child: Text('— بدونِ کارشناس —')),
              ...staff.map((s) => DropdownMenuItem<int?>(value: s['id'] as int, child: Text(s['name'] ?? ''))),
            ],
            onChanged: (v) => setSheet(() => chosen = v),
            decoration: const InputDecoration(labelText: 'کارشناس'),
          ),
          const SizedBox(height: 12),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('ثبت')),
          const SizedBox(height: 16),
        ]),
      )),
    );
    if (ok == true) {
      await _run(() => Api.assign(widget.id, chosen));
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
