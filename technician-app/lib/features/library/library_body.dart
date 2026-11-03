import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:html/dom.dart' as dom;
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../core/config/env.dart';
import '../../core/theme/app_theme.dart';
import 'library_models.dart';
import 'library_widgets.dart';

/// Renders the sanitised guide HTML written in the web editor (Quill) the way
/// the web guide page shows it: top-level numbered lists become numbered step
/// circles (indented items become sub-steps such as 4.1), checklists become
/// ticked rows, quotes become tip boxes, code blocks wrap and can be copied,
/// and YouTube embeds become a thumbnail that opens the YouTube app.
class LibraryBody extends StatelessWidget {
  const LibraryBody({
    super.key,
    required this.html,
    this.onOpenUrl,
    this.textStyle = defaultTextStyle,
    this.nested = false,
  });

  final String html;

  /// Opens a link or video outside the app. Defaults to [openLibraryUrl].
  final void Function(BuildContext context, String url)? onOpenUrl;

  final TextStyle textStyle;

  /// True for the content of a step, checklist row or tip. Ordered lists
  /// there keep plain numbers instead of becoming step circles.
  final bool nested;

  static const defaultTextStyle = TextStyle(
    fontSize: 14.5,
    height: 1.6,
    color: AppColors.ink,
  );

  /// Origin of the API, used to resolve relative image and link URLs.
  static Uri? get baseUri {
    final uri = Uri.tryParse(Env.baseUrl);
    if (uri == null || !uri.hasScheme) return null;
    return Uri(scheme: uri.scheme, host: uri.host, port: uri.port, path: '/');
  }

  void _open(BuildContext context, String url) {
    final resolved = baseUri?.resolve(url).toString() ?? url;
    if (onOpenUrl != null) {
      onOpenUrl!(context, resolved);
    } else {
      openLibraryUrl(context, resolved);
    }
  }

  @override
  Widget build(BuildContext context) => HtmlWidget(
    html,
    baseUrl: baseUri,
    textStyle: textStyle,
    onTapUrl: (url) {
      _open(context, url);
      return true;
    },
    onTapImage: (image) {
      final source = image.sources.isEmpty ? null : image.sources.first.url;
      if (source != null) _open(context, source);
    },
    customStylesBuilder: styles,
    customWidgetBuilder: (element) => widgetFor(context, element),
  );

  /// Body for the inner HTML of a list item or quote.
  Widget _inner(String html, {TextStyle? style}) => LibraryBody(
    html: html,
    onOpenUrl: onOpenUrl,
    textStyle: style ?? textStyle,
    nested: true,
  );

  /// Indent level from Quill's `ql-indent-N` class (0 when not indented).
  static int indentOf(dom.Element element) {
    for (final name in element.classes) {
      final match = RegExp(r'^ql-indent-(\d+)$').firstMatch(name);
      if (match != null) return int.parse(match.group(1)!);
    }
    return 0;
  }

  /// HtmlWidget parses a fragment, so top-level blocks have no parent element
  /// (a full document would put them under `body`).
  static bool _isTopLevel(dom.Element element) {
    final parent = element.parent;
    return parent == null || parent.localName == 'body';
  }

  /// Space around a custom block: none above the first or below the last
  /// block, so the content sits flush with the card padding.
  static EdgeInsets _blockPadding(
    dom.Element element, {
    double top = 6,
    double bottom = 14,
  }) {
    final topLevel = _isTopLevel(element);
    return EdgeInsets.only(
      top: topLevel && element.previousElementSibling == null ? 0 : top,
      bottom: topLevel && element.nextElementSibling == null ? 0 : bottom,
    );
  }

