import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'library_api.dart';
import 'library_body.dart';
import 'library_models.dart';
import 'library_widgets.dart';

/// One how-to guide: video, written steps, documents, links and related guides.
class LibraryArticleScreen extends StatefulWidget {
  const LibraryArticleScreen({
    super.key,
    required this.articleId,
    this.preview,
    this.api,
    this.onSearchTag,
  });

  final int articleId;

  /// Card data shown straight away while the full guide loads.
  final LibraryArticleSummary? preview;

  final LibraryApi? api;

  /// Searches the library for a tag. Tags are plain labels when null.
  final ValueChanged<String>? onSearchTag;

  @override
  State<LibraryArticleScreen> createState() => _LibraryArticleScreenState();
}

class _LibraryArticleScreenState extends State<LibraryArticleScreen> {
  late final LibraryApi api =
      widget.api ?? LibraryApi(context.read<ApiClient>().dio);
  final sectionKeys = {
    'steps': GlobalKey(),
    'videos': GlobalKey(),
    'documents': GlobalKey(),
    'links': GlobalKey(),
  };

  LibraryArticle? article;
  bool loading = true;
  Object? failure;

  /// Document id being re-fetched because its signed link expired.
  int? openingDocument;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      failure = null;
    });
    try {
      final next = await api.guide(widget.articleId);
      if (mounted) setState(() => article = next);
    } catch (exception) {
      if (!mounted) return;
      setState(() => failure = exception);
      // Pull-to-refresh keeps the guide on screen; say why nothing changed.
      if (article != null) {
        showAppSnack(
          context,
          apiErrorMessage(exception, 'Unable to refresh this guide.'),
          tone: Tone.danger,
        );
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void open(String? url) => openLibraryUrl(context, url);

  /// Opens a document. Its signed link is valid for an hour, so a guide left
  /// open longer fetches a fresh link first instead of failing in the browser.
  Future<void> openDocument(LibraryDocument document) async {
    if (!document.linkExpired) return open(document.url);
    if (openingDocument != null) return;
    setState(() => openingDocument = document.id);
    try {
      final next = await api.guide(widget.articleId);
      if (!mounted) return;
      setState(() => article = next);
      final fresh = next.documents
          .where((item) => item.id == document.id)
          .firstOrNull;
      if (fresh == null) {
        showAppSnack(
          context,
          'This document was removed from the guide.',
          tone: Tone.danger,
        );
      } else {
        await openLibraryUrl(context, fresh.url);
      }
    } catch (exception) {
      if (mounted) {
        showAppSnack(
          context,
          apiErrorMessage(exception, 'Unable to open this document.'),
          tone: Tone.danger,
        );
      }
    } finally {
      if (mounted) setState(() => openingDocument = null);
    }
  }

  void jumpTo(String section) {
    final target = sectionKeys[section]?.currentContext;
    if (target != null) {
      Scrollable.ensureVisible(
        target,
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeOutCubic,
        alignment: .02,
      );
    }
  }

  void openRelated(LibraryArticleSummary guide) => Navigator.push<void>(
    context,
    MaterialPageRoute(
      builder: (_) => LibraryArticleScreen(
        articleId: guide.id,
        preview: guide,
        api: api,
        onSearchTag: widget.onSearchTag,
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final article = this.article;
    final summary = article?.summary ?? widget.preview;
    return Scaffold(
      appBar: AppBar(
        title: const T('How-to guide'),
        actions: [
          if (article != null && article.videos.isNotEmpty)
            IconButton(
              tooltip: 'Watch video',
              onPressed: () => open(article.videos.first.watchUrl),
              icon: const Icon(LucideIcons.circlePlay),
            ),
          const SizedBox(width: 4),
        ],
        bottom: loading && article != null
            ? const PreferredSize(
                preferredSize: Size.fromHeight(2),
                child: LinearProgressIndicator(minHeight: 2),
              )
            : null,
      ),
      body: article == null
          ? _placeholder(summary)
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                children: _content(article),
              ),
            ),
    );
  }

  /// Loading or error view, keeping the card details on screen when known.
  Widget _placeholder(LibraryArticleSummary? summary) {
    if (!loading && failure != null) {
      final forbidden = isLibraryForbidden(failure!);
      final missing = isLibraryNotFound(failure!);
      if (forbidden) {
        return ListView(
          padding: const EdgeInsets.all(16),
          children: const [
            NoticeBox(
              'Your account cannot open library guides. Ask your admin to allow library access for your role.',
              tone: Tone.warning,
              icon: LucideIcons.lock,
            ),
          ],
        );
      }
      return EmptyState(
        icon: missing ? LucideIcons.fileX : LucideIcons.cloudOff,
        title: missing ? 'Guide not available' : 'Unable to open this guide',
        message: missing
            ? 'This guide was removed or unpublished by your admin.'
            : apiErrorMessage(failure!, 'Check your connection and try again.'),
        actionLabel: missing ? null : 'Retry',
        onAction: missing ? null : load,
      );
    }
    if (summary == null) return const LoadingView(message: 'Opening guide…');
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        _GuideHeader.preview(summary: summary),
        const SizedBox(height: 24),
        const LoadingView(message: 'Loading steps and files…'),
      ],
    );
  }

  List<Widget> _content(LibraryArticle article) {
    final summary = article.summary;
    final videos = article.videos;
    final heroVideo = videos.isEmpty ? null : videos.first;
    final listedVideos = videos.length > 1 ? videos : const <LibraryVideo>[];
    final jumps = <({String key, IconData icon, String label, Tone tone})>[
      if (article.hasBody)
        (
          key: 'steps',
          icon: LucideIcons.notebookText,
          label: 'Steps',
          tone: Tone.success,
        ),
      if (listedVideos.isNotEmpty)
        (
          key: 'videos',
          icon: LucideIcons.circlePlay,
          label: 'Videos · ${videos.length}',
          tone: Tone.danger,
        ),
      if (article.documents.isNotEmpty)
        (
          key: 'documents',
          icon: LucideIcons.fileText,
          label: 'Documents · ${article.documents.length}',
          tone: Tone.primary,
        ),
      if (article.links.isNotEmpty)
        (
          key: 'links',
          icon: LucideIcons.link,
          label: 'Links · ${article.links.length}',
          tone: Tone.info,
        ),
    ];
    final empty =
        !article.hasBody &&
        videos.isEmpty &&
        article.documents.isEmpty &&
        article.links.isEmpty;

    return [
      if (heroVideo?.videoId != null) ...[
        _VideoHero(
          video: heroVideo!,
          total: videos.length,
          onTap: () => open(heroVideo.watchUrl),
        ),
        const SizedBox(height: 16),
      ],
      _GuideHeader(
        summary: summary,
        summaryText: article.fullSummary,
        showIcon: heroVideo?.videoId == null,
      ),
      if (jumps.length > 1) ...[
        const SizedBox(height: 14),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final jump in jumps)
              _JumpChip(
                icon: jump.icon,
                label: jump.label,
                tone: jump.tone,
                onTap: () => jumpTo(jump.key),
              ),
          ],
        ),
      ],
      const SizedBox(height: 16),
      if (empty)
        const Card(
          child: EmptyState(
            compact: true,
            icon: LucideIcons.fileQuestion,
            title: 'Nothing added yet',
            message: 'Your admin has not added steps or files to this guide.',
          ),
        ),
      if (article.hasBody) ...[
        SectionCard(
          key: sectionKeys['steps'],
          title: 'Steps',
          icon: LucideIcons.listChecks,
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
          child: LibraryBody(html: article.bodyHtml),
        ),
        const SizedBox(height: 14),
      ],
      if (listedVideos.isNotEmpty) ...[
        SectionCard(
          key: sectionKeys['videos'],
          title: 'Videos',
          subtitle: 'Opens in the YouTube app',
          icon: LucideIcons.circlePlay,
          trailing: Pill('${videos.length}', tone: Tone.danger),
          padding: EdgeInsets.zero,
          child: Column(
            children: [
              for (final (index, video) in listedVideos.indexed)
                _VideoRow(
                  video: video,
                  last: index == listedVideos.length - 1,
                  onTap: () => open(video.watchUrl),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),
      ],
      if (article.documents.isNotEmpty) ...[
        SectionCard(
          key: sectionKeys['documents'],
          title: 'Documents',
          subtitle: 'Tap to open or download',
          icon: LucideIcons.fileText,
          trailing: Pill('${article.documents.length}', tone: Tone.primary),
          padding: EdgeInsets.zero,
          child: Column(
            children: [
              for (final (index, document) in article.documents.indexed)
                _DocumentRow(
                  document: document,
                  last: index == article.documents.length - 1,
                  busy: openingDocument == document.id,
                  onTap: () => openDocument(document),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),
      ],
      if (article.links.isNotEmpty) ...[
        SectionCard(
          key: sectionKeys['links'],
          title: 'Web links',
          icon: LucideIcons.link,
          trailing: Pill('${article.links.length}', tone: Tone.info),
          padding: EdgeInsets.zero,
          child: Column(
            children: [
              for (final (index, link) in article.links.indexed)
                _LinkRow(
                  link: link,
                  last: index == article.links.length - 1,
                  onTap: () => open(link.url),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),
      ],
      if (summary.tags.isNotEmpty) ...[
        SectionCard(
          title: 'Tags',
          subtitle: widget.onSearchTag == null
              ? null
              : 'Tap a tag to find similar guides',
          icon: LucideIcons.tag,
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
          child: Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final tag in summary.tags)
                _TagChip(
                  tag: tag,
                  onTap: widget.onSearchTag == null
                      ? null
                      : () => widget.onSearchTag!(tag),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),
      ],
      _AuthorLine(article: article),
      if (article.related.isNotEmpty) ...[
        const SectionLabel('Related guides'),
        for (final guide in article.related)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: LibraryArticleCard(
              guide: guide,
              onTap: () => openRelated(guide),
            ),
          ),
      ],
    ];
  }
}

/// Category, title, summary and equipment chips at the top of a guide.
class _GuideHeader extends StatelessWidget {
  const _GuideHeader({
    required this.summary,
    this.summaryText,
    this.showIcon = true,
  }) : _useCardSummary = false;

  /// Header built from list card data while the full guide loads.
  const _GuideHeader.preview({required this.summary})
    : summaryText = null,
      showIcon = true,
      _useCardSummary = true;

  final LibraryArticleSummary summary;
  final String? summaryText;
  final bool showIcon;
  final bool _useCardSummary;

  @override
  Widget build(BuildContext context) {
    final category = summary.category;
    final updated = summary.updatedAt ?? summary.publishedAt;
    final description = _useCardSummary ? summary.summary : summaryText;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (showIcon) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.md),
                child: SizedBox(
                  width: 52,
                  height: 52,
                  child: CategoryPanel(category: category, iconSize: 24),
                ),
              ),
              const SizedBox(width: 12),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      CategoryPill(category),
                      if (summary.isFeatured)
                        const Pill(
                          'Featured',
                          tone: Tone.warning,
                          icon: LucideIcons.star,
                        ),
                      if (summary.isDraft)
                        const Pill('Draft', tone: Tone.slate),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    summary.title,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      color: AppColors.heading,
                      height: 1.3,
                      letterSpacing: -.2,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        if (description != null) ...[
          const SizedBox(height: 8),
          Text(
            description,
            style: const TextStyle(
              fontSize: 14,
              color: AppColors.muted,
              height: 1.5,
            ),
          ),
        ],
        if (summary.deviceType != null ||
            summary.manufacturer != null ||
            summary.modelNumbers != null) ...[
          const SizedBox(height: 12),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (summary.deviceType != null)
                MetaChip(
                  icon: LucideIcons.airVent,
                  label: summary.deviceType!.label,
                ),
              if (summary.manufacturer != null)
                MetaChip(
                  icon: LucideIcons.factory,
                  label: summary.manufacturer!.label,
                ),
              if (summary.modelNumbers != null)
                MetaChip(
                  icon: LucideIcons.hash,
                  label: summary.modelNumbers!,
                  maxWidth: 240,
                ),
            ],
          ),
        ],
        const SizedBox(height: 12),
        // Wraps instead of overflowing on narrow phones or large text sizes.
        Wrap(
          spacing: 14,
          runSpacing: 4,
          children: [
            _StatText(
              icon: LucideIcons.eye,
              text:
                  '${summary.viewsCount} ${context.tr(summary.viewsCount == 1 ? 'view' : 'views')}',
            ),
            if (updated != null)
              _StatText(
                icon: LucideIcons.calendarClock,
                text:
                    '${context.tr('Updated')} ${DateFormat('d MMM yyyy').format(updated)}',
              ),
          ],
        ),
      ],
    );
  }
}

