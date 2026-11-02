import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_info.dart';
import '../../core/config/env.dart';
import '../../core/i18n/app_language.dart';
import '../../core/i18n/language_picker.dart';
import '../../core/i18n/t.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import '../auth/auth_controller.dart';
import '../auth/sign_out.dart';
import '../hr/hr_self_service_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({
    super.key,
    required this.onOpenMenu,
    required this.onOpenHr,
  });

  final VoidCallback onOpenMenu;
  final Future<void> Function(int tab) onOpenHr;

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user ?? const {};
    final language = context.watch<AppLanguageController>();
    final profile = user['employee_profile'] is Map
        ? Map<String, dynamic>.from(user['employee_profile'])
        : const {};
    final joined = DateTime.tryParse(
      profile['date_of_joining']?.toString() ?? '',
    );
    final name = user['name']?.toString() ?? 'Technician';
    final roles = List<dynamic>.from(
      user['roles'] ?? [],
    ).map((role) => role['name']).whereType<String>().join(', ');

    return Scaffold(
      appBar: AppBar(
        leading: MenuButton(onPressed: onOpenMenu),
        title: const T('My profile'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          Card(
            child: Column(
              children: [
                SizedBox(
                  height: 104,
                  child: Stack(
                    children: [
                      Container(
                        height: 64,
                        decoration: const BoxDecoration(
                          gradient: LinearGradient(
                            colors: [AppColors.navy, Color(0xFF1F3A6B)],
                          ),
                        ),
                      ),
                      Align(
                        alignment: Alignment.bottomCenter,
                        child: Container(
                          padding: const EdgeInsets.all(3),
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                          child: AppAvatar(name: name, size: 68),
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 18),
                  child: Column(
                    children: [
                      Text(
                        name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: AppColors.heading,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        user['designation']?.toString() ?? 'Employee',
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 13,
                        ),
                      ),
                      if (user['employee_code'] != null) ...[
                        const SizedBox(height: 8),
                        Pill(
                          user['employee_code'].toString(),
                          tone: Tone.primary,
                          icon: LucideIcons.idCard,
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SectionCard(
            title: 'Employee details',
            icon: LucideIcons.userRound,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Column(
              children: [
                InfoRow(label: 'Email', value: user['email']?.toString() ?? ''),
                InfoRow(label: 'Phone', value: user['phone']?.toString() ?? ''),
                InfoRow(
                  label: 'Department',
                  value: user['department']?.toString() ?? '',
                ),
                InfoRow(label: 'Role', value: roles),
                InfoRow(
                  label: 'Joining date',
                  value: joined == null
                      ? ''
                      : DateFormat('d MMM yyyy').format(joined),
                  last: true,
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SectionCard(
            title: 'HR & payroll',
            icon: LucideIcons.briefcaseBusiness,
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Column(
              children: [
                _Tile(
                  icon: LucideIcons.idCard,
                  title: 'HR overview & leave balance',
                  onTap: () => onOpenHr(HrTab.overview),
                ),
                _Tile(
                  icon: LucideIcons.calendarPlus,
                  title: 'Leave requests',
                  onTap: () => onOpenHr(HrTab.leave),
                ),
                _Tile(
                  icon: LucideIcons.receiptIndianRupee,
                  title: 'Expense vouchers',
                  onTap: () => onOpenHr(HrTab.vouchers),
                ),
                _Tile(
                  icon: LucideIcons.walletCards,
                  title: 'Salary slips',
                  onTap: () => onOpenHr(HrTab.payslips),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SectionCard(
            title: 'App settings',
            icon: LucideIcons.settings,
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Column(
              children: [
                _Tile(
                  icon: LucideIcons.languages,
                  title: 'App language',
                  trailing: language.current.name,
                  onTap: () => showLanguagePicker(context),
                ),
                const _Tile(
                  icon: LucideIcons.smartphone,
                  title: 'App version',
                  trailing: AppInfo.version,
                ),
                _Tile(
                  icon: LucideIcons.server,
                  title: 'Server',
                  trailing: Uri.tryParse(Env.baseUrl)?.host ?? Env.baseUrl,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: () => confirmAndSignOut(context),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.danger,
              side: const BorderSide(color: Color(0xFFF5C2C2)),
              minimumSize: const Size.fromHeight(48),
            ),
            icon: const Icon(LucideIcons.logOut, size: 18),
            label: const T('Sign out'),
          ),
        ],
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({
    required this.icon,
    required this.title,
    this.trailing,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => ListTile(
    onTap: onTap,
    dense: true,
    visualDensity: const VisualDensity(vertical: -1),
    leading: Icon(icon, size: 19, color: AppColors.primary),
    title: T(
      title,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w500,
        color: AppColors.ink,
      ),
    ),
    trailing: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (trailing != null)
          Text(
            trailing!,
            style: const TextStyle(color: AppColors.muted, fontSize: 13),
          ),
        if (onTap != null) ...[
          const SizedBox(width: 4),
          const Icon(
            LucideIcons.chevronRight,
            size: 17,
            color: AppColors.subtle,
          ),
        ],
      ],
    ),
  );
}
