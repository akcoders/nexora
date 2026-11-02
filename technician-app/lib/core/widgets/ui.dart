import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../i18n/t.dart';
import '../theme/app_theme.dart';

/// Semantic colour pairs used by pills, icon tiles and KPI cards.
enum Tone { primary, success, warning, danger, info, purple, orange, slate }

extension ToneColors on Tone {
  Color get color => switch (this) {
    Tone.primary => AppColors.primary,
    Tone.success => AppColors.success,
    Tone.warning => AppColors.warning,
    Tone.danger => AppColors.danger,
    Tone.info => AppColors.info,
    Tone.purple => AppColors.purple,
    Tone.orange => AppColors.orange,
    Tone.slate => AppColors.slate,
  };

  Color get soft => switch (this) {
    Tone.primary => AppColors.primarySoft,
    Tone.success => AppColors.successSoft,
    Tone.warning => AppColors.warningSoft,
    Tone.danger => AppColors.dangerSoft,
    Tone.info => AppColors.infoSoft,
    Tone.purple => AppColors.purpleSoft,
    Tone.orange => AppColors.orangeSoft,
    Tone.slate => AppColors.slateSoft,
  };
}

/// Maps the tone names the backend sends (`success`, `secondary`, ...) to a [Tone].
Tone toneFromName(String? name) => switch (name) {
  'primary' => Tone.primary,
  'success' => Tone.success,
  'warning' => Tone.warning,
  'danger' => Tone.danger,
  'info' => Tone.info,
  'purple' => Tone.purple,
  'orange' => Tone.orange,
  _ => Tone.slate,
};

/// First readable message from a Laravel error response.
String apiErrorMessage(
  Object error, [
  String fallback = 'Something went wrong. Please try again.',
]) {
  if (error is DioException) {
    final data = error.response?.data;
    if (data is Map && data['errors'] is Map) {
      final errors = data['errors'] as Map;
      if (errors.isNotEmpty) {
        final messages = errors.values.first;
        if (messages is List && messages.isNotEmpty) {
          return messages.first.toString();
        }
      }
    }
    if (data is Map && data['message'] != null) {
      return data['message'].toString();
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout) {
      return 'No connection. Check your internet and try again.';
    }
    return fallback;
  }
  final text = error.toString();
  return text.startsWith('Exception: ') ? text.substring(11) : text;
}

void showAppSnack(BuildContext context, String message, {Tone? tone}) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Row(
          children: [
            if (tone != null) ...[
              Icon(
                tone == Tone.success
                    ? LucideIcons.circleCheck
                    : tone == Tone.danger
                    ? LucideIcons.circleAlert
                    : LucideIcons.info,
                color: Colors.white,
                size: 18,
              ),
              const SizedBox(width: 10),
            ],
            Expanded(child: T(message)),
          ],
        ),
        backgroundColor: switch (tone) {
          Tone.success => AppColors.success,
          Tone.danger => AppColors.danger,
          _ => AppColors.heading,
        },
      ),
    );
}

/// Small uppercase status label.
class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.tone = Tone.slate, this.icon});

  final String text;
  final Tone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
    decoration: BoxDecoration(
      color: tone.soft,
      borderRadius: BorderRadius.circular(4),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[
          Icon(icon, size: 11, color: tone.color),
          const SizedBox(width: 4),
        ],
        Flexible(
          child: T(
            text.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: tone.color,
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: .4,
            ),
          ),
        ),
      ],
    ),
  );
}

/// Icon inside a soft rounded square.
class IconTile extends StatelessWidget {
  const IconTile(
    this.icon, {
    super.key,
    this.tone = Tone.primary,
    this.size = 40,
  });

  final IconData icon;
  final Tone tone;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: tone.soft,
      borderRadius: BorderRadius.circular(AppRadius.md),
    ),
    child: Icon(icon, color: tone.color, size: size * .48),
  );
}

/// Initials avatar with a stable tint per name.
class AppAvatar extends StatelessWidget {
  const AppAvatar({
    super.key,
    required this.name,
    this.size = 40,
    this.dark = false,
  });

  final String name;
  final double size;
  final bool dark;

