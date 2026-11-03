import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../core/i18n/t.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'library_models.dart';

/// Opens the filter sheet. Resolves to the new query, or null when dismissed.
Future<LibraryQuery?> showLibraryFilterSheet(
  BuildContext context, {
  required LibraryFilterOptions options,
  required LibraryQuery query,
}) => showModalBottomSheet<LibraryQuery>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  // Lift the sheet above the keyboard while a list is being searched.
  builder: (sheetContext) => Padding(
    padding: EdgeInsets.only(
      bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
    ),
    child: FractionallySizedBox(
      heightFactor: .9,
      child: LibraryFilterSheet(options: options, query: query),
    ),
  ),
);

class LibraryFilterSheet extends StatefulWidget {
  const LibraryFilterSheet({
    super.key,
    required this.options,
    required this.query,
  });

  final LibraryFilterOptions options;
  final LibraryQuery query;

  @override
  State<LibraryFilterSheet> createState() => _LibraryFilterSheetState();
}

class _LibraryFilterSheetState extends State<LibraryFilterSheet> {
  late String? content = widget.query.content;
  late String? deviceType = widget.query.deviceType;
  late String? manufacturer = widget.query.manufacturer;

  int get selectedCount =>
      [content, deviceType, manufacturer].where((v) => v != null).length;

  void apply() => Navigator.pop(
    context,
    widget.query.copyWith(
      content: () => content,
      deviceType: () => deviceType,
      manufacturer: () => manufacturer,
    ),
  );

  void reset() => setState(() {
    content = null;
    deviceType = null;
    manufacturer = null;
  });

  @override
  Widget build(BuildContext context) {
    final options = widget.options;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 20),
          child: SheetHeader(
            icon: LucideIcons.slidersHorizontal,
            title: 'Filter guides',
            subtitle: 'Find guides for the equipment in front of you.',
          ),
        ),
        const Divider(),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
            children: [
              _FilterGroup(
                title: 'Content type',
                icon: LucideIcons.layers,
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _OptionChip(
                      label: 'Any',
                      selected: content == null,
                      translate: true,
                      onTap: () => setState(() => content = null),
                    ),
                    for (final type in options.contentTypes)
                      _OptionChip(
                        label: type.label,
                        icon: type.iconData,
                        translate: true,
                        selected: content == type.key,
                        onTap: () => setState(
                          () => content = content == type.key ? null : type.key,
                        ),
                      ),
                  ],
                ),
              ),
              _SearchableOptions(
                title: 'Device type',
                icon: LucideIcons.airVent,
                searchHint: 'Search device types',
                options: options.deviceTypes,
                selected: deviceType,
                onChanged: (code) => setState(() => deviceType = code),
              ),
              _SearchableOptions(
                title: 'Manufacturer',
                icon: LucideIcons.factory,
                searchHint: 'Search brands',
                options: options.manufacturers,
                selected: manufacturer,
                onChanged: (code) => setState(() => manufacturer = code),
              ),
            ],
          ),
        ),
        BottomActionBar(
          child: Row(
            children: [
              Expanded(
                flex: 2,
                child: OutlinedButton.icon(
                  onPressed: selectedCount == 0 ? null : reset,
                  icon: const Icon(LucideIcons.rotateCcw, size: 17),
                  label: const T('Reset'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 3,
                child: FilledButton.icon(
                  onPressed: apply,
                  icon: const Icon(LucideIcons.check, size: 18),
                  label: T(
                    selectedCount == 0
                        ? 'Show guides'
                        : 'Apply $selectedCount filter${selectedCount == 1 ? '' : 's'}',
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _FilterGroup extends StatelessWidget {
  const _FilterGroup({
    required this.title,
    required this.icon,
    required this.child,
    this.trailing,
  });

  final String title;
  final IconData icon;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 22),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Icon(icon, size: 16, color: AppColors.primary),
            const SizedBox(width: 8),
            Expanded(
              child: T(
                title,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.heading,
                ),
              ),
            ),
            ?trailing,
          ],
        ),
        const SizedBox(height: 10),
        child,
      ],
    ),
  );
}

/// Chip list with a search box once the list is long enough to need one.
class _SearchableOptions extends StatefulWidget {
  const _SearchableOptions({
    required this.title,
    required this.icon,
    required this.searchHint,
    required this.options,
    required this.selected,
    required this.onChanged,
  });

  final String title;
  final IconData icon;
  final String searchHint;
  final List<LibraryOption> options;
  final String? selected;
  final ValueChanged<String?> onChanged;

  @override
  State<_SearchableOptions> createState() => _SearchableOptionsState();
}

class _SearchableOptionsState extends State<_SearchableOptions> {
  static const searchThreshold = 8;
  final controller = TextEditingController();
  String term = '';

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final needle = term.trim().toLowerCase();
    final visible = needle.isEmpty
        ? widget.options
        : widget.options
              .where((option) => option.label.toLowerCase().contains(needle))
              .toList();
    final selectedLabel = widget.options
        .where((option) => option.code == widget.selected)
        .map((option) => option.label)
        .firstOrNull;
    return _FilterGroup(
      title: widget.title,
      icon: widget.icon,
      trailing: selectedLabel == null
          ? null
          : Pill(selectedLabel, tone: Tone.primary),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (widget.options.length > searchThreshold) ...[
            TextField(
              controller: controller,
              onChanged: (value) => setState(() => term = value),
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: context.tr(widget.searchHint),
                prefixIcon: const Icon(LucideIcons.search, size: 18),
                suffixIcon: term.isEmpty
                    ? null
                    : IconButton(
                        tooltip: 'Clear',
                        onPressed: () {
                          controller.clear();
                          setState(() => term = '');
                        },
                        icon: const Icon(LucideIcons.x, size: 17),
                      ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 11,
                ),
              ),
            ),
            const SizedBox(height: 10),
          ],
          if (widget.options.isEmpty)
            const T(
              'No options have been added yet.',
              style: TextStyle(color: AppColors.muted, fontSize: 13),
            )
          else if (visible.isEmpty)
            T(
              'Nothing matches "$term".',
              style: const TextStyle(color: AppColors.muted, fontSize: 13),
            )
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (needle.isEmpty)
                  _OptionChip(
                    label: 'Any',
                    translate: true,
                    selected: widget.selected == null,
                    onTap: () => widget.onChanged(null),
                  ),
                for (final option in visible)
                  _OptionChip(
                    label: option.label,
                    selected: widget.selected == option.code,
                    onTap: () => widget.onChanged(
                      widget.selected == option.code ? null : option.code,
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

class _OptionChip extends StatelessWidget {
  const _OptionChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.icon,
    this.translate = false,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final IconData? icon;
  final bool translate;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primary : AppColors.ink;
    final style = TextStyle(
      fontSize: 13,
      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
      color: color,
    );
    return Material(
      color: selected ? AppColors.primarySoft : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        side: BorderSide(
          color: selected ? AppColors.primary : AppColors.borderStrong,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Container(
          constraints: const BoxConstraints(minHeight: 40),
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (selected) ...[
                const Icon(
                  LucideIcons.check,
                  size: 14,
                  color: AppColors.primary,
                ),
                const SizedBox(width: 5),
              ] else if (icon != null) ...[
                Icon(icon, size: 14, color: AppColors.muted),
                const SizedBox(width: 5),
              ],
              Flexible(
                child: translate
                    ? T(label, style: style)
                    : Text(label, style: style),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
