import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import '../auth/auth_controller.dart';
import '../complaints/complaint_visit_screen.dart';
import '../hr/hr_self_service_screen.dart';
import '../service_jobs/service_job_detail_screen.dart';
import '../tasks/tasks_screen.dart';
import 'app_shell.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.onNavigate,
    required this.onOpenMenu,
    required this.onOpenHr,
    required this.refreshKey,
  });

  final void Function(int page, {int? tab}) onNavigate;
  final VoidCallback onOpenMenu;
  final Future<void> Function(int tab) onOpenHr;
  final int refreshKey;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  static const closedJobStatuses = {
    'completed',
    'cancelled',
    'customer_declined',
  };

  Map<String, dynamic>? today;
  List<dynamic> workflow = [];
  List<dynamic> todayJobs = [];
  List<dynamic> openJobs = [];
  List<dynamic> visits = [];
  bool loading = true;
  bool failed = false;

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

  /// Each block loads on its own so one failing endpoint never blanks the dashboard.
  Future<void> load() async {
    if (mounted) setState(() => loading = true);
    final api = context.read<ApiClient>().dio;
    var failures = 0;
    Future<R?> fetch<R>(Future<R> Function() request) async {
      try {
        return await request();
      } catch (_) {
        failures++;
        return null;
      }
    }

    final results = await Future.wait([
      fetch(() => api.get('attendance/today')),
      fetch(
        () => api.get(
          'tasks',
          queryParameters: {'scope': 'open', 'type': 'workflow'},
        ),
      ),
      fetch(() => api.get('service-jobs', queryParameters: {'scope': 'today'})),
      fetch(() => api.get('service-jobs')),
      fetch(
        () => api.get('complaint-visits', queryParameters: {'scope': 'open'}),
      ),
    ]);
    if (!mounted) return;
    setState(() {
      if (results[0] != null) today = results[0]!.data['attendance'];
      if (results[1] != null) {
        workflow = List<dynamic>.from(results[1]!.data['data'] ?? []);
      }
      if (results[2] != null) {
        todayJobs = List<dynamic>.from(results[2]!.data['data'] ?? []);
      }
      if (results[3] != null) {
        openJobs = List<dynamic>.from(
          results[3]!.data['data'] ?? [],
        ).where((job) => !closedJobStatuses.contains(job['status'])).toList();
      }
      if (results[4] != null) {
        visits = List<dynamic>.from(results[4]!.data['data'] ?? []);
      }
      failed = failures == results.length;
      loading = false;
    });
  }

  Future<void> openPage(Widget page) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(builder: (_) => page),
    );
    await load();
  }

  int get overdueCount => workflow.where((task) {
    final due = DateTime.tryParse(task['due_at']?.toString() ?? '');
    return due != null && due.isBefore(DateTime.now());
  }).length;

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user ?? const {};
    final name = user['name']?.toString() ?? 'Technician';
    final hour = DateTime.now().hour;
    final greeting = hour < 12
        ? 'Good morning'
        : hour < 17
        ? 'Good afternoon'
        : 'Good evening';

    return Scaffold(
      appBar: AppBar(
        leading: MenuButton(onPressed: widget.onOpenMenu),
        title: const T('Dashboard'),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => widget.onNavigate(AppPage.alerts),
            icon: const Icon(LucideIcons.bell),
          ),
          IconButton(
            tooltip: 'Refresh',
            onPressed: loading ? null : load,
            icon: const Icon(LucideIcons.refreshCw),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
          children: [
            _WelcomePanel(
              greeting: greeting,
              name: name,
              designation: user['designation']?.toString(),
              attendance: today,
              loading: loading && today == null,
              onAttendance: () => widget.onNavigate(AppPage.attendance),
            ),
            if (failed) ...[
              const SizedBox(height: 14),
              const NoticeBox(
                'Could not reach the server. Pull down to try again.',
                icon: LucideIcons.cloudOff,
              ),
            ],
            const SizedBox(height: 16),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 2.25,
              children: [
                KpiTile(
                  label: "Today's jobs",
                  value: '${todayJobs.length}',
                  icon: LucideIcons.wrench,
                  onTap: () =>
                      widget.onNavigate(AppPage.work, tab: WorkTab.jobs),
                ),
                KpiTile(
                  label: 'Complaint visits',
                  value: '${visits.length}',
                  icon: LucideIcons.clipboardList,
                  tone: Tone.purple,
                  onTap: () =>
                      widget.onNavigate(AppPage.work, tab: WorkTab.complaints),
                ),
                KpiTile(
                  label: 'Pending workflow',
                  value: '${workflow.length}',
                  icon: LucideIcons.gitBranch,
                  tone: Tone.warning,
                  onTap: () =>
                      widget.onNavigate(AppPage.work, tab: WorkTab.workflow),
                ),
                KpiTile(
                  label: 'Overdue workflow',
                  value: '$overdueCount',
                  icon: LucideIcons.alarmClock,
                  tone: overdueCount > 0 ? Tone.danger : Tone.success,
                  onTap: () =>
                      widget.onNavigate(AppPage.work, tab: WorkTab.workflow),
                ),
              ],
            ),
            const SectionLabel('Quick actions'),
            Row(
              children: [
                _QuickAction(
                  icon: LucideIcons.scanFace,
                  label: 'Attendance',
                  tone: Tone.success,
                  onTap: () => widget.onNavigate(AppPage.attendance),
                ),
                _QuickAction(
                  icon: LucideIcons.calendarPlus,
                  label: 'Apply leave',
                  tone: Tone.primary,
                  onTap: () => widget.onOpenHr(HrTab.leave),
                ),
                _QuickAction(
                  icon: LucideIcons.receiptIndianRupee,
                  label: 'Expense',
                  tone: Tone.orange,
                  onTap: () => widget.onOpenHr(HrTab.vouchers),
                ),
                _QuickAction(
                  icon: LucideIcons.walletCards,
                  label: 'Payslips',
                  tone: Tone.info,
                  onTap: () => widget.onOpenHr(HrTab.payslips),
                ),
              ],
            ),
            SectionLabel(
              "Today's service visits",
              action: 'All jobs',
              onAction: () =>
                  widget.onNavigate(AppPage.work, tab: WorkTab.jobs),
            ),
            if (todayJobs.isEmpty)
              _EmptyCard(
                icon: LucideIcons.calendarCheck2,
                message: openJobs.isEmpty
                    ? 'No service visit scheduled today.'
                    : 'No visit today · ${openJobs.length} open job${openJobs.length == 1 ? '' : 's'} assigned',
              )
            else
              ...todayJobs.take(3).map((raw) {
                final job = Map<String, dynamic>.from(raw);
                final scheduled = DateTime.tryParse(
                  job['scheduled_at']?.toString() ?? '',
                )?.toLocal();
                return _WorkRow(
                  icon: LucideIcons.airVent,
                  tone: Tone.primary,
                  title: job['customer']?['name']?.toString() ?? 'Customer',
                  subtitle:
                      job['complaint']?.toString() ??
                      job['service_type']?['name']?.toString() ??
                      'Service visit',
                  meta: [
                    job['job_no']?.toString(),
                    if (scheduled != null)
                      DateFormat('hh:mm a').format(scheduled),
                  ].whereType<String>().join(' · '),
                  pill: job['status_label']?.toString(),
                  onTap: () => openPage(
                    ServiceJobDetailScreen(serviceJobId: job['id'] as int),
                  ),
                );
              }),
            SectionLabel(
              'Complaint visits',
              action: 'View all',
              onAction: () =>
                  widget.onNavigate(AppPage.work, tab: WorkTab.complaints),
            ),
            if (visits.isEmpty)
              const _EmptyCard(
                icon: LucideIcons.clipboardCheck,
                message: 'No open complaint visit assigned to you.',
              )
            else
              ...visits.take(3).map((raw) {
                final visit = Map<String, dynamic>.from(raw);
                final complaint = Map<String, dynamic>.from(
                  visit['complaint'] ?? {},
                );
                final appointment = DateTime.tryParse(
                  visit['appointment_at']?.toString() ?? '',
                )?.toLocal();
                return _WorkRow(
                  icon: LucideIcons.clipboardList,
                  tone: Tone.purple,
                  title: complaint['customer_name']?.toString() ?? 'Customer',
                  subtitle:
                      complaint['nature_of_complaint']?.toString() ??
                      'Complaint visit',
                  meta: [
                    '${complaint['complaint_no'] ?? ''} ${visit['label'] ?? ''}'
                        .trim(),
                    if (appointment != null)
                      DateFormat('d MMM, hh:mm a').format(appointment),
                  ].where((value) => value.isNotEmpty).join(' · '),
                  pill: visit['status_label']?.toString(),
                  onTap: () => openPage(
                    ComplaintVisitScreen(visitId: visit['id'] as int),
                  ),
                );
              }),
            SectionLabel(
              'Priority workflow',
              action: 'View all',
              onAction: () =>
                  widget.onNavigate(AppPage.work, tab: WorkTab.workflow),
            ),
            if (workflow.isEmpty)
              const _EmptyCard(
                icon: LucideIcons.partyPopper,
                message: 'No pending workflow. You are all caught up.',
              )
            else
              ...workflow.take(2).map((raw) {
                final task = Map<String, dynamic>.from(raw);
                final due = DateTime.tryParse(
                  task['due_at']?.toString() ?? '',
                )?.toLocal();
                final overdue = due != null && due.isBefore(DateTime.now());
                return _WorkRow(
                  icon: LucideIcons.gitBranch,
                  tone: overdue ? Tone.danger : Tone.warning,
                  title: task['title']?.toString() ?? 'Untitled task',
                  subtitle:
                      task['customer']?['name']?.toString() ??
                      'Internal workflow',
                  meta: [
                    task['task_no']?.toString(),
                    if (due != null)
                      'Due ${DateFormat('d MMM, hh:mm a').format(due)}',
                  ].whereType<String>().join(' · '),
                  pill: overdue
                      ? 'Overdue'
                      : (task['priority']?.toString() ?? 'normal').replaceAll(
                          '_',
                          ' ',
                        ),
                  pillTone: overdue ? Tone.danger : Tone.warning,
                  onTap: () => showTaskDetails(
                    context,
                    task['id'] as int,
                    onUpdated: load,
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}

class _WelcomePanel extends StatelessWidget {
  const _WelcomePanel({
    required this.greeting,
    required this.name,
    required this.designation,
    required this.attendance,
    required this.loading,
    required this.onAttendance,
  });

  final String greeting;
  final String name;
  final String? designation;
  final Map<String, dynamic>? attendance;
  final bool loading;
  final VoidCallback onAttendance;

  static String _time(dynamic value) {
    final date = DateTime.tryParse(value?.toString() ?? '');
    return date == null ? '—' : DateFormat('hh:mm a').format(date.toLocal());
  }

  @override
  Widget build(BuildContext context) {
    final checkedIn = attendance != null;
    final checkedOut = attendance?['checked_out_at'] != null;
    final statusTitle = !checkedIn
        ? 'Not checked in yet'
        : checkedOut
        ? 'Shift completed'
        : 'You are on duty';
    final statusText = !checkedIn
        ? 'Mark attendance before starting field work'
        : checkedOut
        ? '${_time(attendance?['checked_in_at'])} – ${_time(attendance?['checked_out_at'])}'
        : 'Checked in at ${_time(attendance?['checked_in_at'])}';
    final statusColor = !checkedIn
        ? const Color(0xFFFBBF24)
        : checkedOut
        ? AppColors.navyText
        : const Color(0xFF4ADE80);

    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.navy, AppColors.navy2, Color(0xFF1F3A6B)],
        ),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
            child: Row(
              children: [
                AppAvatar(name: name, size: 46, dark: true),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$greeting, ${name.split(' ').first}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        '${DateFormat('EEEE, d MMMM').format(DateTime.now())}${designation == null ? '' : ' · $designation'}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: AppColors.navyText,
                          fontSize: 12.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            margin: const EdgeInsets.fromLTRB(10, 0, 10, 10),
            padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .07),
              borderRadius: BorderRadius.circular(AppRadius.md),
              border: Border.all(color: Colors.white.withValues(alpha: .08)),
            ),
            child: loading
                ? const LinearProgressIndicator(
                    color: Colors.white,
                    backgroundColor: Colors.white24,
                  )
                : Row(
                    children: [
                      Container(
                        width: 10,
                        height: 10,
                        decoration: BoxDecoration(
                          color: statusColor,
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            T(
                              statusTitle,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                                fontSize: 14,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              statusText,
                              style: const TextStyle(
                                color: AppColors.navyText,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (!checkedOut)
                        FilledButton.icon(
                          onPressed: onAttendance,
                          style: FilledButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: AppColors.navy,
                            minimumSize: const Size(0, 38),
                            padding: const EdgeInsets.symmetric(horizontal: 14),
                          ),
                          icon: Icon(
                            checkedIn ? LucideIcons.logOut : LucideIcons.camera,
                            size: 16,
                          ),
                          label: T(checkedIn ? 'Check out' : 'Check in'),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({
    required this.icon,
    required this.label,
    required this.tone,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Tone tone;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Card(
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 4),
            child: Column(
              children: [
                IconTile(icon, tone: tone, size: 40),
                const SizedBox(height: 8),
                T(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ink,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

class _WorkRow extends StatelessWidget {
  const _WorkRow({
    required this.icon,
    required this.tone,
    required this.title,
    required this.subtitle,
    required this.meta,
    required this.onTap,
    this.pill,
    this.pillTone,
  });

  final IconData icon;
  final Tone tone;
  final String title;
  final String subtitle;
  final String meta;
  final String? pill;
  final Tone? pillTone;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Card(
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              IconTile(icon, tone: tone, size: 42),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                              color: AppColors.heading,
                            ),
                          ),
                        ),
                        if (pill != null) ...[
                          const SizedBox(width: 8),
                          Pill(pill!, tone: pillTone ?? tone),
                        ],
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.ink,
                        fontSize: 12.5,
                      ),
                    ),
                    if (meta.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        meta,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 11.5,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 4),
              const Icon(
                LucideIcons.chevronRight,
                size: 18,
                color: AppColors.subtle,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Card(
    color: AppColors.surface2,
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
      child: Row(
        children: [
          Icon(icon, size: 20, color: AppColors.subtle),
          const SizedBox(width: 12),
          Expanded(
            child: T(
              message,
              style: const TextStyle(color: AppColors.muted, fontSize: 13),
            ),
          ),
        ],
      ),
    ),
  );
}
