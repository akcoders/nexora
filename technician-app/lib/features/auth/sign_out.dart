import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/theme/app_theme.dart';
import 'auth_controller.dart';

/// Asks for confirmation, signs out and returns to the login screen.
Future<void> confirmAndSignOut(BuildContext context) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: const Icon(LucideIcons.logOut, color: AppColors.danger),
      title: const T('Sign out?'),
      content: const T(
        'You will need your email and password to sign in again.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(dialogContext, false),
          child: const T('Cancel'),
        ),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: AppColors.danger),
          onPressed: () => Navigator.pop(dialogContext, true),
          child: const T('Sign out'),
        ),
      ],
    ),
  );
  if (confirmed != true || !context.mounted) return;
  await context.read<AuthController>().logout();
  if (context.mounted) {
    Navigator.of(context).pushNamedAndRemoveUntil('/login', (_) => false);
  }
}
