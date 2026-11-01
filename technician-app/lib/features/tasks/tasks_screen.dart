import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:image_picker/image_picker.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../auth/auth_controller.dart';
import '../service_jobs/service_job_detail_screen.dart';
import '../../core/i18n/t.dart';

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
  late final TabController tabs;
  int reload = 0;
  bool workflowOverdue = false;
  bool complaintsOverdue = false;

  @override
  void initState() {
    super.initState();
    tabs = TabController(
      length: 4,
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

  Future<List<dynamic>> load({
    String? status,
    String? scope,
    String? type,
  }) async {
    final query = <String, dynamic>{};
    if (status != null) query['status'] = status;
    if (scope != null) query['scope'] = scope;
    if (type != null) query['type'] = type;
    final response = await context.read<ApiClient>().dio.get(
      'tasks',
      queryParameters: query,
    );
    return List<dynamic>.from(response.data['data'] ?? []);
  }

  Future<List<dynamic>> loadServiceJobs({bool completed = false}) async {
    final response = await context.read<ApiClient>().dio.get('service-jobs');
    final jobs = List<dynamic>.from(response.data['data'] ?? []);
    const closed = {'completed', 'cancelled', 'customer_declined'};
    return jobs
        .where(
          (job) => completed
              ? closed.contains(job['status'])
              : !closed.contains(job['status']),
        )
        .toList();
  }

  Future<List<dynamic>> loadClosedWork() async {
    final results = await Future.wait([
      load(status: 'closed', type: 'workflow'),
      load(status: 'closed', type: 'ticket'),
      loadServiceJobs(completed: true),
    ]);
    return [
      ...results[0].map(
        (item) => {...Map<String, dynamic>.from(item), '_kind': 'workflow'},
      ),
      ...results[1].map(
        (item) => {...Map<String, dynamic>.from(item), '_kind': 'ticket'},
      ),
      ...results[2].map(
        (item) => {...Map<String, dynamic>.from(item), '_kind': 'service_job'},
      ),
    ];
  }

  Widget taskQueue({required bool complaint}) {
    final overdue = complaint ? complaintsOverdue : workflowOverdue;
    final type = complaint ? 'ticket' : 'workflow';
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
          child: Row(
            children: [
              for (final option in [false, true]) ...[
                ChoiceChip(
                  label: Text(option ? 'Overdue' : 'Pending'),
                  selected: overdue == option,
                  onSelected: (_) => setState(() {
                    if (complaint) {
                      complaintsOverdue = option;
                    } else {
                      workflowOverdue = option;
                    }
                  }),
                ),
                const SizedBox(width: 8),
              ],
            ],
          ),
        ),
        Expanded(
          child: _TaskList(
            key: ValueKey('$type$reload$overdue'),
            future: load(
              scope: 'open',
              type: type,
              status: overdue ? 'overdue' : null,
            ),
            title: complaint ? 'Customer complaints' : 'My workflow',
            subtitle: overdue
                ? 'Past the original due date'
                : 'Pending work assigned to you',
            onRefresh: refresh,
          ),
        ),
      ],
    );
  }

  void refresh() => setState(() => reload++);

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: widget.onOpenMenu,
          icon: const Icon(LucideIcons.menu),
        ),
        title: const T('My tasks'),
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
            Tab(
              icon: Icon(LucideIcons.briefcaseBusiness, size: 17),
              text: 'Jobs',
            ),
            Tab(icon: Icon(LucideIcons.gitBranch, size: 17), text: 'Workflow'),
            Tab(
              icon: Icon(LucideIcons.ticketCheck, size: 17),
              text: 'Complaints',
            ),
            Tab(
              icon: Icon(LucideIcons.circleCheckBig, size: 17),
              text: 'Closed',
            ),
          ],
          labelColor: AppColors.primary,
          indicatorColor: AppColors.primary,
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
          taskQueue(complaint: false),
          taskQueue(complaint: true),
          _ClosedWorkList(
            key: ValueKey('closed$reload'),
            future: loadClosedWork(),
            onRefresh: refresh,
          ),
        ],
      ),
    );
  }
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
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
      future: future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _Empty(
            icon: Icons.cloud_off_outlined,
            title: 'Unable to load tasks',
            subtitle: 'Pull down or tap refresh to try again.',
            onRefresh: onRefresh,
          );
        }
        final tasks = snapshot.data ?? [];
        if (tasks.isEmpty) {
          return _Empty(
            icon: LucideIcons.checkCircle2,
            title: 'Nothing here',
            subtitle: 'Your tasks will appear here.',
            onRefresh: onRefresh,
          );
        }
        return RefreshIndicator(
          onRefresh: () async => onRefresh(),
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(18),
            itemCount: tasks.length + 1,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              if (index == 0) {
                return _QueueHeader(
                  tasks: tasks,
                  title: title,
                  subtitle: subtitle,
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
        return const Center(child: CircularProgressIndicator());
      }
      final jobs = snapshot.data ?? [];
      if (snapshot.hasError || jobs.isEmpty) {
        return _Empty(
          icon: snapshot.hasError
              ? LucideIcons.cloudOff
              : LucideIcons.briefcaseBusiness,
          title: snapshot.hasError
              ? 'Unable to load service jobs'
              : 'No active service jobs',
          subtitle: 'Assigned service visits appear here until completed.',
          onRefresh: onRefresh,
        );
      }
      return RefreshIndicator(
        onRefresh: () async => onRefresh(),
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 28),
          itemCount: jobs.length + 1,
          separatorBuilder: (_, _) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            if (index == 0) {
              return _QueueHeader(
                tasks: jobs,
                title: 'Service jobs',
                subtitle: 'Only assigned HVAC service jobs',
              );
            }
            return _ServiceJobCard(
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
    required this.onRefresh,
  });

  final Future<List<dynamic>> future;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => FutureBuilder<List<dynamic>>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState == ConnectionState.waiting) {
        return const Center(child: CircularProgressIndicator());
      }
      final items = snapshot.data ?? [];
      if (snapshot.hasError || items.isEmpty) {
        return _Empty(
          icon: LucideIcons.circleCheckBig,
          title: 'No closed work',
          subtitle: 'Completed jobs, workflows and tickets appear here.',
          onRefresh: onRefresh,
        );
      }
      return RefreshIndicator(
        onRefresh: () async => onRefresh(),
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 28),
          itemCount: items.length,
          separatorBuilder: (_, _) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            final item = Map<String, dynamic>.from(items[index]);
            if (item['_kind'] == 'service_job') {
              return _ServiceJobCard(job: item, onRefresh: onRefresh);
            }
            return _TaskCard(task: item, onUpdated: onRefresh);
          },
        ),
      );
    },
  );
}

