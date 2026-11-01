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
          _TaskList(
            key: ValueKey('workflow$reload'),
            future: load(scope: 'open', type: 'workflow'),
            title: 'My workflow',
            subtitle: 'All active hand-offs, not only pending tasks',
            onRefresh: refresh,
          ),
          _TaskList(
            key: ValueKey('tickets$reload'),
            future: load(scope: 'open', type: 'ticket'),
            title: 'Customer complaints',
            subtitle: 'Customer complaints assigned to you',
            onRefresh: refresh,
          ),
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
      borderRadius: BorderRadius.circular(18),
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
    final actionsCount = (task['actions_count'] as num?)?.toInt() ?? 0;
    return Card(
      color: task['parent_task_id'] != null ? const Color(0xFFFAF5FF) : null,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
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
                          text: isClosed ? 'CLOSED' : 'ACTIVE',
                          color: isClosed
                              ? AppColors.success
                              : AppColors.warning,
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
    super.dispose();
  }

  Future<void> load() async {
    try {
      final response = await context.read<ApiClient>().dio.get(
        'tasks/${widget.taskId}',
      );
      task = Map<String, dynamic>.from(response.data['task']);
      assignees = List<dynamic>.from(response.data['assignees'] ?? []);
      if (assignedTo == task?['assigned_to']) assignedTo = null;
      error = null;
      assignedTo ??= assignees
          .where((item) => item['id'] != task?['assigned_to'])
          .map<int?>((item) => item['id'] as int?)
          .firstOrNull;
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

  Future<void> changeDueDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate:
          DateTime.tryParse(task?['due_at']?.toString() ?? '')?.toLocal() ??
          DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(
        DateTime.tryParse(task?['due_at']?.toString() ?? '')?.toLocal() ??
            DateTime.now(),
      ),
    );
    if (time == null || !mounted) return;
    String reason = '';
    String? validation;
    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const T('Change due date'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  DateFormat('d MMM yyyy, hh:mm a').format(
                    DateTime(
                      date.year,
                      date.month,
                      date.day,
                      time.hour,
                      time.minute,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  onChanged: (value) => reason = value,
                  maxLength: 3000,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: 'Reason for changing due date *',
                    errorText: validation,
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const T('Cancel'),
            ),
            FilledButton(
              onPressed: () {
                if (reason.trim().isEmpty) {
                  update(() => validation = 'Please enter a reason.');
                  return;
                }
                Navigator.pop(dialogContext, true);
              },
              child: const T('Update'),
            ),
          ],
        ),
      ),
    );
    final reasonText = reason.trim();
    if (saved != true || !mounted) return;
    setState(() => submitting = true);
    try {
      await context.read<ApiClient>().dio.post(
        'tasks/${widget.taskId}/due-date',
        data: {
          'due_at': DateTime(
            date.year,
            date.month,
            date.day,
            time.hour,
            time.minute,
          ).toIso8601String(),
          'reason': reasonText,
        },
      );
      widget.onUpdated();
      if (mounted) await load();
    } on DioException catch (exception) {
      if (mounted) {
        setState(
          () => error =
              exception.response?.data?['message']?.toString() ??
              'Unable to change due date.',
        );
      }
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  Future<void> createSubtask() async {
    String title = '';
    String description = '';
    int? owner;
    bool evidenceRequired = false;
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
                    labelText: 'Subtask owner *',
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
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const T('Attachment required when complete'),
                  value: evidenceRequired,
                  onChanged: saving
                      ? null
                      : (value) =>
                            update(() => evidenceRequired = value ?? false),
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

  Future<void> submit() async {
    if (remark.text.trim().isEmpty) {
      setState(() => error = 'Please enter a remark.');
      return;
    }
    if (action == 'assign' && assignedTo == null) {
      setState(() => error = 'Select the next assignee.');
      return;
    }

    if (action == 'close' &&
        task?['attachment_required_on_completion'] == true &&
        attachments.isEmpty) {
      setState(
        () => error =
            'Attach at least one photo before completing this workflow.',
      );
      return;
    }
    final isTicket = task?['task_type'] == 'ticket';
    final workLabel = isTicket ? 'complaint' : 'workflow';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: Icon(
          action == 'close' ? LucideIcons.circleCheckBig : LucideIcons.forward,
          color: action == 'close' ? AppColors.success : AppColors.primary,
        ),
        title: Text(
          action == 'close' ? 'Close this $workLabel?' : 'Assign next?',
        ),
        content: Text(
          action == 'close'
              ? 'This $workLabel will move out of your active queue.'
              : 'This $workLabel will stay active and move to the selected assignee.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const T('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(action == 'close' ? 'Close $workLabel' : 'Assign'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await context.read<ApiClient>().dio.post(
        'tasks/${widget.taskId}/action',
        data: FormData.fromMap({
          'action': action,
          'remark': remark.text.trim(),
          if (action == 'assign') 'assigned_to': assignedTo,
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
              text: task?['status'].toString().toUpperCase() ?? '',
              color: task?['status'] == 'closed'
                  ? AppColors.success
                  : AppColors.warning,
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
                  label: 'Assignee',
                  value: task?['assignee']?['name']?.toString() ?? '—',
                ),
                _DetailRow(
                  label: 'Due',
                  value: _TaskCard._date(task?['due_at']),
                  last: true,
                ),
              ],
            ),
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
                  '${child['task_no']} · ${child['assignee']?['name'] ?? ''} · ${child['status'] == 'closed' ? 'Completed' : 'Active'}',
                ),
                onTap: () => openRelated(child['id'] as int),
              ),
            ),
        ],
        if (canAct)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: submitting ? null : changeDueDate,
              icon: const Icon(LucideIcons.calendarClock, size: 17),
              label: const T('Change due date'),
            ),
          ),
        const SizedBox(height: 22),
        const T(
          'Activity',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        if (actions.isEmpty)
          const T('No activity yet.', style: TextStyle(color: AppColors.muted))
        else
          ...actions.map((item) => _ActionTile(action: item)),
        if (canAct) ...[
          const SizedBox(height: 22),
          const T(
            'Take action',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
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
          if (action == 'assign') ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: assignedTo,
              decoration: const InputDecoration(labelText: 'Next assignee *'),
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
            Text(error!, style: const TextStyle(color: AppColors.danger)),
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
                    action == 'close' ? LucideIcons.check : LucideIcons.send,
                  ),
            label: Text(submitting ? 'Saving…' : 'Submit action'),
          ),
        ] else if (task?['status'] != 'closed') ...[
          const SizedBox(height: 18),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: T(
                'You can view this workflow as an observer. Only the current assignee can take action.',
                style: TextStyle(color: AppColors.muted),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({required this.action});

  final dynamic action;

  @override
  Widget build(BuildContext context) {
    final created = DateTime.tryParse(action['created_at']?.toString() ?? '');
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: const Color(0xFFDBEAFE),
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Icon(
              LucideIcons.activity,
              size: 17,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  action['action']?.toString().toUpperCase() ?? 'ACTION',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                Text(
                  action['remark']?.toString() ?? '',
                  style: const TextStyle(color: AppColors.muted),
                ),
                for (final file in List<dynamic>.from(
                  action['attachments'] ?? [],
                ))
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      '📎 ${file['name']}',
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontSize: 12,
                      ),
                    ),
                  ),
                const SizedBox(height: 3),
                Text(
                  '${action['user']?['name'] ?? 'User'} · ${created == null ? '' : DateFormat('d MMM, hh:mm a').format(created.toLocal())}',
                  style: const TextStyle(color: AppColors.muted, fontSize: 11),
                ),
              ],
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
        borderRadius: BorderRadius.circular(16),
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
