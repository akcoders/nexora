import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';

class TasksScreen extends StatefulWidget {
  const TasksScreen({super.key});

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen>
    with SingleTickerProviderStateMixin {
  late final TabController tabs = TabController(length: 2, vsync: this);
  int reload = 0;

  Future<List<dynamic>> load(String status) async {
    final response = await context.read<ApiClient>().dio.get(
      'tasks',
      queryParameters: {'status': status},
    );
    return List<dynamic>.from(response.data['data'] ?? []);
  }

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
        actions: [IconButton(onPressed: () {}, icon: const Icon(Icons.tune))],
        bottom: TabBar(
          controller: tabs,
          tabs: const [
            Tab(text: 'Pending'),
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
            key: ValueKey('pending$reload'),
            future: load('pending'),
            onRefresh: () async => setState(() => reload++),
          ),
          _TaskList(
            key: ValueKey('closed$reload'),
            future: load('closed'),
            onRefresh: () async => setState(() => reload++),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.small(
        onPressed: () => setState(() => reload++),
        child: const Icon(Icons.refresh),
      ),
    );
  }
}

class _TaskList extends StatelessWidget {
  const _TaskList({super.key, required this.future, required this.onRefresh});
  final Future<List<dynamic>> future;
  final Future<void> Function() onRefresh;

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
            subtitle: 'Pull down to try again.',
          );
        }
        final tasks = snapshot.data ?? [];
        if (tasks.isEmpty) {
          return const _Empty(
            icon: LucideIcons.checkCircle2,
            title: 'Nothing here',
            subtitle: 'Your tasks will appear here.',
          );
        }
        return RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(18),
            itemCount: tasks.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) =>
                _TaskCard(task: Map<String, dynamic>.from(tasks[index])),
          ),
        );
      },
    );
  }
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({required this.task});
  final Map<String, dynamic> task;

  @override
  Widget build(BuildContext context) {
    final priority = task['priority']?.toString() ?? 'normal';
    final color = priority == 'very_high'
        ? AppColors.danger
        : priority == 'high'
        ? AppColors.warning
        : AppColors.secondary;
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => _details(context),
        child: Padding(
          padding: const EdgeInsets.all(17),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: .12),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      priority.replaceAll('_', ' ').toUpperCase(),
                      style: TextStyle(
                        color: color,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
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
                task['customer']?['name']?.toString() ?? 'Internal task',
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
                      task['due_at']?.toString().substring(0, 10) ??
                          'No due date',
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.muted),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _details(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          22,
          6,
          22,
          MediaQuery.viewInsetsOf(context).bottom + 26,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              task['task_no']?.toString() ?? '',
              style: const TextStyle(
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              task['title']?.toString() ?? '',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            Text(
              task['description']?.toString() ?? 'No description',
              style: const TextStyle(color: AppColors.muted, height: 1.5),
            ),
            const SizedBox(height: 22),
            if (task['status'] == 'pending')
              FilledButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Take action'),
              ),
          ],
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({
    required this.icon,
    required this.title,
    required this.subtitle,
  });
  final IconData icon;
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(30),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 48, color: AppColors.muted),
          const SizedBox(height: 14),
          Text(
            title,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          Text(subtitle, style: const TextStyle(color: AppColors.muted)),
        ],
      ),
    ),
  );
}
