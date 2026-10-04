import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../auth/auth_controller.dart';

class TasksScreen extends StatefulWidget {
  const TasksScreen({super.key});

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen>
    with SingleTickerProviderStateMixin {
  late final TabController tabs = TabController(length: 3, vsync: this);
  int reload = 0;

  Future<List<dynamic>> load({required String status, String? type}) async {
    final query = <String, dynamic>{'status': status};
    if (type != null) query['type'] = type;
    final response = await context.read<ApiClient>().dio.get(
      'tasks',
      queryParameters: query,
    );
    return List<dynamic>.from(response.data['data'] ?? []);
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
        title: const Text('My tasks'),
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
            Tab(text: 'Jobs'),
            Tab(text: 'Workflow'),
            Tab(text: 'Closed'),
          ],
          labelColor: AppColors.primary,
          indicatorColor: AppColors.primary,
        ),
      ),
      body: TabBarView(
        controller: tabs,
        children: [
          _TaskList(
            key: ValueKey('jobs$reload'),
            future: load(status: 'pending', type: 'job'),
            onRefresh: refresh,
          ),
          _TaskList(
            key: ValueKey('workflow$reload'),
            future: load(status: 'pending', type: 'workflow'),
            onRefresh: refresh,
          ),
          _TaskList(
            key: ValueKey('closed$reload'),
            future: load(status: 'closed'),
            onRefresh: refresh,
          ),
        ],
      ),
    );
  }
}

class _TaskList extends StatelessWidget {
  const _TaskList({super.key, required this.future, required this.onRefresh});

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
            itemCount: tasks.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) => _TaskCard(
              task: Map<String, dynamic>.from(tasks[index]),
              onUpdated: onRefresh,
            ),
          ),
        );
      },
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
    final taskType = task['task_type']?.toString() ?? 'job';
    final priorityColor = priority == 'very_high'
        ? AppColors.danger
        : priority == 'high'
        ? AppColors.warning
        : AppColors.secondary;
    final typeColor = taskType == 'workflow'
        ? AppColors.primary
        : AppColors.success;
    return Card(
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
                  _Pill(
                    text: taskType == 'workflow' ? 'WORKFLOW' : 'JOB',
                    color: typeColor,
                  ),
                  const SizedBox(width: 7),
                  _Pill(
                    text: priority.replaceAll('_', ' ').toUpperCase(),
                    color: priorityColor,
                  ),
                  const Spacer(),
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
                  const Text(
                    'Open',
                    style: TextStyle(
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

  Future<void> submit() async {
    if (remark.text.trim().isEmpty) {
      setState(() => error = 'Please enter a remark.');
      return;
    }
    if (action == 'assign' && assignedTo == null) {
      setState(() => error = 'Select the next assignee.');
      return;
    }

    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await context.read<ApiClient>().dio.post(
        'tasks/${widget.taskId}/action',
        data: {
          'action': action,
          'remark': remark.text.trim(),
          if (action == 'assign') 'assigned_to': assignedTo,
        },
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
        task?['status'] == 'pending' && task?['assigned_to'] == currentUserId;
    final actions = List<dynamic>.from(task?['actions'] ?? []);
    final type = task?['task_type']?.toString() ?? 'job';

    return ListView(
      padding: const EdgeInsets.fromLTRB(22, 0, 22, 32),
      children: [
        Row(
          children: [
            _Pill(
              text: type == 'workflow' ? 'WORKFLOW' : 'JOB',
              color: type == 'workflow' ? AppColors.primary : AppColors.success,
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
        const SizedBox(height: 22),
        const Text(
          'Activity',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        if (actions.isEmpty)
          const Text(
            'No activity yet.',
            style: TextStyle(color: AppColors.muted),
          )
        else
          ...actions.map((item) => _ActionTile(action: item)),
        if (canAct) ...[
          const SizedBox(height: 22),
          const Text(
            'Take action',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(
                value: 'close',
                icon: Icon(LucideIcons.checkCircle2),
                label: Text('Close'),
              ),
              ButtonSegment(
                value: 'assign',
                icon: Icon(LucideIcons.forward),
                label: Text('Assign next'),
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
        ] else if (task?['status'] == 'pending') ...[
          const SizedBox(height: 18),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text(
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
