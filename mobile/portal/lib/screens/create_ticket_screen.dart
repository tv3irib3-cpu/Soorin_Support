import 'package:flutter/material.dart';
import '../api.dart';
import '../theme.dart';

class CreateTicketScreen extends StatefulWidget {
  const CreateTicketScreen({super.key});
  @override
  State<CreateTicketScreen> createState() => _CreateTicketScreenState();
}

class _CreateTicketScreenState extends State<CreateTicketScreen> {
  final _subject = TextEditingController();
  final _desc = TextEditingController();
  int? _project;
  int? _category;
  String _priority = 'normal';
  List _projects = [];
  List _categories = [];
  Map<String, dynamic> _priorities = {};
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
      final d = await Api.formData();
      setState(() {
        _projects = d['projects'] as List;
        _categories = d['categories'] as List;
        _priorities = (d['priorities'] as Map).cast<String, dynamic>();
        _loading = false;
      });
    } catch (e) {
      setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _save() async {
    if (_subject.text.trim().isEmpty || _desc.text.trim().isEmpty) {
      setState(() => _error = 'موضوع و شرح لازم است.');
      return;
    }
    setState(() { _saving = true; _error = null; });
    try {
      await Api.createTicket({
        'subject': _subject.text.trim(),
        'description': _desc.text.trim(),
        'priority': _priority,
        if (_project != null) 'customer_project_id': _project,
        if (_category != null) 'ticket_category_id': _category,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() { _error = '$e'; _saving = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تیکتِ جدید')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_error != null)
                  Container(
                    padding: const EdgeInsets.all(10),
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
                    child: Text(_error!, style: const TextStyle(color: AppTheme.danger)),
                  ),
                TextField(controller: _subject, decoration: const InputDecoration(labelText: 'موضوع')),
                const SizedBox(height: 12),
                TextField(controller: _desc, maxLines: 5, decoration: const InputDecoration(labelText: 'شرحِ مشکل')),
                const SizedBox(height: 12),
                if (_projects.isNotEmpty) ...[
                  DropdownButtonFormField<int?>(
                    value: _project,
                    isExpanded: true,
                    items: [
                      const DropdownMenuItem<int?>(value: null, child: Text('— بدونِ پروژه —')),
                      ..._projects.map<DropdownMenuItem<int?>>((c) =>
                          DropdownMenuItem(value: c['id'] as int, child: Text(c['name'] ?? ''))),
                    ],
                    onChanged: (v) => setState(() => _project = v),
                    decoration: const InputDecoration(labelText: 'پروژه (اختیاری)'),
                  ),
                  const SizedBox(height: 12),
                ],
                if (_categories.isNotEmpty) ...[
                  DropdownButtonFormField<int?>(
                    value: _category,
                    isExpanded: true,
                    items: [
                      const DropdownMenuItem<int?>(value: null, child: Text('— بدونِ دسته‌بندی —')),
                      ..._categories.map<DropdownMenuItem<int?>>((c) =>
                          DropdownMenuItem(value: c['id'] as int, child: Text(c['name'] ?? ''))),
                    ],
                    onChanged: (v) => setState(() => _category = v),
                    decoration: const InputDecoration(labelText: 'دسته‌بندی (اختیاری)'),
                  ),
                  const SizedBox(height: 12),
                ],
                DropdownButtonFormField<String>(
                  value: _priority,
                  items: _priorities.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
                  onChanged: (v) => setState(() => _priority = v ?? 'normal'),
                  decoration: const InputDecoration(labelText: 'اولویت'),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('ثبتِ تیکت'),
                ),
              ],
            ),
    );
  }
}
