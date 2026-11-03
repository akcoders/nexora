import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:image_picker/image_picker.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import '../auth/auth_controller.dart';
import '../complaints/complaint_visit_list.dart';
import '../complaints/complaint_visit_screen.dart';
import '../service_jobs/service_job_detail_screen.dart';

/// Tabs on the Work page, also used by the side menu.
class WorkTab {
  static const jobs = 0;
  static const complaints = 1;
  static const workflow = 2;
  static const completed = 3;
  static const count = 4;
}

/// Opens the workflow detail sheet for [taskId].
Future<void> showTaskDetails(
  BuildContext context,
  int taskId, {
  required VoidCallback onUpdated,
}) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (_) => FractionallySizedBox(
    heightFactor: .94,
    child: _TaskDetails(taskId: taskId, onUpdated: onUpdated),
  ),
);

String _updatesLabel(int count) =>
    '$count ${count == 1 ? 'update' : 'updates'}';

String _dueText(dynamic raw, {String prefix = 'Due '}) {
  final date = DateTime.tryParse(raw?.toString() ?? '');
  return date == null
      ? 'No due date'
      : '$prefix${DateFormat('d MMM, hh:mm a').format(date.toLocal())}';
}

class TasksScreen extends StatefulWidget {
  const TasksScreen({
    super.key,
    required this.initialTab,
    required this.onOpenMenu,
  });

  final int initialTab;
  final VoidCallback onOpenMenu;

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen>
    with SingleTickerProviderStateMixin {
  static const closedJobStatuses = {
    'completed',
    'cancelled',
    'customer_declined',
  };

  late final TabController tabs;
  int reload = 0;
  bool workflowOverdue = false;
  String completedFilter = 'all';

  @override
  void initState() {
    super.initState();
    tabs = TabController(
      length: WorkTab.count,
      initialIndex: widget.initialTab,
      vsync: this,
    );
  }

  @override
  void didUpdateWidget(covariant TasksScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialTab != widget.initialTab &&
        tabs.index != widget.initialTab) {
      tabs.animateTo(widget.initialTab);
    }
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  Future<List<dynamic>> loadTasks({String? status, String? scope}) async {
    final response = await context.read<ApiClient>().dio.get(
      'tasks',
      queryParameters: {'type': 'workflow', 'status': ?status, 'scope': ?scope},
    );
    return List<dynamic>.from(response.data['data'] ?? []);
  }

  Future<List<dynamic>> loadServiceJobs({bool completed = false}) async {
    final response = await context.read<ApiClient>().dio.get('service-jobs');
    final jobs = List<dynamic>.from(response.data['data'] ?? []);
    return jobs
        .where((job) => completed == closedJobStatuses.contains(job['status']))
        .toList();
  }

  /// Completed work from all three sources; a source that fails is skipped.
  Future<List<dynamic>> loadClosedWork() async {
    Future<List<dynamic>> safe(Future<List<dynamic>> Function() request) async {
      try {
        return await request();
      } catch (_) {
        return const [];
      }
    }

    final results = await Future.wait([
      safe(() => loadServiceJobs(completed: true)),
      safe(() => fetchComplaintVisits(context, scope: 'completed')),
      safe(() => loadTasks(status: 'closed')),
    ]);
    return [
      for (final item in results[0])
        {...Map<String, dynamic>.from(item), '_kind': 'service_job'},
      for (final item in results[1])
        {...Map<String, dynamic>.from(item), '_kind': 'complaint'},
      for (final item in results[2])
        {...Map<String, dynamic>.from(item), '_kind': 'workflow'},
    ];
  }

  void refresh() => setState(() => reload++);

  Widget _tab(IconData icon, String label) => Tab(
    height: 46,
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [Icon(icon, size: 16), const SizedBox(width: 7), T(label)],
    ),
  );

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      leading: MenuButton(onPressed: widget.onOpenMenu),
      title: const T('My work'),
      actions: [
        IconButton(
          tooltip: 'Refresh',
          onPressed: refresh,
          icon: const Icon(LucideIcons.refreshCw),
        ),
        const SizedBox(width: 4),
      ],
      bottom: TabBar(
        controller: tabs,
        isScrollable: true,
        tabs: [
          _tab(LucideIcons.wrench, 'Service jobs'),
          _tab(LucideIcons.clipboardList, 'Complaints'),
          _tab(LucideIcons.gitBranch, 'Workflow'),
          _tab(LucideIcons.circleCheckBig, 'Completed'),
        ],
      ),
    ),
    body: TabBarView(
      controller: tabs,
      children: [
        _ServiceJobList(
          key: ValueKey('jobs$reload'),
          future: loadServiceJobs(),
          onRefresh: refresh,
        ),
        ComplaintVisitList(key: ValueKey('complaints$reload')),
        Column(
          children: [
            _FilterBar(
              options: const {'pending': 'Pending', 'overdue': 'Overdue'},
              selected: workflowOverdue ? 'overdue' : 'pending',
              onSelected: (value) =>
                  setState(() => workflowOverdue = value == 'overdue'),
            ),
            Expanded(
              child: _TaskList(
                key: ValueKey('workflow$reload$workflowOverdue'),
                future: loadTasks(
                  scope: 'open',
                  status: workflowOverdue ? 'overdue' : null,
                ),
                title: workflowOverdue ? 'Overdue workflow' : 'My workflow',
                subtitle: workflowOverdue
                    ? 'Past the original due date'
                    : 'Pending work assigned to you',
                onRefresh: refresh,
              ),
            ),
          ],
        ),
        Column(
          children: [
            _FilterBar(
              options: const {
                'all': 'All',
                'service_job': 'Service jobs',
                'complaint': 'Complaints',
                'workflow': 'Workflow',
              },
              selected: completedFilter,
              onSelected: (value) => setState(() => completedFilter = value),
            ),
            Expanded(
              child: _ClosedWorkList(
                key: ValueKey('closed$reload'),
                future: loadClosedWork(),
                filter: completedFilter,
                onRefresh: refresh,
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.options,
    required this.selected,
    required this.onSelected,
  });

  final Map<String, String> options;
  final String selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    color: Colors.white,
    padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
    child: SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final entry in options.entries) ...[
            ChoiceChip(
              label: T(entry.value),
              selected: selected == entry.key,
              labelStyle: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: selected == entry.key
                    ? AppColors.primary
                    : AppColors.ink,
              ),
              side: BorderSide(
                color: selected == entry.key
                    ? AppColors.primary
                    : AppColors.border,
              ),
              onSelected: (_) => onSelected(entry.key),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    ),
  );
}

class _TaskList extends StatelessWidget {
  const _TaskList({
    super.key,
    required this.future,
    required this.title,
    required this.subtitle,
    required this.onRefresh,
  });

  final Future<List<dynamic>> future;
  final String title;
  final String subtitle;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => FutureBuilder<List<dynamic>>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState == ConnectionState.waiting) {
        return const LoadingView();
      }
      if (snapshot.hasError) {
        return RefreshIndicator(
          onRefresh: () async => onRefresh(),
          child: EmptyState(
            icon: LucideIcons.cloudOff,
            title: 'Unable to load workflow',
            message: apiErrorMessage(
              snapshot.error!,
              'Pull down or tap retry to try again.',
            ),
            actionLabel: 'Retry',
            onAction: onRefresh,
          ),
        );
      }
      final tasks = snapshot.data ?? [];
      if (tasks.isEmpty) {
        return RefreshIndicator(
          onRefresh: () async => onRefresh(),
          child: const EmptyState(
            icon: LucideIcons.circleCheckBig,
            title: 'Nothing pending',
            message:
                'Workflow assigned to you or shared with you appears here.',
          ),
        );
      }
      final urgent = tasks
          .where(
            (item) =>
                item['priority'] == 'high' || item['priority'] == 'very_high',
          )
          .length;
      return RefreshIndicator(
        onRefresh: () async => onRefresh(),
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
          itemCount: tasks.length + 1,
          separatorBuilder: (_, index) =>
              SizedBox(height: index == 0 ? 12 : 10),
          itemBuilder: (context, index) {
            if (index == 0) {
              return ListSummary(
                title: title,
                subtitle: subtitle,
                count: tasks.length,
                caption: urgent == 0 ? null : '$urgent high priority',
              );
            }
            return _TaskCard(
              task: Map<String, dynamic>.from(tasks[index - 1]),
              onUpdated: onRefresh,
            );
          },
        ),
      );
    },
  );
}

