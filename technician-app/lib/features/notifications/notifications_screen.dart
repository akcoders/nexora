import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import '../complaints/complaint_visit_screen.dart';
import '../service_jobs/service_job_detail_screen.dart';
import '../tasks/tasks_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({
    super.key,
    required this.onOpenMenu,
    this.onUnreadChanged,
  });

  final VoidCallback onOpenMenu;
  final ValueChanged<int>? onUnreadChanged;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<Map<String, dynamic>> notifications = [];
  bool loading = true;
  bool marking = false;
  bool unreadOnly = false;
  String? error;

  int get unreadCount =>
      notifications.where((item) => item['read_at'] == null).length;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) {
      setState(() {
        loading = notifications.isEmpty;
        error = null;
      });
    }
    try {
      final response = await context.read<ApiClient>().dio.get('notifications');
      notifications = List<dynamic>.from(
        response.data['notifications']?['data'] ?? [],
      ).map((item) => Map<String, dynamic>.from(item)).toList();
      widget.onUnreadChanged?.call(
        (response.data['unread_count'] as num?)?.toInt() ?? unreadCount,
      );
    } catch (exception) {
      error = apiErrorMessage(exception, 'Unable to load notifications.');
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> markAllRead() async {
    setState(() => marking = true);
    try {
      await context.read<ApiClient>().dio.post('notifications/read-all');
      await load();
      if (mounted) {
        showAppSnack(
          context,
          'All notifications marked as read.',
          tone: Tone.success,
        );
      }
    } catch (exception) {
      if (mounted) {
        showAppSnack(
          context,
          apiErrorMessage(exception, 'Unable to update notifications.'),
          tone: Tone.danger,
        );
      }
    } finally {
      if (mounted) setState(() => marking = false);
    }
  }

  Future<void> open(Map<String, dynamic> notification) async {
    if (notification['read_at'] == null) {
      try {
        await context.read<ApiClient>().dio.post(
          'notifications/${notification['id']}/read',
        );
        notification['read_at'] = DateTime.now().toIso8601String();
        widget.onUnreadChanged?.call(unreadCount);
        if (mounted) setState(() {});
      } catch (_) {
        // Opening the linked record remains useful even if marking read fails.
      }
    }
    final data = notification['data'] is Map
        ? Map<String, dynamic>.from(notification['data'])
        : <String, dynamic>{};
    final type = data['type']?.toString() ?? '';
    if (!mounted) return;
    if (type == 'service_job' && data['service_job_id'] != null) {
      await Navigator.push<void>(
        context,
        MaterialPageRoute(
          builder: (_) => ServiceJobDetailScreen(
            serviceJobId: (data['service_job_id'] as num).toInt(),
          ),
        ),
      );
    } else if (type == 'complaint_visit' &&
        data['complaint_visit_id'] != null) {
      await Navigator.push<void>(
        context,
        MaterialPageRoute(
          builder: (_) => ComplaintVisitScreen(
            visitId: (data['complaint_visit_id'] as num).toInt(),
          ),
        ),
      );
    } else if (type.endsWith('_task') && data['task_id'] != null) {
      await showTaskDetails(
        context,
        (data['task_id'] as num).toInt(),
        onUpdated: () {},
      );
    } else {
      return;
    }
    await load();
  }

  static String dayLabel(DateTime date) {
    final today = DateUtils.dateOnly(DateTime.now());
    final day = DateUtils.dateOnly(date);
    if (day == today) return 'Today';
    if (day == today.subtract(const Duration(days: 1))) return 'Yesterday';
    return DateFormat('EEEE, d MMMM').format(date);
  }

  @override
  Widget build(BuildContext context) {
    final visible = unreadOnly
        ? notifications.where((item) => item['read_at'] == null).toList()
        : notifications;
    final rows = <Object>[];
    String? lastDay;
    for (final item in visible) {
      final created = DateTime.tryParse(
        item['created_at']?.toString() ?? '',
      )?.toLocal();
      final label = created == null ? 'Earlier' : dayLabel(created);
      if (label != lastDay) {
        rows.add(label);
        lastDay = label;
      }
      rows.add(item);
    }

    return Scaffold(
      appBar: AppBar(
        leading: MenuButton(onPressed: widget.onOpenMenu),
        title: const T('Notifications'),
        actions: [
          TextButton.icon(
            onPressed: marking || unreadCount == 0 ? null : markAllRead,
            icon: const Icon(LucideIcons.checkCheck, size: 17),
            label: T(marking ? 'Updating…' : 'Mark all read'),
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: loading
          ? const LoadingView()
          : Column(
              children: [
                Container(
                  color: Colors.white,
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                  child: Row(
                    children: [
                      for (final (value, label) in [
                        (false, 'All'),
                        (true, 'Unread'),
                      ]) ...[
                        ChoiceChip(
                          label: Text(
                            value
                                ? '${context.tr(label)} ($unreadCount)'
                                : '${context.tr(label)} (${notifications.length})',
                          ),
                          selected: unreadOnly == value,
                          labelStyle: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                            color: unreadOnly == value
                                ? AppColors.primary
                                : AppColors.ink,
                          ),
                          side: BorderSide(
                            color: unreadOnly == value
                                ? AppColors.primary
                                : AppColors.border,
                          ),
                          onSelected: (_) => setState(() => unreadOnly = value),
                        ),
                        const SizedBox(width: 8),
                      ],
                    ],
                  ),
                ),
                const Divider(),
                Expanded(
                  child: RefreshIndicator(
                    onRefresh: load,
                    child: error != null && notifications.isEmpty
                        ? EmptyState(
                            icon: LucideIcons.cloudOff,
                            title: 'Unable to load notifications',
                            message: error,
                            actionLabel: 'Retry',
                            onAction: load,
                          )
                        : visible.isEmpty
                        ? EmptyState(
                            icon: LucideIcons.bellOff,
                            title: unreadOnly
                                ? 'No unread notifications'
                                : 'No notifications',
                            message:
                                'New service jobs, complaint visits and workflow alerts will appear here.',
                          )
                        : ListView.builder(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                            itemCount: rows.length,
                            itemBuilder: (context, index) {
                              final row = rows[index];
                              if (row is String) {
                                return Padding(
                                  padding: const EdgeInsets.only(
                                    top: 14,
                                    bottom: 8,
                                  ),
                                  child: T(
                                    row.toUpperCase(),
                                    style: const TextStyle(
                                      color: AppColors.muted,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      letterSpacing: .8,
                                    ),
                                  ),
                                );
                              }
                              final notification = row as Map<String, dynamic>;
                              return Padding(
                                padding: const EdgeInsets.only(bottom: 8),
                                child: _NotificationCard(
                                  notification: notification,
                                  onTap: () => open(notification),
                                ),
                              );
                            },
                          ),
                  ),
                ),
              ],
            ),
    );
  }
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
    final type = data['type']?.toString() ?? '';
    final unread = notification['read_at'] == null;
    final created = DateTime.tryParse(
      notification['created_at']?.toString() ?? '',
    )?.toLocal();
    final (icon, tone, linked) = switch (type) {
      'service_job' => (
        LucideIcons.wrench,
        Tone.primary,
        data['service_job_id'] != null,
      ),
      'complaint_visit' => (
        LucideIcons.clipboardList,
        Tone.purple,
        data['complaint_visit_id'] != null,
      ),
      _ when type.endsWith('_task') => (
        LucideIcons.gitBranch,
        Tone.warning,
        data['task_id'] != null,
      ),
      _ => (LucideIcons.bell, Tone.slate, false),
    };

    return Card(
      color: unread ? const Color(0xFFF5F9FF) : Colors.white,
      child: InkWell(
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            border: Border(
              left: BorderSide(
                color: unread ? tone.color : Colors.transparent,
                width: 3,
              ),
            ),
          ),
          padding: const EdgeInsets.fromLTRB(12, 12, 10, 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconTile(icon, tone: tone, size: 38),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            data['title']?.toString() ?? 'Notification',
                            style: TextStyle(
                              fontWeight: unread
                                  ? FontWeight.w700
                                  : FontWeight.w600,
                              fontSize: 13.5,
                              color: AppColors.heading,
                            ),
                          ),
                        ),
                        Text(
                          created == null
                              ? ''
                              : DateFormat('hh:mm a').format(created),
                          style: const TextStyle(
                            color: AppColors.muted,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      data['message']?.toString() ?? '',
                      style: const TextStyle(
                        color: AppColors.ink,
                        fontSize: 12.5,
                        height: 1.45,
                      ),
                    ),
                  ],
                ),
              ),
              if (linked) ...[
                const SizedBox(width: 4),
                const Padding(
                  padding: EdgeInsets.only(top: 8),
                  child: Icon(
                    LucideIcons.chevronRight,
                    size: 17,
                    color: AppColors.subtle,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