class _ServiceJobCard extends StatelessWidget {
  const _ServiceJobCard({required this.job, required this.onRefresh});

  final Map<String, dynamic> job;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.zero,
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
      child: Padding(
        padding: const EdgeInsets.all(17),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const _Pill(text: 'SERVICE JOB', color: AppColors.success),
                const SizedBox(width: 7),
                _Pill(
                  text: (job['status_label'] ?? job['status'] ?? '')
                      .toString()
                      .toUpperCase(),
                  color: job['status'] == 'completed'
                      ? AppColors.success
                      : AppColors.primary,
                ),
                const Spacer(),
                Text(
                  job['job_no']?.toString() ?? '',
                  style: const TextStyle(color: AppColors.muted, fontSize: 11),
                ),
              ],
            ),
            const SizedBox(height: 13),
            Text(
              job['customer']?['name']?.toString() ?? 'Customer',
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 5),
            Text(
              job['complaint']?.toString() ??
                  job['service_type']?['name']?.toString() ??
                  'Service visit',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: AppColors.muted),
            ),
            const Divider(height: 26),
            Row(
              children: [
                const Icon(
                  LucideIcons.calendarClock,
                  size: 16,
                  color: AppColors.muted,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    _TaskCard._date(job['scheduled_at']),
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.muted,
                    ),
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

class _QueueHeader extends StatelessWidget {
  const _QueueHeader({
    required this.tasks,
    required this.title,
    required this.subtitle,
  });

  final List<dynamic> tasks;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final urgent = tasks.where((item) {
      final priority = item['priority']?.toString();
      return priority == 'high' || priority == 'very_high';
    }).length;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF172554), Color(0xFF1E40AF), Color(0xFF0284C7)],
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: .2),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                '${tasks.length}',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 28,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                urgent == 0 ? 'tasks' : '$urgent priority',
                style: const TextStyle(color: Colors.white70, fontSize: 11),
              ),
            ],
          ),
        ],
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
    final taskType = task['task_type']?.toString() ?? 'workflow';
    final priorityColor = priority == 'very_high'
        ? AppColors.danger
        : priority == 'high'
        ? AppColors.warning
        : AppColors.secondary;
    final typeColor = taskType == 'ticket'
        ? AppColors.secondary
        : AppColors.primary;
    final isClosed = task['status']?.toString() == 'closed';
    final originalDue = DateTime.tryParse(task['due_at']?.toString() ?? '');
    final overdue =
        !isClosed &&
        originalDue != null &&
        originalDue.isBefore(DateTime.now());
    final actionsCount = (task['actions_count'] as num?)?.toInt() ?? 0;
    return Card(
      color: task['parent_task_id'] != null ? const Color(0xFFFAF5FF) : null,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        borderRadius: BorderRadius.zero,
        onTap: () => showModalBottomSheet<void>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          showDragHandle: true,
          builder: (_) => FractionallySizedBox(
            heightFactor: .92,
            child: _TaskDetails(
              taskId: task['id'] as int,
              onUpdated: onUpdated,
            ),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(17),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Wrap(
                      spacing: 7,
                      runSpacing: 6,
                      children: [
                        _Pill(
                          text: taskType == 'ticket'
                              ? 'COMPLAINT'
                              : (task['parent_task_id'] != null
                                    ? 'SUBTASK'
                                    : 'WORKFLOW'),
                          color: typeColor,
                        ),
                        _Pill(
                          text: priority.replaceAll('_', ' ').toUpperCase(),
                          color: priorityColor,
                        ),
                        _Pill(
                          text: isClosed
                              ? 'CLOSED'
                              : (overdue ? 'OVERDUE' : 'PENDING'),
                          color: isClosed
                              ? AppColors.success
                              : (overdue
                                    ? AppColors.danger
                                    : AppColors.warning),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    task['task_no']?.toString() ?? '',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              if (task['parent'] != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    '↳ ${task['parent']['task_no']}',
                    style: const TextStyle(
                      color: Color(0xFF7C3AED),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              if ((task['subtasks_count'] as num? ?? 0) > 0)
                Text(
                  '${task['completed_subtasks_count']}/${task['subtasks_count']} subtasks completed',
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
              Text(
                task['title']?.toString() ?? 'Untitled task',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 7),
              Text(
                task['customer']?['name']?.toString() ?? 'Internal workflow',
                style: const TextStyle(color: AppColors.muted, fontSize: 13),
              ),
              if (task['latest_action'] != null) ...[
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        LucideIcons.activity,
                        size: 15,
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
                            fontSize: 11,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              if (List<dynamic>.from(task['observers'] ?? []).isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Row(
                    children: [
                      const Text(
                        'Observers',
                        style: TextStyle(color: AppColors.muted, fontSize: 12),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _ObserverAvatars(
                          people: List<dynamic>.from(task['observers'])
                              .map((member) => member['user'])
                              .whereType<Map>()
                              .toList(),
                        ),
                      ),
                    ],
                  ),
                ),
              if (task['next_follow_up_at'] != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    'Next follow-up: ${_date(task['next_follow_up_at']).replaceFirst('Due ', '')}',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                    ),
                  ),
                ),
              const Divider(height: 28),
              Row(
                children: [
                  const Icon(
                    LucideIcons.clock3,
                    size: 16,
                    color: AppColors.muted,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      _date(task['due_at']),
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  Text(
                    '$actionsCount updates',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(width: 3),
                  const Icon(Icons.chevron_right, color: AppColors.primary),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _date(dynamic raw) {
    final date = DateTime.tryParse(raw?.toString() ?? '');
    return date == null
        ? 'No due date'
        : 'Due ${DateFormat('d MMM, hh:mm a').format(date.toLocal())}';
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
  Map<String, dynamic>? task;
  List<dynamic> assignees = [];
  String action = 'close';
  String updateStatus = 'pending';
  bool reassign = false;
  DateTime? nextFollowUpAt;
  final delayReason = TextEditingController();
  final List<XFile> attachments = [];
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
      updateStatus = task?['status']?.toString() ?? 'pending';
      nextFollowUpAt = DateTime.tryParse(
        task?['next_follow_up_at']?.toString() ?? '',
      )?.toLocal();
      reassign = false;
      delayReason.clear();
      assignedTo = null;
      error = null;
    } on DioException catch (exception) {
      error =
          exception.response?.data?['message']?.toString() ??
          'Unable to open this task.';
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
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => FractionallySizedBox(
        heightFactor: .92,
        child: _TaskDetails(taskId: id, onUpdated: widget.onUpdated),
      ),
    );
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
                const SizedBox(height: 12),
                TextField(
                  onChanged: (value) => title = value,
                  maxLength: 255,
                  decoration: const InputDecoration(labelText: 'Title *'),
                ),
                const SizedBox(height: 12),
                TextField(
                  onChanged: (value) => description = value,
                  maxLines: 3,
                  maxLength: 5000,
                  decoration: const InputDecoration(labelText: 'Description'),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  decoration: const InputDecoration(
                    labelText: 'Responsible Person *',
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
                const SizedBox(height: 12),
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
                  title: const T('Attachment required when complete'),
                  value: evidenceRequired,
                  onChanged: saving
                      ? null
                      : (value) => update(() => evidenceRequired = value),
                ),
                if (validation != null)
                  Text(
                    validation!,
                    style: const TextStyle(color: AppColors.danger),
                  ),
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
                            validation =
                                exception.response?.data?['message']
                                    ?.toString() ??
                                'Unable to create subtask.';
                          });
                        }
                      }
                    },
              child: Text(saving ? 'Creating…' : 'Create & assign'),
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
    final workflow = task?['task_type'] != 'ticket';
    final completing = workflow ? updateStatus == 'closed' : action == 'close';
    final assigning = workflow ? reassign && !completing : action == 'assign';
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
      final isTicket = task?['task_type'] == 'ticket';
      final workLabel = isTicket ? 'complaint' : 'workflow';
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          icon: Icon(
            completing ? LucideIcons.circleCheckBig : LucideIcons.forward,
            color: completing ? AppColors.success : AppColors.primary,
          ),
          title: Text(completing ? 'Close this $workLabel?' : 'Assign next?'),
          content: Text(
            completing
                ? 'This $workLabel will move out of your pending queue.'
                : 'This $workLabel will stay pending and move to the selected assignee.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const T('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(completing ? 'Close $workLabel' : 'Assign'),
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
          'action': workflow ? 'update' : action,
          if (workflow) ...{
            'status': updateStatus,
            'reassign': assigning ? 1 : 0,
          },
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
      if (mounted) Navigator.pop(context);
    } on DioException catch (exception) {
      if (mounted) {
        setState(() {
          error =
              exception.response?.data?['message']?.toString() ??
              'Unable to update this task.';
        });
      }
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (task == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(error ?? 'Unable to open task.'),
        ),
      );
    }

    final currentUserId = context.read<AuthController>().user?['id'];
    final canAct =
        task?['status'] != 'closed' && task?['assigned_to'] == currentUserId;
    final actions = List<dynamic>.from(task?['actions'] ?? []);
    final type = task?['task_type']?.toString() ?? 'workflow';
    final due = DateTime.tryParse(task?['due_at']?.toString() ?? '');
    final overdue =
        task?['status'] != 'closed' &&
        due != null &&
        due.isBefore(DateTime.now());

    final keyboardInset = MediaQuery.viewInsetsOf(context).bottom;
    return ListView(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: EdgeInsets.fromLTRB(22, 0, 22, 32 + keyboardInset),
      children: [
        Row(
          children: [
            _Pill(
              text: type == 'ticket'
                  ? 'COMPLAINT'
                  : (task?['parent_task_id'] != null ? 'SUBTASK' : 'WORKFLOW'),
              color: type == 'ticket' ? AppColors.secondary : AppColors.primary,
            ),
            const SizedBox(width: 8),
            _Pill(
              text: overdue
                  ? 'OVERDUE'
                  : (task?['status']
                            ?.toString()
                            .replaceAll('_', ' ')
                            .toUpperCase() ??
                        'PENDING'),
              color: task?['status'] == 'closed'
                  ? AppColors.success
                  : (overdue ? AppColors.danger : AppColors.warning),
            ),
            const Spacer(),
            Text(
              task?['task_no']?.toString() ?? '',
              style: const TextStyle(color: AppColors.muted),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Text(
          task?['title']?.toString() ?? '',
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        Text(
          task?['description']?.toString() ?? 'No description provided.',
          style: const TextStyle(color: AppColors.muted, height: 1.5),
        ),
        const SizedBox(height: 18),
        _WorkflowProgress(
          closed: task?['status'] == 'closed',
          actions: actions.length,
        ),
        const SizedBox(height: 18),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                _DetailRow(
                  label: 'Customer',
                  value: task?['customer']?['name']?.toString() ?? 'Internal',
                ),
                _DetailRow(
                  label: 'Responsible Person',
                  value: task?['assignee']?['name']?.toString() ?? '—',
                ),
                _DetailRow(
                  label: 'Original due',
                  value: _TaskCard._date(task?['due_at']),
                ),
                if (task?['next_follow_up_at'] != null)
                  _DetailRow(
                    label: 'Next follow-up',
                    value: _TaskCard._date(
                      task?['next_follow_up_at'],
                    ).replaceFirst('Due ', ''),
                  ),
                if (task?['revised_due_at'] != null)
                  _DetailRow(
                    label: 'Latest revised due',
                    value: _TaskCard._date(
                      task?['revised_due_at'],
                    ).replaceFirst('Due ', ''),
                  ),
                _DetailRow(
                  label: 'Last updated',
                  value:
                      DateTime.tryParse(
                            task?['updated_at']?.toString() ?? '',
                          ) ==
                          null
                      ? '—'
                      : DateFormat('d MMM, hh:mm a').format(
                          DateTime.parse(
                            task!['updated_at'].toString(),
                          ).toLocal(),
                        ),
                  last: true,
                ),
              ],
            ),
          ),
        ),
        if (List<dynamic>.from(task?['observers'] ?? []).isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const T(
                  'Observers',
                  style: TextStyle(
                    color: AppColors.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                _ObserverAvatars(
                  people: List<dynamic>.from(
                    task?['observers'],
                  ).map((member) => member['user']).whereType<Map>().toList(),
                ),
              ],
            ),
          ),
        if (task?['parent'] != null)
          TextButton.icon(
            onPressed: () => openRelated(task!['parent']['id'] as int),
            icon: const Icon(LucideIcons.gitBranch),
            label: Text('Parent: ${task!['parent']['task_no']}'),
          ),
        if (type != 'ticket') ...[
          const SizedBox(height: 18),
          Row(
            children: [
              const Expanded(
                child: T(
                  'Subtasks',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              if (canAct)
                TextButton.icon(
                  onPressed: submitting ? null : createSubtask,
                  icon: const Icon(LucideIcons.plus, size: 17),
                  label: const T('Create subtask'),
                ),
            ],
          ),
          for (final child in List<dynamic>.from(task?['subtasks'] ?? []))
            Card(
              color: const Color(0xFFFAF5FF),
              child: ListTile(
                leading: Icon(
                  child['status'] == 'closed'
                      ? LucideIcons.circleCheckBig
                      : LucideIcons.gitBranch,
                  color: child['status'] == 'closed'
                      ? AppColors.success
                      : const Color(0xFF7C3AED),
                ),
                title: Text(child['title']?.toString() ?? ''),
                subtitle: Text(
                  '${child['task_no']} · ${child['assignee']?['name'] ?? ''} · ${child['status'] == 'closed' ? 'Completed' : 'Pending'}',
                ),
                onTap: () => openRelated(child['id'] as int),
              ),
            ),
        ],
        if (canAct) ...[
          const SizedBox(height: 22),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const T(
                    'Task update',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: remark,
                    maxLines: 3,
                    scrollPadding: EdgeInsets.only(bottom: keyboardInset + 120),
                    decoration: const InputDecoration(
                      labelText: 'Remark *',
                      hintText: 'Describe work completed or next steps',
                    ),
                  ),
                  if (type != 'ticket') ...[
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      initialValue: updateStatus,
                      key: ValueKey('status$updateStatus'),
                      decoration: const InputDecoration(labelText: 'Status *'),
                      items: const [
                        DropdownMenuItem(value: 'pending', child: T('Pending')),
                        DropdownMenuItem(
                          value: 'in_progress',
                          child: T('In progress'),
                        ),
                        DropdownMenuItem(
                          value: 'closed',
                          child: T('Completed'),
                        ),
                      ],
                      onChanged: submitting
                          ? null
                          : (value) => setState(() {
                              updateStatus = value!;
                              if (updateStatus == 'closed') reassign = false;
                            }),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<bool>(
                      isExpanded: true,
                      initialValue: reassign,
                      key: ValueKey('reassign$reassign'),
                      decoration: const InputDecoration(
                        labelText: 'Assign to someone else?',
                      ),
                      items: const [
                        DropdownMenuItem(
                          value: false,
                          child: T('No — keep current owner'),
                        ),
                        DropdownMenuItem(value: true, child: T('Yes')),
                      ],
                      onChanged: submitting || updateStatus == 'closed'
                          ? null
                          : (value) =>
                                setState(() => reassign = value ?? false),
                    ),
                  ] else ...[
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(
                          value: 'close',
                          icon: Icon(LucideIcons.checkCircle2),
                          label: T('Close'),
                        ),
                        ButtonSegment(
                          value: 'assign',
                          icon: Icon(LucideIcons.forward),
                          label: T('Assign next'),
                        ),
                      ],
                      selected: {action},
                      onSelectionChanged: (selection) {
                        setState(() => action = selection.first);
                      },
                    ),
                  ],
                  if (type != 'ticket' ? reassign : action == 'assign') ...[
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: assignedTo,
                      decoration: const InputDecoration(
                        labelText: 'Responsible Person *',
                      ),
                      items: assignees
                          .where((item) => item['id'] != task?['assigned_to'])
                          .map(
                            (item) => DropdownMenuItem<int>(
                              value: item['id'] as int,
                              child: Text(item['name']?.toString() ?? 'User'),
                            ),
                          )
                          .toList(),
                      onChanged: (value) => setState(() => assignedTo = value),
                    ),
                  ],
                  const SizedBox(height: 12),
                  const T(
                    'Next follow-up date & time',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  TextButton.icon(
                    onPressed: submitting ? null : pickFollowUpDate,
                    icon: const Icon(LucideIcons.calendarClock),
                    label: Text(
                      nextFollowUpAt == null
                          ? 'Select follow-up'
                          : DateFormat(
                              'd MMM yyyy, hh:mm a',
                            ).format(nextFollowUpAt!),
                    ),
                  ),
                  if (nextFollowUpAt != null)
                    TextButton(
                      onPressed: submitting
                          ? null
                          : () => setState(() => nextFollowUpAt = null),
                      child: const T('Clear follow-up'),
                    ),
                  const T(
                    'The original due date stays fixed.',
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                  if (followUpRevisesDue) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Revised due date: ${DateFormat('d MMM yyyy, hh:mm a').format(nextFollowUpAt!)}',
                      style: const TextStyle(
                        color: AppColors.warning,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (followUpChanged)
                      TextField(
                        controller: delayReason,
                        maxLines: 2,
                        maxLength: 3000,
                        decoration: const InputDecoration(
                          labelText: 'Reason for delay *',
                        ),
                      ),
                  ],
                  const SizedBox(height: 12),
                  Text(
                    task?['attachment_required_on_completion'] == true
                        ? 'Attachment required to complete'
                        : 'Attachments (optional)',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  Wrap(
                    spacing: 8,
                    children: [
                      TextButton.icon(
                        onPressed: submitting
                            ? null
                            : () => pickAttachments(ImageSource.camera),
                        icon: const Icon(LucideIcons.camera, size: 17),
                        label: const T('Take photo'),
                      ),
                      TextButton.icon(
                        onPressed: submitting
                            ? null
                            : () => pickAttachments(ImageSource.gallery),
                        icon: const Icon(LucideIcons.image, size: 17),
                        label: const T('Choose photos'),
                      ),
                    ],
                  ),
                  for (final file in attachments)
                    ListTile(
                      dense: true,
                      leading: const Icon(LucideIcons.paperclip, size: 17),
                      title: Text(file.name),
                      trailing: IconButton(
                        onPressed: submitting
                            ? null
                            : () => setState(() => attachments.remove(file)),
                        icon: const Icon(LucideIcons.x, size: 17),
                      ),
                    ),
                  if (error != null) ...[
                    const SizedBox(height: 10),
                    Text(
                      error!,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ],
                  const SizedBox(height: 14),
                  FilledButton.icon(
                    onPressed: submitting ? null : submit,
                    icon: submitting
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Icon(
                            type != 'ticket'
                                ? LucideIcons.save
                                : (action == 'close'
                                      ? LucideIcons.check
                                      : LucideIcons.send),
                          ),
                    label: Text(submitting ? 'Saving…' : 'Save update'),
                  ),
                ],
              ),
            ),
          ),
        ] else if (task?['status'] != 'closed') ...[
          const SizedBox(height: 18),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: T(
                'You can view this workflow as an observer. Only the Responsible Person can take action.',
                style: TextStyle(color: AppColors.muted),
              ),
            ),
          ),
        ],
        const SizedBox(height: 22),
        const T(
          'Activity',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        if (actions.isEmpty)
          const T('No activity yet.', style: TextStyle(color: AppColors.muted))
        else
          ...actions.map(
            (item) => _ActionTile(
              action: item,
              isSubtask: task?['parent_task_id'] != null,
            ),
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
          child: CircleAvatar(
            radius: 15,
            backgroundColor: HSVColor.fromAHSV(
              1,
              ((person['id'] as num? ?? 0).toDouble() * 47) % 360,
              .12,
              .96,
            ).toColor(),
            foregroundColor: AppColors.primary,
            child: Text(
              (person['name']?.toString() ?? '?')
                  .trim()
                  .split(RegExp(r'\s+'))
                  .where((part) => part.isNotEmpty)
                  .take(2)
                  .map((part) => part[0])
                  .join()
                  .toUpperCase(),
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800),
            ),
          ),
        ),
    ],
  );
}

class _ActionTile extends StatefulWidget {
  const _ActionTile({required this.action, this.isSubtask = false});
  final dynamic action;
  final bool isSubtask;

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
    final title = subtaskCreated
        ? 'Subtask created and assigned'
        : switch (action['action']) {
            'created' => 'Created',
            'assign' => 'Assigned',
            'close' => 'Completed',
            'returned' => 'Returned to creator',
            'update' => 'Progress update',
            'status' => 'Status changed',
            'priority' => 'Priority changed',
            'due_date' => 'Due date changed',
            _ => 'Update',
          };
    return Container(
      margin: const EdgeInsets.only(bottom: 7),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(
          left: BorderSide(color: Color(0xFFCBD5E1), width: 2),
          bottom: BorderSide(color: Color(0xFFEDF2F7)),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                created == null
                    ? ''
                    : DateFormat('d MMM, h:mm a').format(created.toLocal()),
                style: const TextStyle(color: AppColors.muted, fontSize: 9),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            '${action['user']?['name'] ?? 'System'}${action['assignee'] == null ? '' : ' → ${action['assignee']['name']}'}',
            style: const TextStyle(
              color: AppColors.muted,
              fontSize: 10,
              fontWeight: FontWeight.w600,
            ),
          ),
          if (action['next_follow_up_at'] != null)
            Text(
              'Next follow-up: ${_TaskCard._date(action['next_follow_up_at']).replaceFirst('Due ', '')}',
              style: const TextStyle(fontSize: 11, color: AppColors.primary),
            ),
          if (action['revised_due_at'] != null)
            Text(
              'Revised due: ${_TaskCard._date(action['revised_due_at']).replaceFirst('Due ', '')} · Reason for delay: ${action['delay_reason'] ?? ''}',
              style: const TextStyle(fontSize: 11, color: AppColors.warning),
            ),
          if (remark.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              remark,
              maxLines: expanded ? null : 3,
              overflow: expanded ? TextOverflow.visible : TextOverflow.ellipsis,
              style: const TextStyle(
                color: AppColors.muted,
                fontSize: 11,
                height: 1.45,
              ),
            ),
            if (remark.length > 90 || remark.split('\n').length > 2)
              GestureDetector(
                onTap: () => setState(() => expanded = !expanded),
                child: Padding(
                  padding: const EdgeInsets.only(top: 3),
                  child: Text(
                    expanded ? 'Show less' : 'Read more',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
          ],
          for (final file in List<dynamic>.from(action['attachments'] ?? []))
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                '📎 ${file['name']}',
                style: const TextStyle(color: AppColors.primary, fontSize: 10),
              ),
            ),
        ],
      ),
    );
  }
}

