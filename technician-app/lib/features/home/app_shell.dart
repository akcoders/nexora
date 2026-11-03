import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/app_language.dart';
import '../../core/i18n/language_picker.dart';
import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../attendance/attendance_screen.dart';
import '../auth/auth_controller.dart';
import '../auth/sign_out.dart';
import '../hr/hr_self_service_screen.dart';
import '../library/library_screen.dart';
import '../notifications/notifications_screen.dart';
import '../profile/profile_screen.dart';
import '../tasks/tasks_screen.dart';
import 'app_drawer.dart';
import 'home_screen.dart';

/// Bottom navigation pages.
class AppPage {
  static const home = 0;
  static const work = 1;
  static const attendance = 2;
  static const alerts = 3;
  static const profile = 4;
}

class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  final scaffoldKey = GlobalKey<ScaffoldState>();
  int index = AppPage.home;
  int workTab = WorkTab.jobs;
  int homeRefresh = 0;
  int unreadCount = 0;

  @override
  void initState() {
    super.initState();
    _restoreNavigation();
    refreshUnread();
  }

  Future<void> _restoreNavigation() async {
    final storage = context.read<FlutterSecureStorage>();
    final values = await Future.wait([
      storage.read(key: 'active_navigation_tab'),
      storage.read(key: 'active_work_tab'),
    ]);
    final restoredIndex = int.tryParse(values[0] ?? '');
    final restoredWorkTab = int.tryParse(values[1] ?? '');
    if (!mounted) return;
    setState(() {
      if (restoredIndex != null && restoredIndex >= 0 && restoredIndex < 5) {
        index = restoredIndex;
      }
      if (restoredWorkTab != null &&
          restoredWorkTab >= 0 &&
          restoredWorkTab < WorkTab.count) {
        workTab = restoredWorkTab;
      }
    });
  }

  Future<void> refreshUnread() async {
    try {
      final response = await context.read<ApiClient>().dio.get('notifications');
      final count = (response.data['unread_count'] as num?)?.toInt() ?? 0;
      if (mounted && count != unreadCount) setState(() => unreadCount = count);
    } catch (_) {
      // The badge is a convenience; the notifications page shows errors itself.
    }
  }

  void openMenu() => scaffoldKey.currentState?.openDrawer();

  void navigate(int value, {int? tab}) {
    setState(() {
      if (value == AppPage.home && index != AppPage.home) homeRefresh++;
      index = value;
      if (tab != null) workTab = tab;
    });
    final storage = context.read<FlutterSecureStorage>();
    storage.write(key: 'active_navigation_tab', value: '$value');
    if (tab != null) storage.write(key: 'active_work_tab', value: '$tab');
  }

  Future<void> openHr(int tab) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(builder: (_) => HrSelfServiceScreen(initialTab: tab)),
    );
  }

  Future<void> openLibrary() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(builder: (_) => const LibraryScreen()),
    );
  }

  MenuDestination get _selectedDestination => switch (index) {
    AppPage.work => switch (workTab) {
      WorkTab.complaints => MenuDestination.complaints,
      WorkTab.workflow => MenuDestination.workflow,
      WorkTab.completed => MenuDestination.completedWork,
      _ => MenuDestination.serviceJobs,
    },
    AppPage.attendance => MenuDestination.attendance,
    AppPage.alerts => MenuDestination.notifications,
    AppPage.profile => MenuDestination.profile,
    _ => MenuDestination.dashboard,
  };

  Future<void> onMenu(MenuDestination destination) async {
    Navigator.pop(context);
    switch (destination) {
      case MenuDestination.dashboard:
        navigate(AppPage.home);
      case MenuDestination.notifications:
        navigate(AppPage.alerts);
      case MenuDestination.serviceJobs:
        navigate(AppPage.work, tab: WorkTab.jobs);
      case MenuDestination.complaints:
        navigate(AppPage.work, tab: WorkTab.complaints);
      case MenuDestination.workflow:
        navigate(AppPage.work, tab: WorkTab.workflow);
      case MenuDestination.completedWork:
        navigate(AppPage.work, tab: WorkTab.completed);
      case MenuDestination.library:
        await openLibrary();
      case MenuDestination.attendance:
        navigate(AppPage.attendance);
      case MenuDestination.hrOverview:
        await openHr(HrTab.overview);
      case MenuDestination.leave:
        await openHr(HrTab.leave);
      case MenuDestination.vouchers:
        await openHr(HrTab.vouchers);
      case MenuDestination.payslips:
        await openHr(HrTab.payslips);
      case MenuDestination.holidays:
        await openHr(HrTab.holidays);
      case MenuDestination.profile:
        navigate(AppPage.profile);
      case MenuDestination.language:
        await showLanguagePicker(context);
      case MenuDestination.signOut:
        await confirmAndSignOut(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user ?? const {};
    final language = context.watch<AppLanguageController>();
    for (final label in const [
      'Home',
      'Work',
      'Attendance',
      'Alerts',
      'Profile',
    ]) {
      language.translate(label);
    }

    return PopScope(
      canPop: index == AppPage.home,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) navigate(AppPage.home);
      },
      child: Scaffold(
        key: scaffoldKey,
        drawer: AppDrawer(
          user: Map<String, dynamic>.from(user),
          selected: _selectedDestination,
          unreadCount: unreadCount,
          languageName: language.current.name,
          onSelect: onMenu,
        ),
        onDrawerChanged: (open) {
          if (open) refreshUnread();
        },
        bottomNavigationBar: DecoratedBox(
          decoration: const BoxDecoration(
            border: Border(top: BorderSide(color: Color(0xFFE1E6EE))),
          ),
          child: NavigationBar(
            selectedIndex: index,
            labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
            onDestinationSelected: (value) {
              navigate(value);
              if (value == AppPage.alerts) refreshUnread();
            },
            destinations: [
              NavigationDestination(
                icon: const Icon(LucideIcons.house),
                label: context.tr('Home'),
              ),
              NavigationDestination(
                icon: const Icon(LucideIcons.briefcaseBusiness),
                label: context.tr('Work'),
              ),
              NavigationDestination(
                icon: const Icon(LucideIcons.calendarCheck),
                label: context.tr('Attendance'),
              ),
              NavigationDestination(
                icon: Badge(
                  isLabelVisible: unreadCount > 0,
                  label: Text(unreadCount > 99 ? '99+' : '$unreadCount'),
                  child: const Icon(LucideIcons.bell),
                ),
                label: context.tr('Alerts'),
              ),
              NavigationDestination(
                icon: const Icon(LucideIcons.userRound),
                label: context.tr('Profile'),
              ),
            ],
          ),
        ),
        body: IndexedStack(
          index: index,
          children: [
            HomeScreen(
              onNavigate: navigate,
              onOpenMenu: openMenu,
              onOpenHr: openHr,
              onOpenLibrary: openLibrary,
              refreshKey: homeRefresh,
            ),
            TasksScreen(initialTab: workTab, onOpenMenu: openMenu),
            AttendanceScreen(onOpenMenu: openMenu),
            NotificationsScreen(
              onOpenMenu: openMenu,
              onUnreadChanged: (count) {
                if (mounted && count != unreadCount) {
                  setState(() => unreadCount = count);
                }
              },
            ),
            ProfileScreen(onOpenMenu: openMenu, onOpenHr: openHr),
          ],
        ),
      ),
    );
  }
}
