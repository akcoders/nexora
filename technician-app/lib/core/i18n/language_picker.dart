import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../theme/app_theme.dart';
import '../widgets/ui.dart';
import 'app_language.dart';

/// Bottom sheet listing the languages recorded on the employee master.
Future<void> showLanguagePicker(BuildContext context) async {
  final language = context.read<AppLanguageController>();
  final chosen = await showModalBottomSheet<AppLanguage>(
    context: context,
    builder: (sheetContext) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SheetHeader(
              icon: LucideIcons.languages,
              title: 'Choose app language',
              subtitle:
                  'Only the languages set on your employee profile are available here.',
            ),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                children: [
                  for (final option in language.known)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: _LanguageOption(
                        option: option,
                        selected: option.code == language.current.code,
                        onTap: () => Navigator.pop(sheetContext, option),
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
  if (chosen != null) await language.select(chosen);
}

class _LanguageOption extends StatelessWidget {
  const _LanguageOption({
    required this.option,
    required this.selected,
    required this.onTap,
  });

  final AppLanguage option;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? AppColors.primarySoft : Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.md),
      side: BorderSide(color: selected ? AppColors.primary : AppColors.border),
    ),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 28,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: selected ? Colors.white : AppColors.slateSoft,
                borderRadius: BorderRadius.circular(AppRadius.sm),
              ),
              child: Text(
                option.code.toUpperCase(),
                style: TextStyle(
                  color: selected ? AppColors.primary : AppColors.slate,
                  fontWeight: FontWeight.w700,
                  fontSize: 11,
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                option.name,
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 14.5,
                ),
              ),
            ),
            Icon(
              selected ? LucideIcons.circleCheck : LucideIcons.circle,
              size: 20,
              color: selected ? AppColors.primary : AppColors.borderStrong,
            ),
          ],
        ),
      ),
    ),
  );
}