class _ServiceJobList extends StatelessWidget {
  const _ServiceJobList({
    super.key,
    required this.future,
    required this.onRefresh,
  });

  final Future<List<dynamic>> future;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => FutureBuilder<List<dynamic>>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState == ConnectionState.waiting) {
        return const LoadingView();
      }
      final jobs = snapshot.data ?? [];
      if (snapshot.hasError || jobs.isEmpty) {
        return RefreshIndicator(
          onRefresh: () async => onRefresh(),
          child: EmptyState(
            icon: snapshot.hasError ? LucideIcons.cloudOff : LucideIcons.wrench,
            title: snapshot.hasError
                ? 'Unable to load service jobs'
                : 'No active service jobs',
            message: snapshot.hasError
                ? apiErrorMessage(
                    snapshot.error!,
                    'Check your connection and try again.',
                  )
                : 'Assigned service visits appear here until they are completed.',
            actionLabel: snapshot.hasError ? 'Retry' : null,
            onAction: onRefresh,
          ),
        );
      }
      final today = jobs.where((job) {
        final date = DateTime.tryParse(
          job['scheduled_at']?.toString() ?? '',
        )?.toLocal();
        return date != null && DateUtils.isSameDay(date, DateTime.now());
      }).length;
      return RefreshIndicator(
        onRefresh: () async => onRefresh(),
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
          itemCount: jobs.length + 1,
          separatorBuilder: (_, index) =>
              SizedBox(height: index == 0 ? 12 : 10),
          itemBuilder: (context, index) {
            if (index == 0) {
              return ListSummary(
                title: 'Service jobs',
                subtitle: today == 0
                    ? 'Open HVAC jobs assigned to you'
                    : '$today scheduled for today',
                count: jobs.length,
              );
            }
            return ServiceJobCard(
              job: Map<String, dynamic>.from(jobs[index - 1]),
              onRefresh: onRefresh,
            );
          },
        ),
      );
    },
  );
}

