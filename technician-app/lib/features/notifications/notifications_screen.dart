import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../core/theme/app_theme.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Notifications'),
      actions: [
        TextButton(onPressed: () {}, child: const Text('Mark all read')),
      ],
    ),
    body: ListView(
      padding: const EdgeInsets.all(18),
      children: const [
        _Notification(
          icon: LucideIcons.clipboardList,
          title: 'New task assigned',
          body:
              'Quarterly HVAC preventive maintenance has been assigned to you.',
          time: 'Just now',
          color: AppColors.primary,
        ),
        SizedBox(height: 12),
        _Notification(
          icon: LucideIcons.clock3,
          title: 'Attendance reminder',
          body: 'Remember to mark attendance before taking a task action.',
          time: 'Today, 9:00 AM',
          color: AppColors.warning,
        ),
      ],
    ),
  );
}

class _Notification extends StatelessWidget {
  const _Notification({
    required this.icon,
    required this.title,
    required this.body,
    required this.time,
    required this.color,
  });
  final IconData icon;
  final String title;
  final String body;
  final String time;
  final Color color;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 43,
            height: 43,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: color, size: 21),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 5),
                Text(
                  body,
                  style: const TextStyle(
                    color: AppColors.muted,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  time,
                  style: const TextStyle(color: AppColors.muted, fontSize: 11),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
