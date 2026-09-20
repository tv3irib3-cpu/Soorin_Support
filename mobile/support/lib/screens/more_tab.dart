import 'package:flutter/material.dart';
import '../theme.dart';
import 'reports_screen.dart';
import 'contracts_screen.dart';
import 'activity_screen.dart';

/// تبِ «بیشتر» — دسترسی به گزارش‌ها، قراردادها و تاریخچه (هرکدام بر پایهٔ مجوز).
class MoreTab extends StatelessWidget {
  final Map<String, dynamic> dash;
  const MoreTab({super.key, required this.dash});

  @override
  Widget build(BuildContext context) {
    final entries = <_Entry>[
      if (dash['can_view_reports'] == true)
        _Entry(Icons.bar_chart, 'گزارش‌ها', 'درآمد، تیکت، عملکردِ کارشناسان', const ReportsScreen()),
      if (dash['can_view_contracts'] == true)
        _Entry(Icons.description_outlined, 'قراردادها', 'قراردادهای پشتیبانی و پوشش', const ContractsScreen()),
      if (dash['can_view_activity'] == true)
        _Entry(Icons.history, 'تاریخچهٔ تغییرات', 'چه کسی، کِی، چه کاری', const ActivityScreen()),
    ];

    if (entries.isEmpty) {
      return const Center(child: Text('موردی برای نمایش نیست'));
    }

    return ListView(
      padding: const EdgeInsets.all(14),
      children: entries.map((e) => Card(
        child: ListTile(
          leading: CircleAvatar(backgroundColor: AppTheme.accent.withOpacity(0.12),
              child: Icon(e.icon, color: AppTheme.accent)),
          title: Text(e.title, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Text(e.subtitle, style: const TextStyle(fontSize: 12)),
          trailing: const Icon(Icons.chevron_left),
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => e.screen)),
        ),
      )).toList(),
    );
  }
}

class _Entry {
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget screen;
  _Entry(this.icon, this.title, this.subtitle, this.screen);
}