  /// Typography for the editor's markup, matching the web article styles.
  static Map<String, String>? styles(dom.Element element) {
    final css = <String, String>{};
    for (final name in element.classes) {
      if (name.startsWith('ql-align-')) {
        css['text-align'] = name.substring('ql-align-'.length);
      }
      final indent = RegExp(r'^ql-indent-(\d+)$').firstMatch(name);
      if (indent != null) {
        css['padding-left'] = '${int.parse(indent.group(1)!) * 1.5}em';
      }
      switch (name) {
        case 'ql-size-small':
          css['font-size'] = '0.85em';
        case 'ql-size-large':
          css['font-size'] = '1.25em';
        case 'ql-size-huge':
          css['font-size'] = '1.55em';
      }
    }

    switch (element.localName) {
      case 'h1':
        css.addAll(_heading('21px'));
      case 'h2':
        css.addAll(_heading('18.5px'));
      case 'h3':
        css.addAll(_heading('16.5px'));
      case 'h4' || 'h5' || 'h6':
        css.addAll(_heading('15px'));
      case 'p':
        css['margin'] = '0 0 10px 0';
      case 'a':
        css['color'] = '#1F5FBF';
        css['font-weight'] = '600';
        css['text-decoration'] = 'none';
      case 'ul' || 'ol':
        css['margin'] = '2px 0 12px 0';
        css['padding-left'] = '22px';
        if (element.classes.contains('nx-checklist')) {
          css['list-style-type'] = 'none';
          css['padding-left'] = '0';
        }
      case 'li':
        css['margin-bottom'] = '5px';
      case 'code':
        css.addAll({
          'background-color': '#EEF1F5',
          'color': '#B91C1C',
          'font-family': 'monospace',
          'font-size': '0.88em',
        });
      case 'mark':
        css['background-color'] = '#FDF1DC';
      case 'hr':
        css.addAll({'margin': '14px 0', 'border-top': '1px solid #E1E6EE'});
      case 'table':
        css.addAll({
          'margin': '6px 0 14px 0',
          'border': '1px solid #CFD7E3',
          'border-collapse': 'collapse',
          'font-size': '13px',
        });
      case 'th':
        css.addAll({
          'border': '1px solid #CFD7E3',
          'padding': '6px 8px',
          'background-color': '#F3F5F8',
          'font-weight': '700',
          'text-align': 'left',
        });
      case 'td':
        css.addAll({'border': '1px solid #E1E6EE', 'padding': '6px 8px'});
      case 'figcaption':
        css.addAll({
          'color': '#64748B',
          'font-size': '12px',
          'text-align': 'center',
        });
    }
    // No gap above the first block or below the last one: the content sits
    // right under the card header and above the card's bottom padding.
    if (_isTopLevel(element) && css.containsKey('margin')) {
      if (element.previousElementSibling == null) css['margin-top'] = '0';
      if (element.nextElementSibling == null) css['margin-bottom'] = '0';
    }
    return css.isEmpty ? null : css;
  }

  static Map<String, String> _heading(String size) => {
    'font-size': size,
    'font-weight': '700',
    'color': '#0F172A',
    'line-height': '1.35',
    'margin': '16px 0 8px 0',
  };

  /// True when a list item has nothing to show (Quill leaves `<li><br></li>`).
  static bool _isBlank(dom.Element element) =>
      element.text.trim().isEmpty &&
      element.querySelector('img, iframe') == null;

