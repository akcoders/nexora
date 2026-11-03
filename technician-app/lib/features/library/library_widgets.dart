import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'library_models.dart';

/// Opens [url] outside the app (YouTube app, browser, PDF viewer).
Future<void> openLibraryUrl(BuildContext context, String? url) async {
  final uri = Uri.tryParse(url ?? '');
  if (uri == null || !uri.hasScheme) {
    showAppSnack(context, 'This link is not valid.', tone: Tone.danger);
    return;
  }
  var opened = false;
  try {
    opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
  } catch (_) {
    opened = false;
  }
  if (!opened && context.mounted) {
    showAppSnack(
      context,
      'No app on this phone can open this link.',
      tone: Tone.danger,
    );
  }
}

/// Smaller 16:9 YouTube thumbnail (no letterbox bars) for list rows.
String? listThumbnail(String? url) {
  if (url == null) return null;
  final match = RegExp(
    r'^(https://i\.ytimg\.com/vi/[A-Za-z0-9_-]{11}/)hqdefault\.jpg$',
  ).firstMatch(url);
  return match == null ? url : '${match.group(1)}mqdefault.jpg';
}

/// Network image that shows [fallback] while loading or when it fails.
class LibraryImage extends StatelessWidget {
  const LibraryImage({
    super.key,
    required this.url,
    required this.fallback,
    this.fit = BoxFit.cover,
  });

  final String url;
  final Widget fallback;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) => Image.network(
    url,
    fit: fit,
    width: double.infinity,
    height: double.infinity,
    webHtmlElementStrategy: WebHtmlElementStrategy.fallback,
    frameBuilder: (context, child, frame, wasSynchronouslyLoaded) =>
        wasSynchronouslyLoaded || frame != null ? child : fallback,
    errorBuilder: (context, error, stackTrace) => fallback,
  );
}

/// Category-coloured panel with the category icon, used when a guide has no video.
class CategoryPanel extends StatelessWidget {
  const CategoryPanel({super.key, required this.category, this.iconSize = 26});

  final LibraryCategory category;
  final double iconSize;

  @override
  Widget build(BuildContext context) {
    final swatch = category.swatch;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [swatch.soft, Color.lerp(swatch.soft, swatch.color, .14)!],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -iconSize * .35,
            bottom: -iconSize * .35,
            child: Icon(
              category.iconData,
              size: iconSize * 2.2,
              color: swatch.color.withValues(alpha: .08),
            ),
          ),
          Center(
            child: Icon(category.iconData, size: iconSize, color: swatch.color),
          ),
        ],
      ),
    );
  }
}

/// Round play button drawn over video thumbnails.
class PlayBadge extends StatelessWidget {
  const PlayBadge({super.key, this.size = 30});

  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: const Color(0xFFDC2626),
      shape: BoxShape.circle,
      border: Border.all(color: Colors.white, width: size > 40 ? 3 : 2),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withValues(alpha: .28),
          blurRadius: 10,
          offset: const Offset(0, 3),
        ),
      ],
    ),
    child: Padding(
      padding: EdgeInsets.only(left: size * .06),
      child: Icon(LucideIcons.play, color: Colors.white, size: size * .44),
    ),
  );
}

/// Thumbnail at the start of a guide card: the video cover with a play
/// button, or the category panel.
class GuideThumbnail extends StatelessWidget {
  const GuideThumbnail({
    super.key,
    required this.guide,
    this.width = 112,
    this.height = 84,
  });

  final LibraryArticleSummary guide;
  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    final cover = listThumbnail(guide.coverUrl);
    final panel = CategoryPanel(category: guide.category);
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: SizedBox(
        width: width,
        height: height,
        child: Stack(
          fit: StackFit.expand,
          children: [
            cover == null ? panel : _cover(cover, panel),
            if (guide.isFeatured)
              Positioned(
                left: 5,
                top: 5,
                child: Tooltip(
                  message: 'Featured guide',
                  child: Container(
                    width: 22,
                    height: 22,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF59E0B),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 1.5),
                    ),
                    child: const Icon(
                      LucideIcons.star,
                      size: 12,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _cover(String cover, Widget panel) => Stack(
    fit: StackFit.expand,
    children: [
      LibraryImage(url: cover, fallback: panel),
      const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.transparent, Color(0x66000000)],
          ),
        ),
      ),
      const Center(child: PlayBadge()),
      if (guide.videoCount > 1)
        Positioned(
          right: 5,
          bottom: 5,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: .7),
              borderRadius: BorderRadius.circular(4),
            ),
            child: Text(
              '${guide.videoCount} videos',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 9.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
    ],
  );
}

/// Pill in the category's own colour (supports the `teal` tone the kit lacks).
class CategoryPill extends StatelessWidget {
  const CategoryPill(this.category, {super.key, this.withIcon = true});

  final LibraryCategory category;
  final bool withIcon;

