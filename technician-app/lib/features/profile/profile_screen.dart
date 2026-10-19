import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/app_language.dart';
import '../../core/i18n/t.dart';
import '../../core/theme/app_theme.dart';
import '../auth/auth_controller.dart';
import '../hr/hr_self_service_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key, required this.onOpenMenu});

  final VoidCallback onOpenMenu;
  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final user = auth.user ?? {};
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: onOpenMenu,
          icon: const Icon(LucideIcons.menu),
        ),
        title: const T('Profile'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(22),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 34,
                    backgroundColor: const Color(0xFFDBEAFE),
                    child: Text(
                      (user['name']?.toString() ?? 'T')
                          .substring(0, 1)
                          .toUpperCase(),
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user['name']?.toString() ?? 'Technician',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          user['designation']?.toString() ?? 'HVAC Technician',
                          style: const TextStyle(color: AppColors.muted),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          user['employee_code']?.toString() ?? '',
                          style: const TextStyle(
                            color: AppColors.primary,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 18),
          Card(
            child: Column(
              children: [
                _ProfileTile(
                  icon: LucideIcons.briefcaseBusiness,
                  title: 'My HR, leave & payroll',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const HrSelfServiceScreen(),
                    ),
                  ),
                ),
                const Divider(height: 1, indent: 62),
                _ProfileTile(
                  icon: LucideIcons.languages,
                  title: 'App language',
                  trailing: context.watch<AppLanguageController>().current.name,
                  onTap: () => _pickLanguage(context),
                ),
                const Divider(height: 1, indent: 62),
                const _ProfileTile(
                  icon: LucideIcons.bell,
                  title: 'Notification preferences',
                ),
                const Divider(height: 1, indent: 62),
                const _ProfileTile(
                  icon: Icons.info_outline,
                  title: 'App version',
                  trailing: '1.4.0',
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          OutlinedButton.icon(
            onPressed: () async {
              await auth.logout();
              if (context.mounted) {
                Navigator.pushNamedAndRemoveUntil(
                  context,
                  '/login',
                  (_) => false,
                );
              }
            },
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.danger,
              side: const BorderSide(color: Color(0xFFFECACA)),
              minimumSize: const Size.fromHeight(52),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            icon: const Icon(LucideIcons.logOut),
            label: const T('Sign out'),
          ),
        ],
      ),
    );
  }
}

Future<void> _pickLanguage(BuildContext context) async {
  final language = context.read<AppLanguageController>();
  final chosen = await showModalBottomSheet<AppLanguage>(
    context: context,
    showDragHandle: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (sheetContext) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(22, 4, 22, 6),
            child: Row(
              children: [
                Icon(LucideIcons.languages, color: AppColors.primary),
                SizedBox(width: 10),
                Expanded(
                  child: T(
                    'Choose app language',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
          ),
          const Padding(
            padding: EdgeInsets.fromLTRB(22, 0, 22, 10),
            child: T(
              'Only the languages set on your employee profile are available here.',
              style: TextStyle(color: AppColors.muted, fontSize: 12),
            ),
          ),
          Flexible(
            child: ListView(
              shrinkWrap: true,
              children: [
                for (final option in language.known)
                  ListTile(
                    leading: Container(
                      width: 42,
                      height: 30,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: const Color(0xFFEDE9FE),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        option.code.toUpperCase(),
                        style: const TextStyle(
                          color: Color(0xFF6D28D9),
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                        ),
                      ),
                    ),
                    title: Text(
                      option.name,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    trailing: option.code == language.current.code
                        ? const Icon(
                            LucideIcons.check,
                            color: AppColors.success,
                          )
                        : null,
                    onTap: () => Navigator.pop(sheetContext, option),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    ),
  );
  if (chosen != null) {
    await language.select(chosen);
  }
}

class _ProfileTile extends StatelessWidget {
  const _ProfileTile({
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
    leading: Icon(icon, color: AppColors.primary),
    title: T(title, style: const TextStyle(fontWeight: FontWeight.w600)),
    trailing: trailing == null
        ? const Icon(Icons.chevron_right)
        : Text(trailing!, style: const TextStyle(color: AppColors.muted)),
  );
}