  Widget? widgetFor(BuildContext context, dom.Element element) {
    switch (element.localName) {
      case 'iframe':
        final videoId = youtubeIdFromUrl(element.attributes['src']);
        if (videoId == null) return const SizedBox.shrink();
        return Padding(
          padding: _blockPadding(element, top: 8, bottom: 14),
          child: InlineVideoCard(
            videoId: videoId,
            onTap: () => _open(context, youtubeWatchUrl(videoId)),
          ),
        );
      case 'ol':
        if (nested || !_isTopLevel(element)) return null;
        return Padding(
          padding: _blockPadding(element, top: 2, bottom: 12),
          child: StepList(entries: _steps(element)),
        );
      case 'li':
        final list = element.parent;
        if (list == null || !list.classes.contains('nx-checklist')) {
          return null;
        }
        if (_isBlank(element)) return const SizedBox.shrink();
        return ChecklistItem(
          checked: element.attributes['data-checked'] == 'true',
          depth: indentOf(element),
          child: _inner(element.innerHtml),
        );
      case 'blockquote':
        return Padding(
          padding: _blockPadding(element),
          child: TipBox(
            child: _inner(
              element.innerHtml,
              style: textStyle.copyWith(color: TipBox.textColor),
            ),
          ),
        );
      case 'pre':
        final code = element.text.replaceFirst(RegExp(r'\s+$'), '');
        if (code.trim().isEmpty) return const SizedBox.shrink();
        return Padding(padding: _blockPadding(element), child: CodeBlock(code));
      case 'img':
        final source = element.attributes['src'];
        if (source == null || source.isEmpty) return null;
        final url = baseUri?.resolve(source).toString() ?? source;
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: GestureDetector(
            onTap: () => _open(context, url),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.md),
              child: Image.network(
                url,
                fit: BoxFit.contain,
                webHtmlElementStrategy: WebHtmlElementStrategy.fallback,
                semanticLabel: element.attributes['alt'],
                loadingBuilder: (context, child, progress) => progress == null
                    ? child
                    : Container(
                        height: 160,
                        color: AppColors.surface2,
                        alignment: Alignment.center,
                        child: const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                errorBuilder: (context, error, stackTrace) => Container(
                  height: 90,
                  color: AppColors.surface2,
                  alignment: Alignment.center,
                  child: const Icon(
                    LucideIcons.imageOff,
                    color: AppColors.subtle,
                  ),
                ),
              ),
            ),
          ),
        );
    }
    return null;
  }

  /// Numbers a flat Quill list: plain items are steps 1, 2, 3; indented items
  /// are sub-steps of the step above them (4.1, 4.2), as on the web page.
  List<StepEntry> _steps(dom.Element list) {
    var step = (int.tryParse(list.attributes['start'] ?? '') ?? 1) - 1;
    var sub = 0;
    var started = false;
    final entries = <StepEntry>[];
    for (final item in list.children) {
      if (item.localName != 'li' || _isBlank(item)) continue;
      final depth = started ? indentOf(item) : 0;
      if (depth == 0) {
        step++;
        sub = 0;
        started = true;
        entries.add(StepEntry(label: '$step', child: _inner(item.innerHtml)));
      } else {
        sub++;
        entries.add(
          StepEntry(
            label: '$step.$sub',
            depth: depth,
            child: _inner(item.innerHtml),
          ),
        );
      }
    }
    return entries;
  }
}

/// One numbered step (or sub-step) of a [StepList].
@immutable
class StepEntry {
  const StepEntry({required this.label, required this.child, this.depth = 0});

  final String label;

  /// 0 for a step, 1 or more for a sub-step.
  final int depth;
  final Widget child;
}

/// Numbered step circles for a top-level ordered list.
class StepList extends StatelessWidget {
  const StepList({super.key, required this.entries});

  final List<StepEntry> entries;

  static const _divider = Color(0xFFE3E8EF);

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (final (index, entry) in entries.indexed)
        Container(
          padding: EdgeInsets.only(
            left: entry.depth == 0 ? 0 : 38.0 + (entry.depth - 1) * 18,
            top: index == 0 ? 0 : (entry.depth == 0 ? 10 : 4),
            bottom: index == entries.length - 1
                ? 0
                : (entries[index + 1].depth == 0 ? 10 : 4),
          ),
          decoration: BoxDecoration(
            border: index == entries.length - 1 || entries[index + 1].depth > 0
                ? null
                : const Border(bottom: BorderSide(color: _divider)),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              StepBadge(label: entry.label, sub: entry.depth > 0),
              const SizedBox(width: 12),
              Expanded(child: entry.child),
            ],
          ),
        ),
    ],
  );
}

/// Filled circle with the step number, or a soft pill for sub-steps.
class StepBadge extends StatelessWidget {
  const StepBadge({super.key, required this.label, this.sub = false});

  final String label;
  final bool sub;