class _ClosedWorkList extends StatelessWidget {
  const _ClosedWorkList({
    super.key,
    required this.future,
    required this.filter,
    required this.onRefresh,
  });

  final Future<List<dynamic>> future;
  final String filter;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => FutureBuilder<List<dynamic>>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState == ConnectionState.waiting) {
        return const LoadingView();
      }
      final items = (snapshot.data ?? [])
          .where((item) => filter == 'all' || item['_kind'] == filter)
          .toList();
      if (items.isEmpty) {
        return RefreshIndicator(
          onRefresh: () async => onRefresh(),
          child: const EmptyState(
            icon: LucideIcons.archive,
            title: 'No completed work yet',
            message:
                'Completed service jobs, complaint visits and workflow appear here.',
          ),
        );
      }
      return RefreshIndicator(
        onRefresh: () async => onRefresh(),
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
          itemCount: items.length,
          separatorBuilder: (_, _) => const SizedBox(height: 10),
          itemBuilder: (context, index) {
            final item = Map<String, dynamic>.from(items[index]);
            return switch (item['_kind']) {
              'service_job' => ServiceJobCard(job: item, onRefresh: onRefresh),
              'complaint' => ComplaintVisitCard(
                visit: item,
                onTap: () async {
                  await Navigator.push<void>(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          ComplaintVisitScreen(visitId: item['id'] as int),
                    ),
                  );
                  onRefresh();
                },
              ),
              _ => _TaskCard(task: item, onUpdated: onRefresh),
            };
          },
        ),
      );
    },
  );
}

Tone serviceJobTone(String status) {
  if (const ['completed', 'paid', 'customer_approved'].contains(status)) {
    return Tone.success;
  }
  if (const ['cancelled', 'customer_declined'].contains(status)) {
    return Tone.danger;
  }
  if (const [
    'on_the_way',
    'arrived',
    'inspection_in_progress',
    'service_in_progress',
  ].contains(status)) {
    return Tone.primary;
  }
  return Tone.warning;
}

class ServiceJobCard extends StatelessWidget {
  const ServiceJobCard({super.key, required this.job, required this.onRefresh});

