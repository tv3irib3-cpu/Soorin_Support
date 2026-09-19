import 'package:flutter/material.dart';
import '../api.dart';
import '../store.dart';
import '../theme.dart';
import 'login_screen.dart';
import 'ticket_detail_screen.dart';
import 'create_ticket_screen.dart';

class HomeScreen extends StatefulWidget {
  final Map<String, dynamic> user;
  const HomeScreen({super.key, required this.user});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;
  final _ticketsKey = GlobalKey<_TicketsTabState>();

  Future<void> _logout() async {
    await Api.logout();
    await Store.clearToken();
    await Store.clearUser();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_tab == 0 ? 'داشبورد' : 'تیکت‌ها'),
        actions: [
          Center(child: Text(widget.user['name'] ?? '', style: const TextStyle(fontSize: 13))),
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout), tooltip: 'خروج'),
        ],
      ),
      body: IndexedStack(
        index: _tab,
        children: [
          const _DashboardTab(),
          _TicketsTab(key: _ticketsKey),
        ],
      ),
      floatingActionButton: _tab == 1
          ? FloatingActionButton(
              backgroundColor: AppTheme.accent,
              onPressed: () async {
                final created = await Navigator.push(
                    context, MaterialPageRoute(builder: (_) => const CreateTicketScreen()));
                if (created == true) _ticketsKey.currentState?.refresh();
              },
              child: const Icon(Icons.add, color: Colors.white),
            )
          : null,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'داشبورد'),
          NavigationDestination(icon: Icon(Icons.confirmation_number_outlined), selectedIcon: Icon(Icons.confirmation_number), label: 'تیکت‌ها'),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------- داشبورد

class _DashboardTab extends StatefulWidget {
  const _DashboardTab();
  @override
  State<_DashboardTab> createState() => _DashboardTabState();
}

class _DashboardTabState extends State<_DashboardTab> {
  Map<String, dynamic>? _data;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d = await Api.dashboard();
      if (mounted) setState(() { _data = d; _error = null; });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) return _ErrorView(_error!, _load);
    if (_data == null) return const Center(child: CircularProgressIndicator());
    final d = _data!;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _stat('نیازمندِ رسیدگی', d['needs_attention'], AppTheme.danger, Icons.notifications_active),
          _stat('تیکت‌های باز', d['open'], AppTheme.info, Icons.chat_bubble_outline),
          _stat('حل‌شده', d['resolved'], AppTheme.success, Icons.check_circle_outline),
          _stat('پیام‌های خوانده‌نشده', d['unread'], AppTheme.warning, Icons.mark_email_unread_outlined),
        ],
      ),
    );
  }

  Widget _stat(String label, dynamic value, Color color, IconData icon) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(12)),
              child: Icon(icon, color: color),
            ),
            const SizedBox(width: 14),
            Expanded(child: Text(label, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600))),
            Text('${value ?? 0}', style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: color)),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------- تیکت‌ها

class _TicketsTab extends StatefulWidget {
  const _TicketsTab({super.key});
  @override
  State<_TicketsTab> createState() => _TicketsTabState();
}

class _TicketsTabState extends State<_TicketsTab> {
  List _items = [];
  bool _loading = true;
  String? _error;
  final Set<String> _status = {};

  static const _filters = {
    'waiting_support': 'منتظر پشتیبان',
    'in_progress': 'در حال بررسی',
    'waiting_customer': 'منتظر مشتری',
    'resolved': 'حل‌شده',
  };

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() => _loading = true);
    try {
      final res = await Api.tickets(status: _status.toList());
      if (mounted) setState(() { _items = res['data'] as List; _error = null; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 52,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            children: _filters.entries.map((e) {
              final on = _status.contains(e.key);
              return Padding(
                padding: const EdgeInsets.only(left: 8),
                child: FilterChip(
                  label: Text(e.value),
                  selected: on,
                  onSelected: (_) {
                    setState(() => on ? _status.remove(e.key) : _status.add(e.key));
                    refresh();
                  },
                ),
              );
            }).toList(),
          ),
        ),
        Expanded(
          child: _error != null
              ? _ErrorView(_error!, refresh)
              : _loading
                  ? const Center(child: CircularProgressIndicator())
                  : RefreshIndicator(
                      onRefresh: refresh,
                      child: _items.isEmpty
                          ? ListView(children: const [SizedBox(height: 120), Center(child: Text('تیکتی نیست'))])
                          : ListView.separated(
                              padding: const EdgeInsets.all(12),
                              itemCount: _items.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (_, i) => _tile(_items[i]),
                            ),
                    ),
        ),
      ],
    );
  }

  Widget _tile(Map<String, dynamic> t) {
    final unread = (t['unread'] ?? 0) as int;
    return Card(
      child: ListTile(
        onTap: () async {
          await Navigator.push(context, MaterialPageRoute(builder: (_) => TicketDetailScreen(id: t['id'] as int)));
          refresh();
        },
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        leading: Container(width: 6, height: 44, decoration: BoxDecoration(
            color: AppTheme.priorityColor(t['priority'] ?? 'low'), borderRadius: BorderRadius.circular(4))),
        title: Row(
          children: [
            Expanded(child: Text(t['subject'] ?? '', maxLines: 1, overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w600))),
            if (unread > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(color: AppTheme.danger, borderRadius: BorderRadius.circular(20)),
                child: Text('$unread', style: const TextStyle(color: Colors.white, fontSize: 11)),
              ),
          ],
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Row(
            children: [
              Text(t['customer'] ?? '—', style: const TextStyle(fontSize: 12)),
              const Spacer(),
              _badge(t['status_label'] ?? '', AppTheme.statusColor(t['status'] ?? '')),
            ],
          ),
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

// ---------------------------------------------------------------- خطا

class _ErrorView extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const _ErrorView(this.message, this.onRetry);
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.redAccent),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('تلاشِ دوباره')),
          ],
        ),
      ),
    );
  }
}
