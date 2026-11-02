import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../core/config/app_info.dart';
import '../../core/i18n/t.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';

/// Destinations the side menu can open.
enum MenuDestination {
  dashboard,
  notifications,
  serviceJobs,
  complaints,
  workflow,
  completedWork,
  attendance,
  hrOverview,
  leave,
  vouchers,
  payslips,
  holidays,
  profile,
  language,
  signOut,
}

/// Classic navy side menu with every section of the app.
class AppDrawer extends StatelessWidget {
  const AppDrawer({
    super.key,
    required this.user,
    required this.selected,
    required this.unreadCount,
    required this.languageName,
    required this.onSelect,
  });

  final Map<String, dynamic> user;
  final MenuDestination selected;
  final int unreadCount;
  final String languageName;
  final ValueChanged<MenuDestination> onSelect;

  @override
  Widget build(BuildContext context) {
    final name = user['name']?.toString() ?? 'Field team';
    final subtitle = [user['employee_code'], user['designation'] ?? 'Employee']
        .where((value) => value != null && value.toString().isNotEmpty)
        .join(' · ');

    return Drawer(
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 16, 8, 14),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    child: Image.asset(
                      'assets/images/classic-app-icon.png',
                      width: 36,
                      height: 36,
                      fit: BoxFit.cover,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'NEXORA',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 2,
                          ),
                        ),
                        T(
                          'Field Service',
                          style: TextStyle(
                            color: AppColors.navyText,
                            fontSize: 11.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close menu',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(
                      LucideIcons.x,
                      color: AppColors.navyText,
                      size: 20,
                    ),
                  ),
                ],
              ),
            ),
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 14),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.navy2,
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(color: Colors.white.withValues(alpha: .06)),
              ),
              child: InkWell(
                onTap: () => onSelect(MenuDestination.profile),
                child: Row(
                  children: [
                    AppAvatar(name: name, size: 42, dark: true),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14.5,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            subtitle,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: AppColors.navyText,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(
                      LucideIcons.chevronRight,
                      color: AppColors.navyMuted,
                      size: 18,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 6),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(10, 4, 10, 12),
                children: [
                  const _Section('Main'),
                  _item(
                    MenuDestination.dashboard,
                    LucideIcons.layoutDashboard,
                    'Dashboard',
                  ),
                  _item(
                    MenuDestination.notifications,
                    LucideIcons.bell,
                    'Notifications',
                    badge: unreadCount,
                  ),
                  const _Section('Work'),
                  _item(
                    MenuDestination.serviceJobs,
                    LucideIcons.wrench,
                    'Service jobs',
                  ),
                  _item(
                    MenuDestination.complaints,
                    LucideIcons.clipboardList,
                    'Complaint visits',
                  ),
                  _item(
                    MenuDestination.workflow,
                    LucideIcons.gitBranch,
                    'Internal workflow',
                  ),
                  _item(
                    MenuDestination.completedWork,
                    LucideIcons.circleCheckBig,
                    'Completed work',
                  ),
                  const _Section('Attendance'),
                  _item(
                    MenuDestination.attendance,
                    LucideIcons.calendarCheck,
                    'My attendance',
                  ),
                  const _Section('HR & payroll'),
                  _item(
                    MenuDestination.hrOverview,
                    LucideIcons.idCard,
                    'HR overview',
                  ),
                  _item(
                    MenuDestination.leave,
                    LucideIcons.calendarPlus,
                    'Leave requests',
                  ),
                  _item(
                    MenuDestination.vouchers,
                    LucideIcons.receiptIndianRupee,
                    'Expense vouchers',
                  ),
                  _item(
                    MenuDestination.payslips,
                    LucideIcons.walletCards,
                    'Salary slips',
                  ),
                  _item(
                    MenuDestination.holidays,
                    LucideIcons.calendarDays,
                    'Holiday calendar',
                  ),
                  const _Section('Account'),
                  _item(
                    MenuDestination.profile,
                    LucideIcons.userRound,
                    'My profile',
                  ),
                  _item(
                    MenuDestination.language,
                    LucideIcons.languages,
                    'App language',
                    trailing: languageName,
                  ),
                  _item(
                    MenuDestination.signOut,
                    LucideIcons.logOut,
                    'Sign out',
                    danger: true,
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
              decoration: BoxDecoration(
                border: Border(
                  top: BorderSide(color: Colors.white.withValues(alpha: .07)),
                ),
              ),
              child: const Row(
                children: [
                  Icon(
                    LucideIcons.shieldCheck,
                    size: 15,
                    color: AppColors.navyMuted,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${AppInfo.name} · v${AppInfo.version}',
                      style: TextStyle(
                        color: AppColors.navyMuted,
                        fontSize: 11.5,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _item(
    MenuDestination destination,
    IconData icon,
    String label, {
    int badge = 0,
    String? trailing,
    bool danger = false,
  }) => _MenuItem(
    icon: icon,
    label: label,
    selected: destination == selected,
    badge: badge,
    trailing: trailing,
    danger: danger,
    onTap: () => onSelect(destination),
  );
}

class _Section extends StatelessWidget {
  const _Section(this.label);

  final String label;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(12, 16, 12, 6),
    child: T(
      label.toUpperCase(),
      style: const TextStyle(
        color: AppColors.navyMuted,
        fontSize: 10.5,
        fontWeight: FontWeight.w700,
        letterSpacing: 1.1,
      ),
    ),
  );
}

class _MenuItem extends StatelessWidget {
  const _MenuItem({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
    this.badge = 0,
    this.trailing,
    this.danger = false,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final int badge;
  final String? trailing;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final foreground = danger
        ? const Color(0xFFF87171)
        : selected
        ? Colors.white
        : AppColors.navyText;
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Material(
        color: selected
            ? AppColors.navyActive.withValues(alpha: .16)
            : Colors.transparent,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          splashColor: Colors.white.withValues(alpha: .06),
          highlightColor: Colors.white.withValues(alpha: .04),
          child: Container(
            height: 44,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.sm),
              border: Border(
                left: BorderSide(
                  color: selected ? AppColors.navyActive : Colors.transparent,
                  width: 3,
                ),
              ),
            ),
            child: Row(
              children: [
                Icon(
                  icon,
                  size: 19,
                  color: selected ? AppColors.navyActive : foreground,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: T(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: foreground,
                      fontSize: 14,
                      fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                    ),
                  ),
                ),
                if (badge > 0)
                  Container(
                    constraints: const BoxConstraints(minWidth: 22),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 6,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.danger,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      badge > 99 ? '99+' : '$badge',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                if (trailing != null)
                  Text(
                    trailing!,
                    style: const TextStyle(
                      color: AppColors.navyMuted,
                      fontSize: 12,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