  final Map<String, dynamic> job;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final status = job['status']?.toString() ?? 'assigned';
    final priority = job['priority']?.toString() ?? 'normal';
    final scheduledAt = DateTime.tryParse(
      job['scheduled_at']?.toString() ?? '',
    )?.toLocal();
    final equipment = job['equipment'] is Map
        ? Map<String, dynamic>.from(job['equipment'])
        : null;
    final urgent = priority == 'emergency' || priority == 'very_high';
    return Card(
      child: InkWell(
        onTap: () async {
          await Navigator.push<void>(
            context,
            MaterialPageRoute(
              builder: (_) =>
                  ServiceJobDetailScreen(serviceJobId: job['id'] as int),
            ),
          );
          onRefresh();
        },
        child: Container(
          decoration: BoxDecoration(
            border: Border(
              left: BorderSide(
                color: urgent ? AppColors.danger : serviceJobTone(status).color,
                width: 3,
              ),
            ),
          ),
          padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        Pill(
                          job['status_label']?.toString() ?? status,
                          tone: serviceJobTone(status),
                        ),
                        if (priority != 'normal')
                          Pill(
                            priority.replaceAll('_', ' '),
                            tone: urgent ? Tone.danger : Tone.warning,
                          ),
                      ],
                    ),
                  ),
                  Text(
                    job['job_no']?.toString() ?? '',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                job['customer']?['name']?.toString() ?? 'Customer',
                style: const TextStyle(
                  fontSize: 15.5,
                  fontWeight: FontWeight.w700,
                  color: AppColors.heading,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                job['complaint']?.toString() ??
                    job['service_type']?['name']?.toString() ??
                    'Service visit',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.ink, fontSize: 13),
              ),
              if (equipment != null) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(
                      LucideIcons.airVent,
                      size: 14,
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
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 10),
                child: Divider(),
              ),
              Row(
                children: [
                  const Icon(
                    LucideIcons.calendarClock,
                    size: 15,
                    color: AppColors.muted,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      scheduledAt == null
                          ? 'Schedule not set'
                          : DateFormat(
                              'EEE, d MMM · hh:mm a',
                            ).format(scheduledAt),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.muted,
                      ),
                    ),
                  ),
                  Text(
                    job['primary_action']?['label']?.toString() ?? 'Open',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const Icon(
                    LucideIcons.chevronRight,
                    size: 16,
                    color: AppColors.primary,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({required this.task, required this.onUpdated});

  final Map<String, dynamic> task;
  final VoidCallback onUpdated;

  @override
  Widget build(BuildContext context) {
    final priority = task['priority']?.toString() ?? 'normal';
    final priorityTone = priority == 'very_high'
        ? Tone.danger
        : priority == 'high'
        ? Tone.warning
        : Tone.info;
    final isClosed = task['status']?.toString() == 'closed';
    final due = DateTime.tryParse(task['due_at']?.toString() ?? '');
    final overdue = !isClosed && due != null && due.isBefore(DateTime.now());
    final isSubtask = task['parent_task_id'] != null;
    final observers = List<dynamic>.from(
      task['observers'] ?? [],
    ).map((member) => member['user']).whereType<Map>().toList();
    final statusTone = isClosed
        ? Tone.success
        : (overdue ? Tone.danger : Tone.warning);
    return Card(
      child: InkWell(
        onTap: () =>
            showTaskDetails(context, task['id'] as int, onUpdated: onUpdated),
        child: Container(
          decoration: BoxDecoration(
            color: isSubtask ? const Color(0xFFFBF9FF) : null,
            border: Border(left: BorderSide(color: statusTone.color, width: 3)),
          ),
          padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        Pill(
                          isSubtask ? 'Subtask' : 'Workflow',
                          tone: isSubtask ? Tone.purple : Tone.primary,
                        ),
                        Pill(priority.replaceAll('_', ' '), tone: priorityTone),
                        Pill(
                          isClosed
                              ? 'Completed'
                              : (overdue ? 'Overdue' : 'Pending'),
                          tone: statusTone,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    task['task_no']?.toString() ?? '',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              if (task['parent'] != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(
                    '↳ ${task['parent']['task_no']}',
                    style: const TextStyle(
                      color: AppColors.purple,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                    ),
                  ),
                ),
              Text(
                task['title']?.toString() ?? 'Untitled task',
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: AppColors.heading,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                task['customer']?['name']?.toString() ?? 'Internal workflow',
                style: const TextStyle(color: AppColors.ink, fontSize: 13),
              ),
              if ((task['subtasks_count'] as num? ?? 0) > 0) ...[
                const SizedBox(height: 4),
                Text(
                  '${task['completed_subtasks_count']}/${task['subtasks_count']} subtasks completed',
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
              ],
              if (task['latest_action'] != null) ...[
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.surface2,
                    border: Border.all(color: AppColors.border),
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        LucideIcons.activity,
                        size: 14,
                        color: AppColors.primary,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          task['latest_action']?['remark']?.toString() ??
                              'Workflow updated',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: AppColors.muted,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              if (observers.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Row(
                    children: [
                      const T(
                        'Observers',
                        style: TextStyle(color: AppColors.muted, fontSize: 12),
                      ),
                      const SizedBox(width: 8),
                      Expanded(child: _ObserverAvatars(people: observers)),
                    ],
                  ),
                ),
              if (task['next_follow_up_at'] != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    'Next follow-up: ${_dueText(task['next_follow_up_at'], prefix: '')}',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 10),
                child: Divider(),
              ),
              Row(
                children: [
                  Icon(
                    LucideIcons.clock3,
                    size: 15,
                    color: overdue ? AppColors.danger : AppColors.muted,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      _dueText(task['due_at']),
                      style: TextStyle(
                        color: overdue ? AppColors.danger : AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  Text(
                    _updatesLabel(
                      (task['actions_count'] as num?)?.toInt() ?? 0,
                    ),
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const Icon(
                    LucideIcons.chevronRight,
                    size: 16,
                    color: AppColors.primary,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TaskDetails extends StatefulWidget {
  const _TaskDetails({required this.taskId, required this.onUpdated});

  final int taskId;
  final VoidCallback onUpdated;

  @override
  State<_TaskDetails> createState() => _TaskDetailsState();
}

class _TaskDetailsState extends State<_TaskDetails> {
  final remark = TextEditingController();
  final delayReason = TextEditingController();
  final List<XFile> attachments = [];
  Map<String, dynamic>? task;
  List<dynamic> assignees = [];
  String updateStatus = 'pending';
  bool reassign = false;
  DateTime? nextFollowUpAt;
  int? assignedTo;
  bool loading = true;
  bool submitting = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    remark.dispose();
    delayReason.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final response = await context.read<ApiClient>().dio.get(
        'tasks/${widget.taskId}',
      );
      task = Map<String, dynamic>.from(response.data['task']);
      assignees = List<dynamic>.from(response.data['assignees'] ?? []);
      updateStatus = task?['status'] == 'closed' ? 'closed' : 'pending';
      nextFollowUpAt = DateTime.tryParse(
        task?['next_follow_up_at']?.toString() ?? '',
      )?.toLocal();
      reassign = false;
      delayReason.clear();
      assignedTo = null;
      error = null;
    } on DioException catch (exception) {
      error = apiErrorMessage(exception, 'Unable to open this task.');
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> pickAttachments(ImageSource source) async {
    try {
      final picker = ImagePicker();
      final selected = source == ImageSource.camera
          ? [
              await picker.pickImage(source: source, imageQuality: 85),
            ].whereType<XFile>().toList()
          : await picker.pickMultiImage(imageQuality: 85);
      if (!mounted) return;
      if (attachments.length + selected.length > 5) {
        setState(() => error = 'You can attach up to 5 photos.');
        return;
      }
      setState(() {
        attachments.addAll(selected);
        error = null;
      });
    } catch (_) {
      if (mounted) {
        setState(() => error = 'Unable to select a photo. Please try again.');
      }
    }
  }

  Future<void> openRelated(int id) async {
    await showTaskDetails(context, id, onUpdated: widget.onUpdated);
    if (mounted) await load();
  }

  Future<void> createSubtask() async {
    String title = '';
    String description = '';
    int? owner;
    bool evidenceRequired = false;
    final inheritedObservers = List<dynamic>.from(
      task?['observers'] ?? [],
    ).map((member) => member['user']).whereType<Map>().toList();
    String? validation;
    bool saving = false;
    final created = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const T('Create subtask'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Linked to ${task?['task_no']}. This workflow will move to the subtask owner.',
                  style: const TextStyle(color: AppColors.muted, fontSize: 13),
                ),
                const SizedBox(height: 14),
                TextField(
                  onChanged: (value) => title = value,
                  maxLength: 255,
                  decoration: InputDecoration(labelText: context.tr('Title *')),
                ),
                const SizedBox(height: 8),
                TextField(
                  onChanged: (value) => description = value,
                  maxLines: 3,
                  maxLength: 5000,
                  decoration: InputDecoration(
                    labelText: context.tr('Description'),
                  ),
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<int>(
                  decoration: InputDecoration(
                    labelText: context.tr('Responsible Person *'),
                  ),
                  isExpanded: true,
                  items: assignees
                      .map(
                        (item) => DropdownMenuItem<int>(
                          value: item['id'] as int,
                          child: Text(
                            item['name']?.toString() ?? '',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: saving
                      ? null
                      : (value) => update(() => owner = value),
                ),
                const SizedBox(height: 14),
                const T(
                  'Observers inherited from parent',
                  style: TextStyle(
                    color: AppColors.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                if (inheritedObservers.isNotEmpty)
                  _ObserverAvatars(people: inheritedObservers)
                else
                  const T(
                    'No observers on the parent workflow.',
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const T(
                    'Attachment required when complete',
                    style: TextStyle(fontSize: 13.5),
                  ),
                  value: evidenceRequired,
                  onChanged: saving
                      ? null
                      : (value) => update(() => evidenceRequired = value),
                ),
                if (validation != null) NoticeBox(validation!),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving
                  ? null
                  : () => Navigator.pop(dialogContext, false),
              child: const T('Cancel'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (title.trim().isEmpty || owner == null) {
                        update(
                          () =>
                              validation = 'Enter a title and select an owner.',
                        );
                        return;
                      }
                      update(() {
                        saving = true;
                        validation = null;
                      });
                      try {
                        await this.context.read<ApiClient>().dio.post(
                          'tasks/${widget.taskId}/subtasks',
                          data: {
                            'title': title.trim(),
                            'description': description.trim(),
                            'assigned_to': owner,
                            'priority': 'normal',
                            'attachment_required_on_completion':
                                evidenceRequired,
                          },
                        );
                        if (dialogContext.mounted) {
                          Navigator.pop(dialogContext, true);
                        }
                      } on DioException catch (exception) {
                        if (dialogContext.mounted) {
                          update(() {
                            saving = false;
                            validation = apiErrorMessage(
                              exception,
                              'Unable to create subtask.',
                            );
                          });
                        }
                      }
                    },
              child: T(saving ? 'Creating…' : 'Create & assign'),
            ),
          ],
        ),
      ),
    );
    if (created == true) {
      widget.onUpdated();
      if (mounted) await load();
    }
  }

  Future<void> pickFollowUpDate() async {
    final today = DateUtils.dateOnly(DateTime.now());
    final date = await showDatePicker(
      context: context,
      initialDate: nextFollowUpAt == null || nextFollowUpAt!.isBefore(today)
          ? today
          : nextFollowUpAt!,
      firstDate: today,
      lastDate: DateTime(2100),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(nextFollowUpAt ?? DateTime.now()),
    );
    if (time == null || !mounted) return;
    setState(
      () => nextFollowUpAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  bool get followUpRevisesDue {
    final due = DateTime.tryParse(task?['due_at']?.toString() ?? '');
    return nextFollowUpAt != null &&
        due != null &&
        nextFollowUpAt!.isAfter(due);
  }

  bool get followUpChanged {
    final previous = DateTime.tryParse(
      task?['next_follow_up_at']?.toString() ?? '',
    );
    return previous == null
        ? nextFollowUpAt != null
        : nextFollowUpAt == null || !nextFollowUpAt!.isAtSameMomentAs(previous);
  }

  Future<void> submit() async {
    final completing = updateStatus == 'closed';
    final assigning = reassign && !completing;
    if (followUpRevisesDue &&
        followUpChanged &&
        delayReason.text.trim().isEmpty) {
      setState(() => error = 'Please specify the reason for the delay.');
      return;
    }
    if (remark.text.trim().isEmpty) {
      setState(() => error = 'Please enter a remark.');
      return;
    }
    if (assigning && assignedTo == null) {
      setState(() => error = 'Select the Responsible Person.');
      return;
    }
    if (completing &&
        task?['attachment_required_on_completion'] == true &&
        attachments.isEmpty) {
      setState(
        () => error =
            'Attach at least one photo before completing this workflow.',
      );
      return;
    }
    if (completing || assigning) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          icon: Icon(
            completing ? LucideIcons.circleCheckBig : LucideIcons.forward,
            color: completing ? AppColors.success : AppColors.primary,
          ),
          title: T(completing ? 'Close this workflow?' : 'Assign next?'),
          content: T(
            completing
                ? 'This workflow will move out of your pending queue.'
                : 'This workflow will stay pending and move to the selected assignee.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const T('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: T(completing ? 'Close workflow' : 'Assign'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await context.read<ApiClient>().dio.post(
        'tasks/${widget.taskId}/action',
        data: FormData.fromMap({
          'action': 'update',
          'status': updateStatus,
          'reassign': assigning ? 1 : 0,
          'next_follow_up_at': nextFollowUpAt?.toIso8601String(),
          if (followUpRevisesDue && followUpChanged)
            'delay_reason': delayReason.text.trim(),
          'remark': remark.text.trim(),
          if (assigning) 'assigned_to': assignedTo,
          if (attachments.isNotEmpty)
            'attachments[]': [
              for (final file in attachments)
                await MultipartFile.fromFile(file.path, filename: file.name),
            ],
        }),
      );
      widget.onUpdated();
      if (mounted) {
        showAppSnack(
          context,
          completing ? 'Workflow closed.' : 'Update saved.',
          tone: Tone.success,
        );
        Navigator.pop(context);
      }
    } on DioException catch (exception) {
      if (mounted) {
        setState(
          () =>
              error = apiErrorMessage(exception, 'Unable to update this task.'),
        );
      }
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const LoadingView();
    if (task == null) {
      return EmptyState(
        icon: LucideIcons.cloudOff,
        title: 'Unable to open task',
        message: error,
      );
    }

    final currentUserId = context.read<AuthController>().user?['id'];
    final closed = task?['status'] == 'closed';
    final canAct = !closed && task?['assigned_to'] == currentUserId;
    final actions = List<dynamic>.from(task?['actions'] ?? []);
    final due = DateTime.tryParse(task?['due_at']?.toString() ?? '');
    final overdue = !closed && due != null && due.isBefore(DateTime.now());
    final isSubtask = task?['parent_task_id'] != null;
    final observers = List<dynamic>.from(
      task?['observers'] ?? [],
    ).map((member) => member['user']).whereType<Map>().toList();
    final subtasks = List<dynamic>.from(task?['subtasks'] ?? []);
    final updatedAt = DateTime.tryParse(task?['updated_at']?.toString() ?? '');
    final keyboardInset = MediaQuery.viewInsetsOf(context).bottom;

    return ListView(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: EdgeInsets.fromLTRB(18, 0, 18, 32 + keyboardInset),
      children: [
        Row(
          children: [
            Pill(
              isSubtask ? 'Subtask' : 'Workflow',
              tone: isSubtask ? Tone.purple : Tone.primary,
            ),
            const SizedBox(width: 6),
            Pill(
              overdue ? 'Overdue' : (closed ? 'Completed' : 'Pending'),
              tone: closed
                  ? Tone.success
                  : (overdue ? Tone.danger : Tone.warning),
            ),
            const Spacer(),
            Text(
              task?['task_no']?.toString() ?? '',
              style: const TextStyle(
                color: AppColors.muted,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Text(
          task?['title']?.toString() ?? '',
          style: const TextStyle(
            fontSize: 19,
            fontWeight: FontWeight.w700,
            color: AppColors.heading,
            height: 1.3,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          task?['description']?.toString() ?? 'No description provided.',
          style: const TextStyle(color: AppColors.muted, height: 1.5),
        ),
        const SizedBox(height: 14),
        StageTracker(
          stages: const ['Created', 'Assigned', 'In workflow', 'Completed'],
          current: 2,
          allDone: closed,
        ),
        const SizedBox(height: 12),
        SectionCard(
          title: 'Details',
          icon: LucideIcons.info,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          child: Column(
            children: [
              InfoRow(
                label: 'Customer',
                value: task?['customer']?['name']?.toString() ?? 'Internal',
              ),
              InfoRow(
                label: 'Responsible Person',
                value: task?['assignee']?['name']?.toString() ?? '—',
              ),
              InfoRow(
                label: 'Original due',
                value: _dueText(task?['due_at'], prefix: ''),
                valueColor: overdue ? AppColors.danger : null,
              ),
              if (task?['next_follow_up_at'] != null)
                InfoRow(
                  label: 'Next follow-up',
                  value: _dueText(task?['next_follow_up_at'], prefix: ''),
                ),
              if (task?['revised_due_at'] != null)
                InfoRow(
                  label: 'Latest revised due',
                  value: _dueText(task?['revised_due_at'], prefix: ''),
                  valueColor: AppColors.warning,
                ),
              InfoRow(
                label: 'Last updated',
                value: updatedAt == null
                    ? '—'
                    : DateFormat('d MMM, hh:mm a').format(updatedAt.toLocal()),
                last: true,
              ),
            ],
          ),
        ),
        if (observers.isNotEmpty) ...[
          const SectionLabel(
            'Observers',
            padding: EdgeInsets.only(top: 16, bottom: 8),
          ),
          _ObserverAvatars(people: observers),
        ],
        if (task?['parent'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: OutlinedButton.icon(
              onPressed: () => openRelated(task!['parent']['id'] as int),
              icon: const Icon(LucideIcons.cornerLeftUp, size: 17),
              label: Text(
                '${context.tr('Parent')}: ${task!['parent']['task_no']}',
              ),
            ),
          ),
        SectionLabel(
          'Subtasks',
          action: canAct && !submitting ? 'Create subtask' : null,
          onAction: createSubtask,
          padding: const EdgeInsets.only(top: 18, bottom: 8),
        ),
        if (subtasks.isEmpty)
          const T(
            'No subtasks.',
            style: TextStyle(color: AppColors.muted, fontSize: 13),
          )
        else
          for (final child in subtasks)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Card(
                color: const Color(0xFFFBF9FF),
                child: ListTile(
                  onTap: () => openRelated(child['id'] as int),
                  leading: Icon(
                    child['status'] == 'closed'
                        ? LucideIcons.circleCheckBig
                        : LucideIcons.gitBranch,
                    color: child['status'] == 'closed'
                        ? AppColors.success
                        : AppColors.purple,
                  ),
                  title: Text(child['title']?.toString() ?? ''),
                  subtitle: Text(
                    '${child['task_no']} · ${child['assignee']?['name'] ?? ''} · ${child['status'] == 'closed' ? 'Completed' : 'Pending'}',
                  ),
                  trailing: const Icon(LucideIcons.chevronRight, size: 18),
                ),
              ),
            ),
        if (canAct) ...[
          const SizedBox(height: 14),
          SectionCard(
            title: 'Update workflow',
            icon: LucideIcons.pencilLine,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  controller: remark,
                  minLines: 3,
                  maxLines: 6,
                  scrollPadding: EdgeInsets.only(bottom: keyboardInset + 120),
                  textCapitalization: TextCapitalization.sentences,
                  decoration: InputDecoration(
                    labelText: context.tr('Remark *'),
                    hintText: context.tr(
                      'Describe work completed or next steps',
                    ),
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(
                      value: 'pending',
                      icon: Icon(LucideIcons.hourglass, size: 16),
                      label: T('Pending'),
                    ),
                    ButtonSegment(
                      value: 'closed',
                      icon: Icon(LucideIcons.circleCheck, size: 16),
                      label: T('Completed'),
                    ),
                  ],
                  selected: {updateStatus},
                  onSelectionChanged: submitting
                      ? null
                      : (selection) => setState(() {
                          updateStatus = selection.first;
                          if (updateStatus == 'closed') reassign = false;
                        }),
                ),
                if (updateStatus != 'closed')
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const T(
                      'Assign to someone else',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    value: reassign,
                    onChanged: submitting
                        ? null
                        : (value) => setState(() => reassign = value),
                  ),
                if (reassign && updateStatus != 'closed') ...[
                  DropdownButtonFormField<int>(
                    initialValue: assignedTo,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: context.tr('Responsible Person *'),
                    ),
                    items: assignees
                        .where((item) => item['id'] != task?['assigned_to'])
                        .map(
                          (item) => DropdownMenuItem<int>(
                            value: item['id'] as int,
                            child: Text(
                              item['name']?.toString() ?? 'User',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => setState(() => assignedTo = value),
                  ),
                  const SizedBox(height: 8),
                ],
                const SizedBox(height: 8),
                InkWell(
                  onTap: submitting ? null : pickFollowUpDate,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  child: InputDecorator(
                    decoration: InputDecoration(
                      labelText: context.tr('Next follow-up date & time'),
                      helperText: context.tr(
                        'The original due date stays fixed.',
                      ),
                      suffixIcon: nextFollowUpAt == null
                          ? const Icon(LucideIcons.calendarClock, size: 18)
                          : IconButton(
                              tooltip: 'Clear follow-up',
                              onPressed: submitting
                                  ? null
                                  : () => setState(() => nextFollowUpAt = null),
                              icon: const Icon(LucideIcons.x, size: 18),
                            ),
                    ),
                    child: Text(
                      nextFollowUpAt == null
                          ? ''
                          : DateFormat(
                              'd MMM yyyy, hh:mm a',
                            ).format(nextFollowUpAt!),
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                ),
                if (followUpRevisesDue) ...[
                  const SizedBox(height: 10),
                  NoticeBox(
                    'Revised due date: ${DateFormat('d MMM yyyy, hh:mm a').format(nextFollowUpAt!)}',
                    tone: Tone.warning,
                    icon: LucideIcons.calendarClock,
                  ),
                  if (followUpChanged) ...[
                    const SizedBox(height: 10),
                    TextField(
                      controller: delayReason,
                      maxLines: 2,
                      maxLength: 3000,
                      decoration: InputDecoration(
                        labelText: context.tr('Reason for delay *'),
                      ),
                    ),
                  ],
                ],
                const SizedBox(height: 12),
                T(
                  task?['attachment_required_on_completion'] == true
                      ? 'Attachment required to complete'
                      : 'Attachments (optional)',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: submitting
                            ? null
                            : () => pickAttachments(ImageSource.camera),
                        icon: const Icon(LucideIcons.camera, size: 17),
                        label: const T('Take photo'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: submitting
                            ? null
                            : () => pickAttachments(ImageSource.gallery),
                        icon: const Icon(LucideIcons.images, size: 17),
                        label: const T('Choose photos'),
                      ),
                    ),
                  ],
                ),
                for (final file in attachments)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(LucideIcons.paperclip, size: 17),
                    title: Text(file.name, overflow: TextOverflow.ellipsis),
                    trailing: IconButton(
                      onPressed: submitting
                          ? null
                          : () => setState(() => attachments.remove(file)),
                      icon: const Icon(LucideIcons.x, size: 17),
                    ),
                  ),
                if (error != null) ...[
                  const SizedBox(height: 10),
                  NoticeBox(error!),
                ],
                const SizedBox(height: 14),
                BusyButton(
                  label: 'Save update',
                  busyLabel: 'Saving…',
                  icon: updateStatus == 'closed'
                      ? LucideIcons.circleCheck
                      : LucideIcons.save,
                  busy: submitting,
                  onPressed: submit,
                ),
              ],
            ),
          ),
        ] else if (!closed) ...[
          const SizedBox(height: 14),
          const NoticeBox(
            'You can view this workflow as an observer. Only the Responsible Person can take action.',
            tone: Tone.info,
          ),
        ],
        const SectionLabel(
          'Activity',
          padding: EdgeInsets.only(top: 22, bottom: 10),
        ),
        if (actions.isEmpty)
          const T('No activity yet.', style: TextStyle(color: AppColors.muted))
        else
          for (final (index, item) in actions.indexed)
            _ActionTile(
              action: item,
              isSubtask: isSubtask,
              last: index == actions.length - 1,
            ),
      ],
    );
  }
}

class _ObserverAvatars extends StatelessWidget {
  const _ObserverAvatars({required this.people});

  final List<dynamic> people;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 5,
    runSpacing: 6,
    children: [
      for (final person in people)
        Tooltip(
          message: person['name']?.toString() ?? 'Observer',
          child: AppAvatar(name: person['name']?.toString() ?? '?', size: 28),
        ),
    ],
  );
}

class _ActionTile extends StatefulWidget {
  const _ActionTile({
    required this.action,
    this.isSubtask = false,
    this.last = false,
  });

  final dynamic action;
  final bool isSubtask;
  final bool last;

  @override
  State<_ActionTile> createState() => _ActionTileState();
}

class _ActionTileState extends State<_ActionTile> {
  bool expanded = false;

  @override
  Widget build(BuildContext context) {
    final action = widget.action;
    final created = DateTime.tryParse(action['created_at']?.toString() ?? '');
    final remark = action['remark']?.toString() ?? '';
    final subtaskCreated =
        action['action'] == 'created' &&
        (widget.isSubtask || action['related_task_id'] != null);
    final (title, tone) = subtaskCreated
        ? ('Subtask created and assigned', Tone.purple)
        : switch (action['action']) {
            'created' => ('Created', Tone.primary),
            'assign' => ('Assigned', Tone.info),
            'close' => ('Completed', Tone.success),
            'returned' => ('Returned to creator', Tone.warning),
            'update' => ('Progress update', Tone.primary),
            'status' => ('Status changed', Tone.slate),
            'priority' => ('Priority changed', Tone.warning),
            'due_date' => ('Due date changed', Tone.warning),
            _ => ('Update', Tone.slate),
          };
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 20,
            child: Column(
              children: [
                const SizedBox(height: 4),
                Container(
                  width: 11,
                  height: 11,
                  decoration: BoxDecoration(
                    color: tone.soft,
                    shape: BoxShape.circle,
                    border: Border.all(color: tone.color, width: 2),
                  ),
                ),
                if (!widget.last)
                  Expanded(
                    child: Container(width: 1.5, color: AppColors.border),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: T(
                          title,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                            color: AppColors.heading,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        created == null
                            ? ''
                            : DateFormat(
                                'd MMM, h:mm a',
                              ).format(created.toLocal()),
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${action['user']?['name'] ?? 'System'}${action['assignee'] == null ? '' : ' → ${action['assignee']['name']}'}',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  if (action['next_follow_up_at'] != null)
                    Text(
                      'Next follow-up: ${_dueText(action['next_follow_up_at'], prefix: '')}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.primary,
                      ),
                    ),
                  if (action['revised_due_at'] != null)
                    Text(
                      'Revised due: ${_dueText(action['revised_due_at'], prefix: '')} · Reason for delay: ${action['delay_reason'] ?? ''}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.warning,
                      ),
                    ),
                  if (remark.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: AppColors.surface2,
                        border: Border.all(color: AppColors.border),
                        borderRadius: BorderRadius.circular(AppRadius.sm),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            remark,
                            maxLines: expanded ? null : 3,
                            overflow: expanded
                                ? TextOverflow.visible
                                : TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: AppColors.ink,
                              fontSize: 12.5,
                              height: 1.45,
                            ),
                          ),
                          if (remark.length > 90 ||
                              remark.split('\n').length > 2)
                            GestureDetector(
                              onTap: () => setState(() => expanded = !expanded),
                              child: Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: T(
                                  expanded ? 'Show less' : 'Read more',
                                  style: const TextStyle(
                                    color: AppColors.primary,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                  for (final file in List<dynamic>.from(
                    action['attachments'] ?? [],
                  ))
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Row(
                        children: [
                          const Icon(
                            LucideIcons.paperclip,
                            size: 13,
                            color: AppColors.primary,
                          ),
                          const SizedBox(width: 5),
                          Expanded(
                            child: Text(
                              file['name']?.toString() ?? 'Attachment',
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontSize: 12,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