class _WorkflowProgress extends StatelessWidget {
  const _WorkflowProgress({required this.closed, required this.actions});

  final bool closed;
  final int actions;

  @override
  Widget build(BuildContext context) {
    final steps = [
      (label: 'Created', icon: LucideIcons.plus, done: true),
      (label: 'Assigned', icon: LucideIcons.userCheck, done: true),
      (
        label: closed ? 'Processed' : 'In workflow',
        icon: LucideIcons.activity,
        done: closed || actions > 1,
      ),
      (label: 'Closed', icon: LucideIcons.circleCheckBig, done: closed),
    ];
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        borderRadius: BorderRadius.zero,
      ),
      child: Row(
        children: List.generate(steps.length, (index) {
          final step = steps[index];
          final active = !closed && index == 2;
          final color = step.done
              ? AppColors.success
              : active
              ? AppColors.primary
              : const Color(0xFFCBD5E1);
          return Expanded(
            child: Column(
              children: [
                Row(
                  children: [
                    if (index > 0)
                      Expanded(child: Divider(color: color, thickness: 2)),
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: step.done || active ? color : Colors.white,
                        shape: BoxShape.circle,
                        border: Border.all(color: color, width: 2),
                      ),
                      child: Icon(
                        step.icon,
                        size: 14,
                        color: step.done || active ? Colors.white : color,
                      ),
                    ),
                    if (index < steps.length - 1)
                      Expanded(
                        child: Divider(
                          color: step.done
                              ? AppColors.success
                              : const Color(0xFFCBD5E1),
                          thickness: 2,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 7),
                Text(
                  step.label,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: active ? AppColors.primary : AppColors.muted,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    required this.value,
    this.last = false,
  });

  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 9),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: Color(0xFFE8EEF6))),
    ),
    child: Row(
      children: [
        Expanded(
          child: Text(label, style: const TextStyle(color: AppColors.muted)),
        ),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _Pill extends StatelessWidget {
  const _Pill({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .12),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      text,
      style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800),
    ),
  );
}

class _Empty extends StatelessWidget {
  const _Empty({
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
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () async => onRefresh(),
    child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 150),
        Icon(icon, size: 48, color: AppColors.muted),
        const SizedBox(height: 14),
        Center(
          child: Text(
            title,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(height: 5),
        Center(
          child: Text(subtitle, style: const TextStyle(color: AppColors.muted)),
        ),
      ],
    ),
  );
}
