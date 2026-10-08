import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../auth/auth_controller.dart';
import '../service_jobs/service_job_detail_screen.dart';
import '../service_jobs/service_jobs_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.onNavigate,
    required this.onOpenMenu,
    required this.refreshKey,
  });

  final ValueChanged<int> onNavigate;
  final VoidCallback onOpenMenu;
  final int refreshKey;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic>? today;
  List<dynamic> activeTasks = [];
  List<dynamic> todayServiceJobs = [];
  int closedCount = 0;
  bool loading = true;
  bool checkingOut = false;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void didUpdateWidget(covariant HomeScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.refreshKey != widget.refreshKey) load();
  }

  Future<void> load() async {
    if (mounted) setState(() => loading = true);
    try {
      final api = context.read<ApiClient>().dio;
      final responses = await Future.wait([
        api.get('attendance/today'),
        api.get('tasks', queryParameters: {'scope': 'open'}),
        api.get('tasks', queryParameters: {'status': 'closed'}),
        api.get('service-jobs', queryParameters: {'scope': 'today'}),
      ]);
      today = responses[0].data['attendance'];
      activeTasks = List<dynamic>.from(responses[1].data['data'] ?? []);
      closedCount =
          (responses[2].data['total'] as num?)?.toInt() ??
          List<dynamic>.from(responses[2].data['data'] ?? []).length;
      todayServiceJobs = List<dynamic>.from(responses[3].data['data'] ?? []);
    } catch (_) {
      // Keep the dashboard usable and allow a manual refresh.
    }
    if (mounted) setState(() => loading = false);
  }

  Future<Position> _locate() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw Exception('Turn on location services to check out.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw Exception('Location permission is required to check out.');
    }
    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    );
  }

  Future<void> checkOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(LucideIcons.logOut, color: AppColors.danger),
        title: const Text('Confirm checkout'),
        content: const Text(
          'Your current location and checkout time will be recorded. Continue?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Check out'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final api = context.read<ApiClient>().dio;
    setState(() => checkingOut = true);
    try {
      final position = await _locate();
      final response = await api.post(
        'attendance/check-out',
        data: {'latitude': position.latitude, 'longitude': position.longitude},
      );
      today = Map<String, dynamic>.from(response.data['attendance']);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Checkout recorded successfully.'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } on DioException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error.response?.data?['message']?.toString() ??
                  'Unable to check out.',
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => checkingOut = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    final firstName = (user?['name']?.toString() ?? 'Technician')
        .split(' ')
        .first;
    final hasCheckedIn = today != null;
    final hasCheckedOut = today?['checked_out_at'] != null;
    final priorityTask = activeTasks.isEmpty
        ? null
        : Map<String, dynamic>.from(activeTasks.first);
    final cardColors = hasCheckedIn
        ? const [Color(0xFF166534), Color(0xFF16A34A), Color(0xFF22C55E)]
        : const [Color(0xFF1E3A8A), AppColors.primary, AppColors.secondary];

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: widget.onOpenMenu,
          icon: const Icon(LucideIcons.menu),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Good ${DateTime.now().hour < 12 ? 'morning' : 'afternoon'}, $firstName',
              style: const TextStyle(fontSize: 19),
            ),
            Text(
              DateFormat('EEEE, d MMMM').format(DateTime.now()),
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.muted,
                fontWeight: FontWeight.w400,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(onPressed: load, icon: const Icon(LucideIcons.refreshCw)),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 28),
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 350),
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: cardColors),
                borderRadius: BorderRadius.circular(22),
                boxShadow: [
                  BoxShadow(
                    color: cardColors[1].withValues(alpha: .24),
                    blurRadius: 25,
                    offset: const Offset(0, 12),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(LucideIcons.mapPin, color: Colors.white70, size: 18),
                      SizedBox(width: 7),
                      Text(
                        'TODAY\'S ATTENDANCE',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 11,
                          letterSpacing: 1.2,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Text(
                    !hasCheckedIn
                        ? 'Not checked in yet'
                        : hasCheckedOut
                        ? 'Shift completed'
                        : 'You are checked in',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    !hasCheckedIn
                        ? 'Mark attendance before starting field work'
                        : hasCheckedOut
                        ? '${_time(today?['checked_in_at'])} – ${_time(today?['checked_out_at'])}'
                        : 'Checked in at ${_time(today?['checked_in_at'])}',
                    style: const TextStyle(color: Colors.white70, fontSize: 13),
                  ),
                  const SizedBox(height: 20),
                  if (loading)
                    const LinearProgressIndicator(color: Colors.white)
                  else if (!hasCheckedIn)
                    FilledButton.icon(
                      onPressed: () => widget.onNavigate(2),
                      style: _whiteButton(AppColors.primary),
                      icon: const Icon(LucideIcons.camera, size: 18),
                      label: const Text('Check in now'),
                    )
                  else if (!hasCheckedOut)
                    FilledButton.icon(
                      onPressed: () => widget.onNavigate(2),
                      style: _whiteButton(const Color(0xFF15803D)),
                      icon: const Icon(LucideIcons.camera, size: 18),
                      label: const Text('Selfie & check out'),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'Today at a glance',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetricCard(
                    label: 'Active',
                    value: activeTasks.length.toString(),
                    icon: LucideIcons.clock3,
                    color: AppColors.warning,
                    soft: const Color(0xFFFFF7E6),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _MetricCard(
                    label: 'Completed',
                    value: closedCount.toString(),
                    icon: LucideIcons.checkCircle2,
                    color: AppColors.success,
                    soft: const Color(0xFFEAF8EF),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Today’s service visits',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                TextButton(
                  onPressed: () async {
                    await Navigator.push<void>(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const ServiceJobsScreen(),
                      ),
                    );
                    await load();
                  },
                  child: Text(
                    todayServiceJobs.isEmpty
                        ? 'View jobs'
                        : '${todayServiceJobs.length} jobs',
                  ),
                ),
              ],
            ),
            if (todayServiceJobs.isEmpty)
              Card(
                child: InkWell(
                  borderRadius: BorderRadius.circular(18),
                  onTap: () async {
                    await Navigator.push<void>(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const ServiceJobsScreen(),
                      ),
                    );
                    await load();
                  },
                  child: const Padding(
                    padding: EdgeInsets.all(18),
                    child: Row(
                      children: [
                        Icon(
                          LucideIcons.briefcaseBusiness,
                          color: AppColors.primary,
                        ),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'No service visit scheduled today. Open all assigned jobs.',
                            style: TextStyle(color: AppColors.muted),
                          ),
                        ),
                        Icon(Icons.chevron_right, color: AppColors.primary),
                      ],
                    ),
                  ),
                ),
              )
            else
              _TodayServiceJob(
                job: Map<String, dynamic>.from(todayServiceJobs.first),
                remaining: todayServiceJobs.length - 1,
                onTap: () async {
                  await Navigator.push<void>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ServiceJobDetailScreen(
                        serviceJobId: todayServiceJobs.first['id'] as int,
                      ),
                    ),
                  );
                  await load();
                },
              ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Priority task',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                TextButton(
                  onPressed: () => widget.onNavigate(1),
                  child: const Text('View all'),
                ),
              ],
            ),
            if (priorityTask == null)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(22),
                  child: Text(
                    'No active tasks. You are all caught up.',
                    style: TextStyle(color: AppColors.muted),
                  ),
                ),
              )
            else
              _PriorityTask(
                task: priorityTask,
                onTap: () => widget.onNavigate(1),
              ),
          ],
        ),
      ),
    );
  }

  ButtonStyle _whiteButton(Color foreground) => FilledButton.styleFrom(
    backgroundColor: Colors.white,
    foregroundColor: foreground,
    minimumSize: const Size(0, 45),
    padding: const EdgeInsets.symmetric(horizontal: 18),
  );

  static String _time(dynamic value) {
    final date = DateTime.tryParse(value?.toString() ?? '');
    return date == null ? '—' : DateFormat('hh:mm a').format(date.toLocal());
  }
}

