import 'package:flutter/material.dart';
import '../api.dart';
import '../store.dart';
import '../theme.dart';
import 'login_screen.dart';
import 'ticket_detail_screen.dart';
import 'create_ticket_screen.dart';
import 'invoices_tab.dart';

class HomeScreen extends StatefulWidget {
  final Map<String, dynamic> user;
  const HomeScreen({super.key, required this.user});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;
  Map<String, dynamic>? _dash;
  String? _error;
  final _ticketsKey = GlobalKey<_TicketsTabState>();

  @override
  void initState() {
    super.initState();
    _loadDash();
  }

  Future<void> _loadDash() async {
    try {
      final d = await Api.dashboard();
      if (mounted) setState(() { _dash = d; _error = null; });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

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
    if (_error != null && _dash == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('پرتالِ مشتریان')),
        body: _ErrorView(_error!, _loadDash),
      );
    }
    if (_dash == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final canInvoices = _dash!['can_view_invoices'] == true;
    final canCreate = _dash!['can_create_ticket'] == true;

    final tabs = <Widget>[
      _DashboardTab(data: _dash!, onRefresh: _loadDash),
      _TicketsTab(key: _ticketsKey, isAdmin: widget.user['is_admin'] == true),
      if (canInvoices) const InvoicesTab(),
    ];

    final destinations = <NavigationDestination>[
      const NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'داشبورد'),
      const NavigationDestination(icon: Icon(Icons.confirmation_number_outlined), selectedIcon: Icon(Icons.confirmation_number), label: 'تیکت‌ها'),
      if (canInvoices)
        const NavigationDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long), label: 'فاکتورها'),
    ];

    final titles = ['داشبورد', 'تیکت‌ها', if (canInvoices) 'فاکتورها'];
    final safeTab = _tab < tabs.length ? _tab : 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[safeTab]),
        actions: [
          Center(child: Text(widget.user['name'] ?? '', style: const TextStyle(fontSize: 13))),
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout), tooltip: 'خروج'),
        ],
      ),
      body: IndexedStack(index: safeTab, children: tabs),
      floatingActionButton: (safeTab == 1 && canCreate)
          ? FloatingActionButton(
              backgroundColor: AppTheme.accent,
              onPressed: () async {
                final created = await Navigator.push(
                    context, MaterialPageRoute(builder: (_) => const CreateTicketScreen()));
                if (created == true) {
                  _ticketsKey.currentState?.refresh();
                  _loadDash();
                }
              },
              child: const Icon(Icons.add, color: Colors.white),
            )
          : null,
      bottomNavigationBar: NavigationBar(
        selectedIndex: safeTab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: destinations,
      ),
    );
  }
}

// ---------------------------------------------------------------- داشبورد

class _DashboardTab extends StatelessWidget {
  final Map<String, dynamic> data;
  final Future<void> Function() onRefresh;
  const _DashboardTab({required this.data, required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    final unrated = (data['resolved_unrated'] ?? 0) as int;
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _stat('نیازمندِ رسیدگی', data['needs_attention'], AppTheme.warning, Icons.pending_actions),
          _stat('حل‌شده', data['resolved'], AppTheme.success, Icons.check_circle_outline),
          _stat('پیام‌های خوانده‌نشده', data['unread'], AppTheme.info, Icons.mark_email_unread_outlined),
          if (data['can_view_invoices'] == true)
            _stat('فاکتورهای پرداخت‌نشده', data['unpaid_invoices'], AppTheme.danger, Icons.request_quote_outlined),
          if (unrated > 0)
            Card(
              margin: const EdgeInsets.only(top: 4),
              color: AppTheme.warning.withOpacity(0.08),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(children: [
                  const Icon(Icons.star_outline, color: AppTheme.warning),
                  const SizedBox(width: 12),
                  Expanded(child: Text('$unrated تیکتِ حل‌شده منتظرِ امتیازِ شماست.',
                      style: const TextStyle(fontWeight: FontWeight.w600))),
                ]),
              ),
            ),
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
  final bool isAdmin;
  const _TicketsTab({super.key, required this.isAdmin});
  @override
  State<_TicketsTab> createState() => _TicketsTabState();
}

class _TicketsTabState extends State<_TicketsTab> {
  List _items = [];
  bool _loading = true;
  String? _error;
  final Set<String> _status = {};

  static const _filters = {
    'waiting_customer': 'منتظر شما',
    'waiting_support': 'منتظر پشتیبان',
    'in_progress': 'در حال بررسی',
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
          await Navigator.push(context,
              MaterialPageRoute(builder: (_) => TicketDetailScreen(id: t['id'] as int, isAdmin: widget.isAdmin)));
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
              Text(t['number'] ?? '', style: const TextStyle(fontSize: 11, color: Colors.black54)),
              if (t['by_support'] == true) ...[
                const SizedBox(width: 6),
                _badge('از طرفِ پشتیبانی', AppTheme.info),
              ],
              if (t['rating'] != null) ...[
                const SizedBox(width: 6),
                const Icon(Icons.star, size: 13, color: AppTheme.warning),
                Text('${t['rating']}', style: const TextStyle(fontSize: 11, color: AppTheme.warning)),
              ],
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
