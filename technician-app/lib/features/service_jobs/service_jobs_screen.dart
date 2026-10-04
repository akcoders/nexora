import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'service_job_detail_screen.dart';

class ServiceJobsScreen extends StatefulWidget {
  const ServiceJobsScreen({super.key});

  @override
  State<ServiceJobsScreen> createState() => _ServiceJobsScreenState();
}

class _ServiceJobsScreenState extends State<ServiceJobsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController tabs = TabController(length: 3, vsync: this);
  int reloadKey = 0;

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  Future<List<dynamic>> load(String scope) async {
    final query = <String, dynamic>{};
    if (scope == 'today') query['scope'] = 'today';
    if (scope == 'completed') query['status'] = 'completed';
    final response = await context.read<ApiClient>().dio.get(
      'service-jobs',
      queryParameters: query,
    );
    final jobs = List<dynamic>.from(response.data['data'] ?? []);
    if (scope == 'open') {
      return jobs
          .where(
            (job) => ![
              'completed',
              'cancelled',
              'customer_declined',
            ].contains(job['status']),
          )
          .toList();
    }
    return jobs;
  }

  void refresh() => setState(() => reloadKey++);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My service jobs'),
        actions: [
          IconButton(
            onPressed: refresh,
            icon: const Icon(LucideIcons.refreshCw),
          ),
        ],
        bottom: TabBar(
          controller: tabs,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Today'),
            Tab(text: 'Open'),
            Tab(text: 'Completed'),
          ],
          labelColor: AppColors.primary,
          indicatorColor: AppColors.primary,
        ),
      ),
      body: TabBarView(
        controller: tabs,
        children: [
          _JobList(
            key: ValueKey('today$reloadKey'),
            future: load('today'),
            onRefresh: refresh,
          ),
          _JobList(
            key: ValueKey('open$reloadKey'),
            future: load('open'),
            onRefresh: refresh,
          ),
          _JobList(
            key: ValueKey('completed$reloadKey'),
            future: load('completed'),
            onRefresh: refresh,
          ),
        ],
      ),
    );
  }
}

class _JobList extends StatelessWidget {
  const _JobList({super.key, required this.future, required this.onRefresh});

  final Future<List<dynamic>> future;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
      future: future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ServiceEmpty(
            icon: LucideIcons.cloudOff,
            title: 'Unable to load service jobs',
            subtitle: _errorMessage(snapshot.error),
            onRefresh: onRefresh,
          );
        }
        final jobs = snapshot.data ?? [];
        if (jobs.isEmpty) {
          return _ServiceEmpty(
            icon: LucideIcons.clipboardCheck,
            title: 'No service jobs here',
            subtitle: 'Assigned HVAC service visits will appear here.',
            onRefresh: onRefresh,
          );
        }
        return RefreshIndicator(
          onRefresh: () async => onRefresh(),
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(18),
            itemCount: jobs.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final job = Map<String, dynamic>.from(jobs[index]);
              return _ServiceJobCard(
                job: job,
                onTap: () async {
                  await Navigator.push<void>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ServiceJobDetailScreen(
                        serviceJobId: job['id'] as int,
                      ),
                    ),
                  );
                  onRefresh();
                },
              );
            },
          ),
        );
      },
    );
  }

  static String _errorMessage(Object? error) {
    if (error is DioException) {
      return error.response?.data?['message']?.toString() ??
          'Check your connection and try again.';
    }
    return 'Check your connection and try again.';
  }
}

class _ServiceJobCard extends StatelessWidget {
  const _ServiceJobCard({required this.job, required this.onTap});

  final Map<String, dynamic> job;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final priority = job['priority']?.toString() ?? 'normal';
    final status = job['status']?.toString() ?? 'assigned';
    final statusColor = _statusColor(status);
    final scheduledAt = DateTime.tryParse(
      job['scheduled_at']?.toString() ?? '',
    )?.toLocal();
    final equipment = job['equipment'] as Map<String, dynamic>?;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(17),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  _Pill(
                    text: job['status_label']?.toString() ?? status,
                    color: statusColor,
                  ),
                  const SizedBox(width: 7),
                  if (priority != 'normal')
                    _Pill(
                      text: priority.replaceAll('_', ' ').toUpperCase(),
                      color: priority == 'emergency'
                          ? AppColors.danger
                          : AppColors.warning,
                    ),
                  const Spacer(),
                  Text(
                    job['job_no']?.toString() ?? '',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(
                job['customer']?['name']?.toString() ?? 'Customer',
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                job['complaint']?.toString() ?? 'Service visit',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.muted, height: 1.4),
              ),
              if (equipment != null) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(
                      LucideIcons.airVent,
                      size: 15,
                      color: AppColors.secondary,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        [
                          equipment['equipment_type'],
                          equipment['brand'],
                          equipment['model'],
                        ].where((value) => value != null).join(' · '),
                        style: const TextStyle(fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ],
              const Divider(height: 28),
              Row(
                children: [
                  const Icon(
                    LucideIcons.calendarClock,
                    size: 16,
                    color: AppColors.muted,
                  ),
                  const SizedBox(width: 7),
                  Expanded(
                    child: Text(
                      scheduledAt == null
                          ? 'Schedule not set'
                          : DateFormat(
                              'EEE, d MMM · hh:mm a',
                            ).format(scheduledAt),
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  Text(
                    job['primary_action']?['label']?.toString() ?? 'Open',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.primary),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static Color _statusColor(String status) {
    if (['completed', 'paid', 'customer_approved'].contains(status)) {
      return AppColors.success;
    }
    if (['cancelled', 'customer_declined'].contains(status)) {
      return AppColors.danger;
    }
    if ([
      'on_the_way',
      'arrived',
      'inspection_in_progress',
      'service_in_progress',
    ].contains(status)) {
      return AppColors.primary;
    }
    return AppColors.warning;
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.text, required this.color});
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .11),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      text.toUpperCase(),
      style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800),
    ),
  );
}

class _ServiceEmpty extends StatelessWidget {
  const _ServiceEmpty({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onRefresh,
  });
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onRefresh;

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
            onPressed: onRefresh,
            icon: const Icon(LucideIcons.refreshCw),
            label: const Text('Retry'),
          ),
        ],
      ),
    ),
  );
}