/// Small grey icon + text (views, last update).
class _StatText extends StatelessWidget {
  const _StatText({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: AppColors.subtle),
      const SizedBox(width: 5),
      Flexible(
        child: Text(
          text,
          style: const TextStyle(fontSize: 12, color: AppColors.muted),
        ),
      ),
    ],
  );
}

class _VideoHero extends StatelessWidget {
  const _VideoHero({
    required this.video,
    required this.total,
    required this.onTap,
  });

  final LibraryVideo video;
  final int total;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.navy,
    borderRadius: BorderRadius.circular(AppRadius.lg),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: AspectRatio(
        aspectRatio: 16 / 9,
        child: Stack(
          fit: StackFit.expand,
          children: [
            LibraryImage(
              url: video.thumbnail ?? '',
              fallback: const ColoredBox(color: AppColors.navy),
            ),
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0x22000000), Color(0xCC000000)],
                  stops: [.35, 1],
                ),
              ),
            ),
            const Center(child: PlayBadge(size: 60)),
            Positioned(
              left: 14,
              right: 14,
              bottom: 12,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    video.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                      height: 1.3,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(
                        LucideIcons.externalLink,
                        size: 13,
                        color: Colors.white70,
                      ),
                      const SizedBox(width: 5),
                      Flexible(
                        child: T(
                          total > 1
                              ? 'Watch on YouTube · 1 of $total'
                              : 'Watch on YouTube',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
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

class _JumpChip extends StatelessWidget {
  const _JumpChip({
    required this.icon,
    required this.label,
    required this.tone,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Tone tone;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.md),
      side: const BorderSide(color: AppColors.border),
    ),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Container(
        constraints: const BoxConstraints(minHeight: 40),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 15, color: tone.color),
            const SizedBox(width: 6),
            T(
              label,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: AppColors.ink,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Shared row layout for videos, documents and links inside a section card.
class _ResourceRow extends StatelessWidget {
  const _ResourceRow({
    required this.leading,
    required this.title,
    required this.subtitle,
    required this.trailingIcon,
    required this.onTap,
    required this.last,
    this.busy = false,
  });

  final Widget leading;
  final String title;
  final String subtitle;
  final IconData trailingIcon;
  final VoidCallback onTap;
  final bool last;
  final bool busy;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 14, 12),
      decoration: BoxDecoration(
        border: last
            ? null
            : const Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          leading,
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.heading,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 12, color: AppColors.muted),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          busy
              ? const SizedBox.square(
                  dimension: 17,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Icon(trailingIcon, size: 17, color: AppColors.subtle),
        ],
      ),
    ),
  );
}

class _VideoRow extends StatelessWidget {
  const _VideoRow({
    required this.video,
    required this.onTap,
    required this.last,
  });

  final LibraryVideo video;
  final VoidCallback onTap;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final thumbnail = listThumbnail(video.thumbnail);
    const fallback = ColoredBox(
      color: AppColors.navy,
      child: Center(
        child: Icon(LucideIcons.circlePlay, color: Colors.white70, size: 22),
      ),
    );
    return _ResourceRow(
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: SizedBox(
          width: 96,
          height: 54,
          child: Stack(
            fit: StackFit.expand,
            children: [
              thumbnail == null
                  ? fallback
                  : LibraryImage(url: thumbnail, fallback: fallback),
              const Center(child: PlayBadge(size: 24)),
            ],
          ),
        ),
      ),
      title: video.title,
      subtitle: 'YouTube',
      trailingIcon: LucideIcons.externalLink,
      onTap: onTap,
      last: last,
    );
  }
}

