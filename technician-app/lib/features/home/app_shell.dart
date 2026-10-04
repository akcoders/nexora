import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../attendance/attendance_screen.dart';
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
  int index = 0;
  int homeRefresh = 0;

  void navigate(int value) {
    setState(() {
      if (value == 0 && index != 0) homeRefresh++;
      index = value;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: index,
        children: [
          HomeScreen(onNavigate: navigate, refreshKey: homeRefresh),
          const TasksScreen(),
          const AttendanceScreen(),
          const NotificationsScreen(),
          const ProfileScreen(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: navigate,
        destinations: const [
          NavigationDestination(
            icon: Icon(LucideIcons.home),
            selectedIcon: Icon(LucideIcons.home),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(LucideIcons.clipboardList),
            selectedIcon: Icon(LucideIcons.clipboardList),
            label: 'Tasks',
          ),
          NavigationDestination(
            icon: Icon(LucideIcons.calendarCheck),
            selectedIcon: Icon(LucideIcons.calendarCheck),
            label: 'Attendance',
          ),
          NavigationDestination(
            icon: Icon(LucideIcons.bell),
            selectedIcon: Icon(LucideIcons.bell),
            label: 'Alerts',
          ),
          NavigationDestination(
            icon: Icon(LucideIcons.user),
            selectedIcon: Icon(LucideIcons.user),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}