  @override
  Widget build(BuildContext context) => Container(
    margin: EdgeInsets.only(top: sub ? 1 : 0),
    constraints: BoxConstraints(
      minWidth: sub ? 30 : 26,
      minHeight: sub ? 22 : 26,
    ),
    padding: EdgeInsets.symmetric(horizontal: sub ? 7 : 4),
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: sub ? AppColors.primarySoft : AppColors.primary,
      borderRadius: BorderRadius.circular(13),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: sub ? AppColors.primary : Colors.white,
        fontSize: sub ? 11.5 : 12.5,
        fontWeight: FontWeight.w700,
        height: 1.2,
      ),
    ),
  );
}

/// One checklist line with a ticked or empty box, as a bordered row.
class ChecklistItem extends StatelessWidget {
  const ChecklistItem({
    super.key,
    required this.checked,
    required this.child,
    this.depth = 0,
  });

  final bool checked;
  final int depth;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    margin: EdgeInsets.only(left: depth * 22.0, bottom: 6),
    padding: const EdgeInsets.fromLTRB(10, 7, 12, 7),
    decoration: BoxDecoration(
      color: checked ? const Color(0xFFF2FBF5) : AppColors.surface2,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      border: Border.all(
        color: checked ? const Color(0xFFC7EAD3) : AppColors.border,
      ),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 3, right: 10),
          child: Container(
            width: 18,
            height: 18,
            decoration: BoxDecoration(
              color: checked ? AppColors.success : Colors.white,
              borderRadius: BorderRadius.circular(4),
              border: Border.all(
                color: checked ? AppColors.success : AppColors.borderStrong,
                width: 1.5,
              ),
            ),
            child: checked
                ? const Icon(LucideIcons.check, size: 13, color: Colors.white)
                : null,
          ),
        ),
        Expanded(child: child),
      ],
    ),
  );
}

/// Quote from the editor, shown as a tip with a light bulb.
class TipBox extends StatelessWidget {
  const TipBox({super.key, required this.child});

  final Widget child;

  static const textColor = Color(0xFF1E3A5F);
  static const _border = Color(0xFFCFE0FA);

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(AppRadius.sm),
    child: Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 14, 10),
      decoration: const BoxDecoration(
        color: Color(0xFFEEF5FF),
        border: Border(
          left: BorderSide(color: AppColors.primary, width: 4),
          top: BorderSide(color: _border),
          right: BorderSide(color: _border),
          bottom: BorderSide(color: _border),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.only(top: 3),
            child: Icon(
              LucideIcons.lightbulb,
              size: 18,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(child: child),
        ],
      ),
    ),
  );
}

/// Code or terminal text. Long lines wrap so nothing is cut off on a phone,
/// and the text can be selected and copied.
class CodeBlock extends StatelessWidget {
  const CodeBlock(this.code, {super.key});

  final String code;

  static const style = TextStyle(
    fontFamily: 'monospace',
    fontFamilyFallback: ['Roboto Mono', 'Menlo', 'Courier New', 'Courier'],
    fontSize: 12.5,
    height: 1.55,
    color: Color(0xFFE2E8F0),
  );

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(
      color: const Color(0xFF0F172A),
      borderRadius: BorderRadius.circular(AppRadius.md),
    ),
    child: SelectableText(code, style: style),
  );
}

/// YouTube embed inside the written steps, shown as a tappable thumbnail.
class InlineVideoCard extends StatelessWidget {
  const InlineVideoCard({
    super.key,
    required this.videoId,
    required this.onTap,
    this.title,
  });

  final String videoId;
  final String? title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.navy,
    borderRadius: BorderRadius.circular(AppRadius.md),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: AspectRatio(
        aspectRatio: 16 / 9,
        child: Stack(
          fit: StackFit.expand,
          children: [
            LibraryImage(
              url: youtubeThumbnailUrl(videoId),
              fallback: const ColoredBox(color: AppColors.navy),
            ),
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Color(0xAA000000)],
                  stops: [.45, 1],
                ),
              ),
            ),
            const Center(child: PlayBadge(size: 54)),
            Positioned(
              left: 12,
              right: 12,
              bottom: 10,
              child: Row(
                children: [
                  const Icon(
                    LucideIcons.externalLink,
                    size: 13,
                    color: Colors.white70,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      title ?? 'Watch on YouTube',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                      ),
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