class _DocumentRow extends StatelessWidget {
  const _DocumentRow({
    required this.document,
    required this.onTap,
    required this.last,
    this.busy = false,
  });

  final LibraryDocument document;
  final VoidCallback onTap;
  final bool last;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final style = documentStyle(document.extension);
    final details = [
      document.extension?.toUpperCase(),
      document.sizeLabel,
      if (document.fileName != null && document.fileName != document.title)
        document.fileName,
    ].whereType<String>().join(' · ');
    return _ResourceRow(
      leading: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: style.swatch.soft,
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Icon(style.icon, size: 20, color: style.swatch.color),
      ),
      title: document.title,
      subtitle: details.isEmpty ? 'Document' : details,
      trailingIcon: LucideIcons.download,
      onTap: onTap,
      last: last,
      busy: busy,
    );
  }
}

class _LinkRow extends StatelessWidget {
  const _LinkRow({required this.link, required this.onTap, required this.last});

  final LibraryLink link;
  final VoidCallback onTap;
  final bool last;

  @override
  Widget build(BuildContext context) => _ResourceRow(
    leading: const IconTile(LucideIcons.globe, tone: Tone.info, size: 42),
    title: link.title,
    subtitle: link.host ?? link.url,
    trailingIcon: LucideIcons.externalLink,
    onTap: onTap,
    last: last,
  );
}

/// `#tag` label; searches the library for the tag when [onTap] is set.
class _TagChip extends StatelessWidget {
  const _TagChip({required this.tag, this.onTap});

  final String tag;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.slateSoft,
    borderRadius: BorderRadius.circular(AppRadius.sm),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: Container(
        constraints: const BoxConstraints(minHeight: 36),
        padding: const EdgeInsets.symmetric(horizontal: 11),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (onTap != null) ...[
              const Icon(LucideIcons.search, size: 13, color: AppColors.muted),
              const SizedBox(width: 5),
            ],
            Text(
              '#$tag',
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: AppColors.slate,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _AuthorLine extends StatelessWidget {
  const _AuthorLine({required this.article});

  final LibraryArticle article;

  @override
  Widget build(BuildContext context) {
    final author = article.authorName;
    final editor = article.updatedByName;
    if (author == null && editor == null) return const SizedBox.shrink();
    final text = [
      if (author != null) '${context.tr('Written by')} $author',
      if (editor != null && editor != author)
        '${context.tr('updated by')} $editor',
    ].join(' · ');
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          const Icon(LucideIcons.penLine, size: 14, color: AppColors.subtle),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(fontSize: 12, color: AppColors.muted),
            ),
          ),
        ],
      ),
    );
  }
}