class _TodayServiceJob extends StatelessWidget {
  const _TodayServiceJob({
    required this.job,
    required this.remaining,
    required this.onTap,
  });

  final Map<String, dynamic> job;
  final int remaining;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheduledAt = DateTime.tryParse(
      job['scheduled_at']?.toString() ?? '',
    )?.toLocal();
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  LucideIcons.airVent,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      job['customer']?['name']?.toString() ?? 'Customer',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      scheduledAt == null
                          ? job['status_label']?.toString() ?? 'Assigned'
                          : '${DateFormat('hh:mm a').format(scheduledAt)} · ${job['status_label'] ?? 'Assigned'}',
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                    if (remaining > 0) ...[
                      const SizedBox(height: 4),
                      Text(
                        '+$remaining more visit${remaining == 1 ? '' : 's'} today',
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppColors.primary),
            ],
          ),
        ),
      ),
    );
  }
}

class _PriorityTask extends StatelessWidget {
  const _PriorityTask({required this.task, required this.onTap});

  final Map<String, dynamic> task;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Badge(
                  label: Text(
                    (task['priority']?.toString() ?? 'normal').toUpperCase(),
                  ),
                  backgroundColor: AppColors.warning,
                  textColor: Colors.white,
                ),
                const SizedBox(width: 8),
                Badge(
                  label: Text(
                    task['task_type'] == 'ticket' ? 'TICKET' : 'WORKFLOW',
                  ),
                  backgroundColor: task['task_type'] == 'ticket'
                      ? AppColors.secondary
                      : AppColors.primary,
                  textColor: Colors.white,
                ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              task['title']?.toString() ?? 'Untitled task',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 7),
            Text(
              task['customer']?['name']?.toString() ?? 'Internal workflow',
              style: const TextStyle(color: AppColors.muted),
            ),
          ],
        ),
      ),
    ),
  );
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    required this.soft,
  });
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final Color soft;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: soft,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: color, size: 21),
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                label,
                style: const TextStyle(color: AppColors.muted, fontSize: 12),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}