  static String initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty);
    final value = parts.take(2).map((part) => part[0]).join().toUpperCase();
    return value.isEmpty ? '?' : value;
  }

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: dark
          ? AppColors.navyActive.withValues(alpha: .18)
          : AppColors.primarySoft,
      shape: BoxShape.circle,
      border: Border.all(
        color: dark
            ? AppColors.navyActive.withValues(alpha: .45)
            : const Color(0xFFCFE0FA),
      ),
    ),
    child: Text(
      initials(name),
      style: TextStyle(
        color: dark ? Colors.white : AppColors.primary,
        fontSize: size * .36,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

/// Heading above a group of cards, with an optional text action.
class SectionLabel extends StatelessWidget {
  const SectionLabel(
    this.title, {
    super.key,
    this.action,
    this.onAction,
    this.padding,
  });

  final String title;
  final String? action;
  final VoidCallback? onAction;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) => Padding(
    padding: padding ?? const EdgeInsets.only(top: 22, bottom: 10),
    child: Row(
      children: [
        Expanded(
          child: T(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: AppColors.heading,
            ),
          ),
        ),
        if (action != null)
          InkWell(
            onTap: onAction,
            borderRadius: BorderRadius.circular(AppRadius.sm),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  T(
                    action!,
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const Icon(
                    LucideIcons.chevronRight,
                    size: 16,
                    color: AppColors.primary,
                  ),
                ],
              ),
            ),
          ),
      ],
    ),
  );
}

/// Card with an optional header row (icon, title, trailing) above its body.
class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    this.title,
    this.subtitle,
    this.icon,
    this.trailing,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.color,
  });

  final String? title;
  final String? subtitle;
  final IconData? icon;
  final Widget? trailing;
  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color? color;

  @override
  Widget build(BuildContext context) => Card(
    color: color,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (title != null) ...[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 10, 12),
            child: Row(
              children: [
                if (icon != null) ...[
                  Icon(icon, size: 18, color: AppColors.primary),
                  const SizedBox(width: 10),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      T(
                        title!,
                        style: const TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.heading,
                        ),
                      ),
                      if (subtitle != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: T(
                            subtitle!,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.muted,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                ?trailing,
              ],
            ),
          ),
          const Divider(),
        ],
        Padding(padding: padding, child: child),
      ],
    ),
  );
}

/// Label / value row used inside detail cards.
class InfoRow extends StatelessWidget {
  const InfoRow({
    super.key,
    required this.label,
    required this.value,
    this.icon,
    this.last = false,
    this.valueColor,
    this.stacked = false,
  });

  final String label;
  final String value;
  final IconData? icon;
  final bool last;
  final Color? valueColor;

  /// Shows the value under the label; used for long text like addresses.
  final bool stacked;

  @override
  Widget build(BuildContext context) {
    final valueText = Text(
      value.trim().isEmpty ? '—' : value,
      textAlign: stacked ? TextAlign.start : TextAlign.end,
      style: TextStyle(
        fontWeight: FontWeight.w600,
        fontSize: 13.5,
        color: valueColor ?? AppColors.heading,
        height: 1.4,
      ),
    );
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 11),
      decoration: BoxDecoration(
        border: last
            ? null
            : const Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 17, color: AppColors.muted),
            const SizedBox(width: 10),
          ],
          if (stacked)
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  T(
                    label,
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 3),
                  valueText,
                ],
              ),
            )
          else ...[
            Expanded(
              flex: 4,
              child: T(
                label,
                style: const TextStyle(color: AppColors.muted, fontSize: 13),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(flex: 6, child: valueText),
          ],
        ],
      ),
    );
  }
}

/// Compact metric card: icon, value and label.
class KpiTile extends StatelessWidget {
  const KpiTile({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.tone = Tone.primary,
    this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final Tone tone;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          border: Border(left: BorderSide(color: tone.color, width: 3)),
        ),
        padding: const EdgeInsets.fromLTRB(12, 12, 10, 12),
        child: Row(
          children: [
            IconTile(icon, tone: tone, size: 36),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    value,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      color: AppColors.heading,
                      height: 1.1,
                    ),
                  ),
                  const SizedBox(height: 2),
                  T(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 11.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Centered empty / error message. Scrollable so it works inside RefreshIndicator.
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.actionLabel,
    this.actionIcon,
    this.onAction,
    this.compact = false,
  });

  final IconData icon;
  final String title;
  final String? message;
  final String? actionLabel;
  final IconData? actionIcon;
  final VoidCallback? onAction;

  /// Renders inline (inside a card) instead of filling the screen.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final content = Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: compact ? 48 : 64,
          height: compact ? 48 : 64,
          decoration: const BoxDecoration(
            color: AppColors.slateSoft,
            shape: BoxShape.circle,
          ),
          child: Icon(icon, size: compact ? 22 : 28, color: AppColors.slate),
        ),
        SizedBox(height: compact ? 10 : 16),
        T(
          title,
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: compact ? 14 : 16,
            fontWeight: FontWeight.w700,
            color: AppColors.heading,
          ),
        ),
        if (message != null) ...[
          const SizedBox(height: 6),
          T(
            message!,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: AppColors.muted,
              height: 1.45,
              fontSize: 13,
            ),
          ),
        ],
        if (actionLabel != null && onAction != null) ...[
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onAction,
            icon: Icon(actionIcon ?? LucideIcons.refreshCw, size: 17),
            label: T(actionLabel!),
          ),
        ],
      ],
    );
    if (compact) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 12),
        child: content,
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight * .8),
            child: Center(
              child: Padding(padding: const EdgeInsets.all(32), child: content),
            ),
          ),
        ],
      ),
    );
  }
}

