import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../attendance/attendance_screen.dart';
import '../auth/auth_controller.dart';
import '../hr/hr_self_service_screen.dart';
import '../notifications/notifications_screen.dart';
import '../profile/profile_screen.dart';
import '../tasks/tasks_screen.dart';
import 'home_screen.dart';

class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  final scaffoldKey = GlobalKey<ScaffoldState>();
  int index = 0;
  int taskTab = 0;
  int homeRefresh = 0;

  @override
  void initState() {
    super.initState();
    _restoreNavigation();
  }

  Future<void> _restoreNavigation() async {
    final storage = context.read<FlutterSecureStorage>();
    final values = await Future.wait([
      storage.read(key: 'active_navigation_tab'),
      storage.read(key: 'active_task_tab'),
    ]);
    final restoredIndex = int.tryParse(values[0] ?? '');
    final restoredTaskTab = int.tryParse(values[1] ?? '');
    if (!mounted) return;
    setState(() {
      if (restoredIndex != null && restoredIndex >= 0 && restoredIndex < 5) {
        index = restoredIndex;
      }
      if (restoredTaskTab != null &&
          restoredTaskTab >= 0 &&
          restoredTaskTab < 4) {
        taskTab = restoredTaskTab;
      }
    });
  }

  void openMenu() => scaffoldKey.currentState?.openDrawer();

  void navigate(int value, {int? workTab, bool closeDrawer = false}) {
    if (closeDrawer && Navigator.canPop(context)) Navigator.pop(context);
    setState(() {
      if (value == 0 && index != 0) homeRefresh++;
      index = value;
      if (workTab != null) taskTab = workTab;
    });
    final storage = context.read<FlutterSecureStorage>();
    storage.write(key: 'active_navigation_tab', value: '$value');
    if (workTab != null) {
      storage.write(key: 'active_task_tab', value: '$workTab');
    }
  }

  void openHr() {
    Navigator.pop(context);
    Navigator.push<void>(
      context,
      MaterialPageRoute(builder: (_) => const HrSelfServiceScreen()),
    );
  }

  Future<void> signOut() async {
    Navigator.pop(context);
    await context.read<AuthController>().logout();
    if (mounted) {
      Navigator.pushNamedAndRemoveUntil(context, '/login', (_) => false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user ?? const {};
    return Scaffold(
      key: scaffoldKey,
      drawer: Drawer(
        child: SafeArea(
          child: Column(
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Color(0xFF102A5E), Color(0xFF1D4ED8)],
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.asset(
                        'assets/images/classic-app-icon.png',
                        width: 58,
                        height: 58,
                        fit: BoxFit.cover,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      user['name']?.toString() ?? 'Field team',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${user['employee_code'] ?? ''} · ${user['designation'] ?? 'Employee'}',
                      style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  children: [
                    _MenuItem(
                      icon: LucideIcons.layoutDashboard,
                      label: 'Dashboard',
                      selected: index == 0,
                      onTap: () => navigate(0, closeDrawer: true),
                    ),
                    const _MenuLabel('WORK'),
                    _MenuItem(
                      icon: LucideIcons.briefcaseBusiness,
                      label: 'Service Jobs',
                      selected: index == 1 && taskTab == 0,
                      onTap: () => navigate(1, workTab: 0, closeDrawer: true),
                    ),
                    _MenuItem(
                      icon: LucideIcons.gitBranch,
                      label: 'Internal Workflow',
                      selected: index == 1 && taskTab == 1,
                      onTap: () => navigate(1, workTab: 1, closeDrawer: true),
                    ),
                    _MenuItem(
                      icon: LucideIcons.ticketCheck,
                      label: 'Customer Tickets',
                      selected: index == 1 && taskTab == 2,
                      onTap: () => navigate(1, workTab: 2, closeDrawer: true),
                    ),
                    _MenuItem(
                      icon: LucideIcons.circleCheckBig,
                      label: 'Closed Work',
                      selected: index == 1 && taskTab == 3,
                      onTap: () => navigate(1, workTab: 3, closeDrawer: true),
                    ),
                    const _MenuLabel('SELF SERVICE'),
                    _MenuItem(
                      icon: LucideIcons.calendarCheck,
                      label: 'Attendance',
                      selected: index == 2,
                      onTap: () => navigate(2, closeDrawer: true),
                    ),
                    _MenuItem(
                      icon: LucideIcons.walletCards,
                      label: 'My HR & Salary Slips',
                      onTap: openHr,
                    ),
                    _MenuItem(
                      icon: LucideIcons.bell,
                      label: 'Notifications',
                      selected: index == 3,
                      onTap: () => navigate(3, closeDrawer: true),
                    ),
                    _MenuItem(
                      icon: LucideIcons.userRound,
                      label: 'Profile',
                      selected: index == 4,
                      onTap: () => navigate(4, closeDrawer: true),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              _MenuItem(
                icon: LucideIcons.logOut,
                label: 'Sign out',
                color: AppColors.danger,
                onTap: signOut,
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
      body: IndexedStack(
        index: index,
        children: [
          HomeScreen(
            onNavigate: (value) => navigate(value),
            onOpenMenu: openMenu,
            refreshKey: homeRefresh,
          ),
          TasksScreen(initialTab: taskTab, onOpenMenu: openMenu),
          AttendanceScreen(onOpenMenu: openMenu),
          NotificationsScreen(onOpenMenu: openMenu),
          ProfileScreen(onOpenMenu: openMenu),
        ],
      ),
    );
  }
}

class _MenuLabel extends StatelessWidget {
  const _MenuLabel(this.value);

  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(20, 18, 20, 7),
    child: Text(
      value,
      style: const TextStyle(
        color: AppColors.muted,
        fontSize: 10,
        fontWeight: FontWeight.w800,
        letterSpacing: 1,
      ),
    ),
  );
}

class _MenuItem extends StatelessWidget {
  const _MenuItem({
    required this.icon,
    required this.label,
    required this.onTap,
    this.selected = false,
    this.color,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool selected;
  final Color? color;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
    child: ListTile(
      selected: selected,
      selectedColor: AppColors.primary,
      selectedTileColor: const Color(0xFFEFF6FF),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      leading: Icon(icon, color: color),
      title: Text(
        label,
        style: TextStyle(fontWeight: FontWeight.w700, color: color),
      ),
      trailing: selected
          ? const Icon(LucideIcons.chevronRight, size: 17)
          : null,
      onTap: onTap,
    ),
  );
}
