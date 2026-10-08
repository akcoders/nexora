import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../service_jobs/service_job_detail_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.onOpenMenu});

  final VoidCallback onOpenMenu;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<dynamic> notifications = [];
  bool loading = true;
  bool marking = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final response = await context.read<ApiClient>().dio.get('notifications');
      notifications = List<dynamic>.from(
        response.data['notifications']?['data'] ?? [],
      );
    } on DioException catch (exception) {
      error =
          exception.response?.data?['message']?.toString() ??
          'Unable to load notifications.';
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> markAllRead() async {
    setState(() => marking = true);
    try {
      await context.read<ApiClient>().dio.post('notifications/read-all');
      await load();
    } on DioException catch (exception) {
      if (mounted) {
        setState(
          () => error =
              exception.response?.data?['message']?.toString() ??
              'Unable to update notifications.',
        );
      }
    } finally {
      if (mounted) setState(() => marking = false);
    }
  }

  Future<void> openNotification(Map<String, dynamic> notification) async {
    if (notification['read_at'] == null) {
      try {
        await context.read<ApiClient>().dio.post(
          'notifications/${notification['id']}/read',
        );
        notification['read_at'] = DateTime.now().toIso8601String();
        if (mounted) setState(() {});
      } on DioException catch (_) {
        // Opening the linked job remains useful even if marking read fails.
      }
    }
    final data = notification['data'] is Map
        ? Map<String, dynamic>.from(notification['data'])
        : <String, dynamic>{};
    if (data['type'] == 'service_job' && data['service_job_id'] != null) {
      if (!mounted) return;
      await Navigator.push<void>(
        context,
        MaterialPageRoute(
          builder: (_) => ServiceJobDetailScreen(
            serviceJobId: (data['service_job_id'] as num).toInt(),
          ),
        ),
      );
      await load();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      leading: IconButton(
        onPressed: widget.onOpenMenu,
        icon: const Icon(LucideIcons.menu),
      ),
      title: const Text('Notifications'),
      actions: [
        TextButton(
          onPressed: marking || notifications.isEmpty ? null : markAllRead,
          child: Text(marking ? 'Updating…' : 'Mark all read'),
        ),
      ],
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null && notifications.isEmpty
        ? _NotificationState(
            icon: LucideIcons.cloudOff,
            title: 'Unable to load notifications',
            subtitle: error!,
            onRetry: load,
          )
        : notifications.isEmpty
        ? _NotificationState(
            icon: LucideIcons.bellOff,
            title: 'No notifications',
            subtitle: 'New service jobs and task alerts will appear here.',
            onRetry: load,
          )
        : RefreshIndicator(
            onRefresh: load,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(18),
              itemCount: notifications.length,
              separatorBuilder: (_, _) => const SizedBox(height: 11),
              itemBuilder: (context, index) {
                final notification = Map<String, dynamic>.from(
                  notifications[index],
                );
                return _NotificationCard(
                  notification: notification,
                  onTap: () => openNotification(notification),
                );
              },
            ),
          ),
  );
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.notification, required this.onTap});

  final Map<String, dynamic> notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final data = notification['data'] is Map
        ? Map<String, dynamic>.from(notification['data'])
        : <String, dynamic>{};
    final serviceJob = data['type'] == 'service_job';
    final unread = notification['read_at'] == null;
    final created = DateTime.tryParse(
      notification['created_at']?.toString() ?? '',
    )?.toLocal();
    final color = serviceJob ? AppColors.primary : AppColors.warning;

    return Card(
      color: unread ? const Color(0xFFF8FBFF) : Colors.white,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
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
                child: Icon(
                  serviceJob ? LucideIcons.wrench : LucideIcons.clipboardList,
                  color: color,
                  size: 21,
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            data['title']?.toString() ?? 'Notification',
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        if (unread)
                          Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: color,
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      data['message']?.toString() ?? '',
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 13,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      created == null
                          ? ''
                          : DateFormat('d MMM, hh:mm a').format(created),
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
              if (serviceJob)
                const Icon(Icons.chevron_right, color: AppColors.primary),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotificationState extends StatelessWidget {
  const _NotificationState({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onRetry,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(30),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 45, color: AppColors.muted),
          const SizedBox(height: 14),
          Text(
            title,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: const TextStyle(color: AppColors.muted),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(LucideIcons.refreshCw),
            label: const Text('Retry'),
          ),
        ],
      ),
    ),
  );
}