/// Inline coloured message box.
class NoticeBox extends StatelessWidget {
  const NoticeBox(
    this.message, {
    super.key,
    this.tone = Tone.danger,
    this.icon,
  });

  final String message;
  final Tone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: tone.soft,
      borderRadius: BorderRadius.circular(AppRadius.md),
      border: Border.all(color: tone.color.withValues(alpha: .25)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          icon ??
              (tone == Tone.danger
                  ? LucideIcons.circleAlert
                  : tone == Tone.success
                  ? LucideIcons.circleCheck
                  : LucideIcons.info),
          size: 18,
          color: tone.color,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: T(
            message,
            style: TextStyle(
              color: tone.color,
              fontSize: 13,
              height: 1.45,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    ),
  );
}

/// Sticky footer for the main action of a screen.
class BottomActionBar extends StatelessWidget {
  const BottomActionBar({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    decoration: const BoxDecoration(
      color: Colors.white,
      border: Border(top: BorderSide(color: AppColors.border)),
    ),
    child: SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: child,
      ),
    ),
  );
}

/// Filled button that swaps its icon for a spinner while [busy].
class BusyButton extends StatelessWidget {
  const BusyButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.busy = false,
    this.busyLabel = 'Please wait…',
    this.color,
    this.expand = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool busy;
  final String busyLabel;
  final Color? color;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final button = FilledButton.icon(
      onPressed: busy ? null : onPressed,
      style: color == null
          ? null
          : FilledButton.styleFrom(backgroundColor: color),
      icon: busy
          ? const SizedBox.square(
              dimension: 18,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: AppColors.subtle,
              ),
            )
          : Icon(icon ?? LucideIcons.check, size: 18),
      label: T(busy ? busyLabel : label),
    );
    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}

/// Horizontal stage tracker (done stages are green, the current one blue).
class StageTracker extends StatelessWidget {
  const StageTracker({
    super.key,
    required this.stages,
    required this.current,
    this.allDone = false,
  });

  final List<String> stages;
  final int current;
  final bool allDone;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(8, 14, 8, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: List.generate(stages.length, (index) {
          final done = allDone || index < current;
          final active = !allDone && index == current;
          final color = done
              ? AppColors.success
              : active
              ? AppColors.primary
              : AppColors.borderStrong;
          return Expanded(
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 2,
                        color: index == 0
                            ? Colors.transparent
                            : (done || active
                                  ? (done
                                        ? AppColors.success
                                        : AppColors.primary)
                                  : AppColors.border),
                      ),
                    ),
                    Container(
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        color: done || active ? color : Colors.white,
                        border: Border.all(color: color, width: 2),
                        shape: BoxShape.circle,
                      ),
                      child: done
                          ? const Icon(
                              LucideIcons.check,
                              size: 13,
                              color: Colors.white,
                            )
                          : Center(
                              child: Text(
                                '${index + 1}',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                  color: active
                                      ? Colors.white
                                      : AppColors.subtle,
                                ),
                              ),
                            ),
                    ),
                    Expanded(
                      child: Container(
                        height: 2,
                        color: index == stages.length - 1
                            ? Colors.transparent
                            : (done ? AppColors.success : AppColors.border),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                T(
                  stages[index],
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: active
                        ? AppColors.primary
                        : (done ? AppColors.success : AppColors.muted),
                    fontSize: 10,
                    fontWeight: active ? FontWeight.w700 : FontWeight.w600,
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    ),
  );
}

/// AppBar leading button that opens the side menu.
class MenuButton extends StatelessWidget {
  const MenuButton({super.key, required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => IconButton(
    tooltip: 'Menu',
    onPressed: onPressed,
    icon: const Icon(LucideIcons.alignLeft),
  );
}

/// Centered spinner.
class LoadingView extends StatelessWidget {
  const LoadingView({super.key, this.message});

  final String? message;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const SizedBox.square(
          dimension: 28,
          child: CircularProgressIndicator(strokeWidth: 2.6),
        ),
        if (message != null) ...[
          const SizedBox(height: 14),
          T(
            message!,
            style: const TextStyle(color: AppColors.muted, fontSize: 13),
          ),
        ],
      ],
    ),
  );
}

/// Title + optional caption for sheets and dialogs built by hand.
class SheetHeader extends StatelessWidget {
  const SheetHeader({super.key, required this.title, this.subtitle, this.icon});

  final String title;
  final String? subtitle;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (icon != null) ...[
          IconTile(icon!, size: 38),
          const SizedBox(width: 12),
        ],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              T(
                title,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                  color: AppColors.heading,
                ),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 3),
                T(
                  subtitle!,
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: AppColors.muted,
                    height: 1.4,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    ),
  );
}