  @override
  Widget build(BuildContext context) {
    final swatch = category.swatch;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: swatch.soft,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (withIcon) ...[
            Icon(category.iconData, size: 11, color: swatch.color),
            const SizedBox(width: 4),
          ],
          Flexible(
            child: Text(
              category.name.toUpperCase(),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: swatch.color,
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
}

/// Small outlined chip with an icon, for device type / manufacturer / model.
class MetaChip extends StatelessWidget {
  const MetaChip({
    super.key,
    required this.icon,
    required this.label,
    this.maxWidth = 150,
  });

  final IconData icon;
  final String label;
  final double maxWidth;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: AppColors.surface2,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      border: Border.all(color: AppColors.border),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 12.5, color: AppColors.secondary),
        const SizedBox(width: 5),
        ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: AppColors.ink,
            ),
          ),
        ),
      ],
    ),
  );
}

/// Icon + number shown at the end of a guide card.
class _CountIcon extends StatelessWidget {
  const _CountIcon({
    required this.icon,
    required this.color,
    required this.tooltip,
    this.count,
  });

  final IconData icon;
  final Color color;
  final String tooltip;
  final int? count;

  @override
  Widget build(BuildContext context) => Tooltip(
    message: tooltip,
    child: Padding(
      padding: const EdgeInsets.only(left: 10),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14.5, color: color),
          if (count != null) ...[
            const SizedBox(width: 3),
            Text(
              '$count',
              style: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: AppColors.muted,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

/// Content counts (steps, videos, documents, links) for a guide.
class GuideContentIcons extends StatelessWidget {
  const GuideContentIcons({super.key, required this.guide});

  final LibraryArticleSummary guide;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      if (guide.hasSteps)
        const _CountIcon(
          icon: LucideIcons.notebookText,
          color: AppColors.success,
          tooltip: 'Written steps',
        ),
      if (guide.videoCount > 0)
        _CountIcon(
          icon: LucideIcons.circlePlay,
          color: AppColors.danger,
          tooltip: 'Videos',
          count: guide.videoCount,
        ),
      if (guide.documentCount > 0)
        _CountIcon(
          icon: LucideIcons.fileText,
          color: AppColors.primary,
          tooltip: 'Documents',
          count: guide.documentCount,
        ),
      if (guide.linkCount > 0)
        _CountIcon(
          icon: LucideIcons.link,
          color: AppColors.info,
          tooltip: 'Web links',
          count: guide.linkCount,
        ),
    ],
  );
}

/// Guide row used by the library list and related guides.
class LibraryArticleCard extends StatelessWidget {
  const LibraryArticleCard({
    super.key,
    required this.guide,
    required this.onTap,
    this.showCategory = true,
  });

  final LibraryArticleSummary guide;
  final VoidCallback onTap;
  final bool showCategory;

  @override
  Widget build(BuildContext context) {
    final summary = guide.summary;
    final hasMeta =
        guide.deviceType != null ||
        guide.manufacturer != null ||
        guide.hasSteps ||
        guide.videoCount + guide.documentCount + guide.linkCount > 0;
    return Card(
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  GuideThumbnail(guide: guide),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (showCategory || guide.isDraft) ...[
                          Wrap(
                            spacing: 5,
                            runSpacing: 5,
                            children: [
                              if (showCategory) CategoryPill(guide.category),
                              if (guide.isDraft)
                                const Pill('Draft', tone: Tone.slate),
                            ],
                          ),
                          const SizedBox(height: 6),
                        ],
                        Text(
                          guide.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.w700,
                            color: AppColors.heading,
                            height: 1.3,
                          ),
                        ),
                        if (summary != null) ...[
                          const SizedBox(height: 3),
                          Text(
                            summary,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
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
              if (hasMeta) ...[
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          if (guide.deviceType != null)
                            MetaChip(
                              icon: LucideIcons.airVent,
                              label: guide.deviceType!.label,
                              maxWidth: 110,
                            ),
                          if (guide.manufacturer != null)
                            MetaChip(
                              icon: LucideIcons.factory,
                              label: guide.manufacturer!.label,
                              maxWidth: 100,
                            ),
                        ],
                      ),
                    ),
                    GuideContentIcons(guide: guide),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Grey placeholder shaped like [LibraryArticleCard], shown on first load.
class LibraryCardSkeleton extends StatelessWidget {
  const LibraryCardSkeleton({super.key});

  static Widget _bar(double width, double height) => Container(
    width: width,
    height: height,
    decoration: BoxDecoration(
      color: AppColors.slateSoft,
      borderRadius: BorderRadius.circular(4),
    ),
  );

  @override
  Widget build(BuildContext context) =>
      Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 112,
                        height: 84,
                        decoration: BoxDecoration(
                          color: AppColors.slateSoft,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _bar(86, 14),
                            const SizedBox(height: 9),
                            _bar(double.infinity, 12),
                            const SizedBox(height: 6),
                            _bar(150, 12),
                            const SizedBox(height: 10),
                            _bar(double.infinity, 9),
                            const SizedBox(height: 5),
                            _bar(120, 9),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      _bar(78, 20),
                      const SizedBox(width: 6),
                      _bar(64, 20),
                      const Spacer(),
                      _bar(48, 12),
                    ],
                  ),
                ],
              ),
            ),
          )
          .animate(onPlay: (controller) => controller.repeat())
          .shimmer(
            duration: 1200.ms,
            color: Colors.white.withValues(alpha: .7),
          );
}
