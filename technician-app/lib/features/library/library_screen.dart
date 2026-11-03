import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'library_api.dart';
import 'library_article_screen.dart';
import 'library_filter_sheet.dart';
import 'library_models.dart';
import 'library_widgets.dart';

/// How-to library: search, category chips, filters and an endless list of guides.
class LibraryScreen extends StatefulWidget {
  const LibraryScreen({super.key, this.api});

  /// Injected in tests; defaults to the app's [ApiClient].
  final LibraryApi? api;

  @override
  State<LibraryScreen> createState() => _LibraryScreenState();
}

class _LibraryScreenState extends State<LibraryScreen> {
  late final LibraryApi api =
      widget.api ?? LibraryApi(context.read<ApiClient>().dio);
  final searchController = TextEditingController();
  final scrollController = ScrollController();
  Timer? debounce;

  LibraryFilterOptions options = const LibraryFilterOptions();
  LibraryQuery query = const LibraryQuery();
  List<LibraryArticleSummary> guides = [];
  int page = 0;
  int lastPage = 1;
  int total = 0;
  bool loading = true;
  bool loadingMore = false;
  bool forbidden = false;

  /// False until the filter options load; retried on the next reload.
  bool filtersLoaded = false;
  String? error;
  String? moreError;

  /// Bumped on every reload so late responses for an old query are ignored.
  int generation = 0;

  bool get hasMore => page < lastPage;

  @override
  void initState() {
    super.initState();
    scrollController.addListener(onScroll);
    loadFilters();
    reload();
  }

  @override
  void dispose() {
    debounce?.cancel();
    searchController.dispose();
    scrollController.dispose();
    super.dispose();
  }

  /// In-flight filters request, shared so start-up and retries ask once.
  Future<void>? filtersRequest;

  Future<void> loadFilters() => filtersRequest ??= _fetchFilters().whenComplete(
    () => filtersRequest = null,
  );

  Future<void> _fetchFilters() async {
    try {
      final next = await api.filters();
      if (mounted) {
        setState(() {
          options = next;
          filtersLoaded = true;
        });
      }
    } catch (exception) {
      if (mounted && isLibraryForbidden(exception)) {
        setState(() => forbidden = true);
      }
    }
  }

  Future<void> reload() async {
    final current = ++generation;
    setState(() {
      loading = true;
      error = null;
      moreError = null;
    });
    // Categories and filters failed earlier (offline start): try again.
    if (!filtersLoaded && !forbidden) loadFilters();
    try {
      final result = await api.guides(query);
      if (!mounted || current != generation) return;
      setState(() {
        guides = result.items;
        page = result.currentPage;
        lastPage = result.lastPage;
        total = result.total;
        forbidden = false;
      });
      // A short first page on a tall screen cannot be scrolled to load more.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) onScroll();
      });
    } catch (exception) {
      if (!mounted || current != generation) return;
      setState(() {
        forbidden = isLibraryForbidden(exception);
        error = apiErrorMessage(
          exception,
          'Check your connection and try again.',
        );
      });
    } finally {
      if (mounted && current == generation) setState(() => loading = false);
    }
  }

  Future<void> loadMore() async {
    if (loading || loadingMore || !hasMore) return;
    final current = generation;
    setState(() {
      loadingMore = true;
      moreError = null;
    });
    try {
      final result = await api.guides(query, page: page + 1);
      if (!mounted || current != generation) return;
      final known = guides.map((guide) => guide.id).toSet();
      setState(() {
        guides = [
          ...guides,
          ...result.items.where((guide) => !known.contains(guide.id)),
        ];
        page = result.currentPage;
        lastPage = result.lastPage;
        total = result.total;
      });
    } catch (exception) {
      if (!mounted || current != generation) return;
      setState(() => moreError = apiErrorMessage(exception));
    } finally {
      if (mounted && current == generation) {
        setState(() => loadingMore = false);
      }
    }
  }

  void onScroll() {
    if (!scrollController.hasClients) return;
    if (scrollController.position.extentAfter < 600 && moreError == null) {
      loadMore();
    }
  }

  Future<void> refresh() async {
    await Future.wait([loadFilters(), reload()]);
  }

  /// Search for a tag tapped on a guide: back to this list, filters cleared.
  void searchTag(String tag) {
    final route = ModalRoute.of(context);
    Navigator.of(context).popUntil((other) => other == route || other.isFirst);
    debounce?.cancel();
    searchController.text = tag;
    setQuery(LibraryQuery(search: tag));
  }

  void setQuery(LibraryQuery next) {
    if (next == query) return;
    setState(() => query = next);
    if (scrollController.hasClients) scrollController.jumpTo(0);
    reload();
  }

  void onSearchChanged(String value) {
    setState(() {}); // Show or hide the clear button.
    debounce?.cancel();
    debounce = Timer(
      const Duration(milliseconds: 400),
      () => setQuery(query.copyWith(search: value)),
    );
  }

  void clearSearch() {
    debounce?.cancel();
    searchController.clear();
    setQuery(query.copyWith(search: ''));
  }

  void clearAll() {
    debounce?.cancel();
    searchController.clear();
    setQuery(const LibraryQuery());
  }

  Future<void> openFilters() async {
    FocusScope.of(context).unfocus();
    final next = await showLibraryFilterSheet(
      context,
      options: options,
      query: query,
    );
    if (next != null) setQuery(next);
  }

  Future<void> openGuide(LibraryArticleSummary guide) async {
    FocusScope.of(context).unfocus();
    await Navigator.push<void>(
      context,
      MaterialPageRoute(
        builder: (_) => LibraryArticleScreen(
          articleId: guide.id,
          preview: guide,
          api: api,
          onSearchTag: searchTag,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const T('How-to library'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: loading ? null : refresh,
            icon: const Icon(LucideIcons.refreshCw),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: forbidden
          ? const _NoAccess()
          : Column(
              children: [
                _header(),
                Expanded(
                  child: Stack(
                    children: [
                      Positioned.fill(child: _results()),
                      if (loading && guides.isNotEmpty)
                        const Positioned(
                          left: 0,
                          right: 0,
                          top: 0,
                          child: LinearProgressIndicator(minHeight: 2),
                        ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _header() {
    final filterCount = query.sheetFilterCount;
    final activeFilters = <Widget>[
      if (query.content != null)
        _ActiveFilter(
          icon: LucideIcons.layers,
          label: options.contentLabel(query.content) ?? query.content!,
          onRemove: () => setQuery(query.copyWith(content: () => null)),
        ),
      if (query.deviceType != null)
        _ActiveFilter(
          icon: LucideIcons.airVent,
          label: options.deviceTypeLabel(query.deviceType) ?? '',
          onRemove: () => setQuery(query.copyWith(deviceType: () => null)),
        ),
      if (query.manufacturer != null)
        _ActiveFilter(
          icon: LucideIcons.factory,
          label: options.manufacturerLabel(query.manufacturer) ?? '',
          onRemove: () => setQuery(query.copyWith(manufacturer: () => null)),
        ),
    ];

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: AppColors.border)),
      ),
      padding: const EdgeInsets.only(top: 12, bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: searchController,
                    onChanged: onSearchChanged,
                    onSubmitted: (value) {
                      debounce?.cancel();
                      setQuery(query.copyWith(search: value));
                    },
                    textInputAction: TextInputAction.search,
                    decoration: InputDecoration(
                      hintText: context.tr('Search guides or error codes'),
                      prefixIcon: const Icon(LucideIcons.search, size: 19),
                      fillColor: AppColors.surface2,
                      suffixIcon: searchController.text.isEmpty
                          ? null
                          : IconButton(
                              tooltip: 'Clear search',
                              onPressed: clearSearch,
                              icon: const Icon(LucideIcons.x, size: 18),
                            ),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                _FilterButton(count: filterCount, onPressed: openFilters),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                _CategoryChip(
                  label: 'All guides',
                  icon: LucideIcons.layoutGrid,
                  swatch: LibrarySwatch.of(Tone.primary),
                  count: options.categories.isEmpty
                      ? null
                      : options.totalGuides,
                  selected: query.categoryId == null,
                  translate: true,
                  onTap: () => setQuery(query.copyWith(categoryId: () => null)),
                ),
                for (final category in options.categories)
                  _CategoryChip(
                    label: category.name,
                    icon: category.iconData,
                    swatch: category.swatch,
                    count: category.count,
                    selected: query.categoryId == category.id,
                    onTap: () => setQuery(
                      query.copyWith(
                        categoryId: () => query.categoryId == category.id
                            ? null
                            : category.id,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (activeFilters.isNotEmpty) ...[
            const SizedBox(height: 10),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: activeFilters,
                    ),
                  ),
                  InkWell(
                    onTap: () => setQuery(query.clearSheetFilters()),
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                    child: Container(
                      constraints: const BoxConstraints(minHeight: 34),
                      alignment: Alignment.center,
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      child: const T(
                        'Clear',
                        style: TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.w600,
                          fontSize: 12.5,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _results() {
    if (loading && guides.isEmpty) {
      return ListView.separated(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        itemCount: 4,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, _) => const LibraryCardSkeleton(),
      );
    }
    if (error != null && guides.isEmpty) {
      return RefreshIndicator(
        onRefresh: refresh,
        child: EmptyState(
          icon: LucideIcons.cloudOff,
          title: 'Unable to load the library',
          message: error,
          actionLabel: 'Retry',
          onAction: refresh,
        ),
      );
    }
    if (guides.isEmpty) {
      final onlySearching =
          query.categoryId == null && query.sheetFilterCount == 0;
      return RefreshIndicator(
        onRefresh: refresh,
        child: query.isFiltered
            ? EmptyState(
                icon: LucideIcons.searchX,
                title: 'No guides match',
                message: onlySearching
                    ? 'Try another word, model number or error code.'
                    : 'Try another word, model number or error code, or clear the filters.',
                actionLabel: onlySearching ? 'Clear search' : 'Clear filters',
                actionIcon: onlySearching ? LucideIcons.x : LucideIcons.funnelX,
                onAction: clearAll,
              )
            : const EmptyState(
                icon: LucideIcons.bookOpen,
                title: 'No guides yet',
                message:
                    'How-to guides, videos and manuals added by your admin will appear here.',
              ),
      );
    }

    final category = options.categoryById(query.categoryId);
    return RefreshIndicator(
      onRefresh: refresh,
      child: ListView.separated(
        controller: scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
        itemCount: guides.length + 2,
        separatorBuilder: (_, index) => SizedBox(height: index == 0 ? 12 : 10),
        itemBuilder: (context, index) {
          if (index == 0) {
            return _ResultsHeader(
              title: category?.name,
              description: category?.description,
              total: total,
              searching: query.search.trim().isNotEmpty,
              filtered: query.sheetFilterCount > 0,
            );
          }
          if (index == guides.length + 1) return _footer();
          final guide = guides[index - 1];
          return LibraryArticleCard(
            guide: guide,
            showCategory: query.categoryId == null,
            onTap: () => openGuide(guide),
          );
        },
      ),
    );
  }

  Widget _footer() {
    if (loadingMore) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 14),
        child: Center(
          child: SizedBox.square(
            dimension: 22,
            child: CircularProgressIndicator(strokeWidth: 2.4),
          ),
        ),
      );
    }
    if (moreError != null) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          children: [
            NoticeBox(moreError!, icon: LucideIcons.cloudOff),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: () {
                setState(() => moreError = null);
                loadMore();
              },
              icon: const Icon(LucideIcons.refreshCw, size: 16),
              label: const T('Load more'),
            ),
          ],
        ),
      );
    }
    if (hasMore) return const SizedBox(height: 40);
    if (guides.length < 4) return const SizedBox.shrink();
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(LucideIcons.circleCheck, size: 15, color: AppColors.subtle),
          SizedBox(width: 6),
          T(
            'You have seen every guide here',
            style: TextStyle(color: AppColors.subtle, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _NoAccess extends StatelessWidget {
  const _NoAccess();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: const [
      SizedBox(height: 24),
      Center(child: IconTile(LucideIcons.lock, tone: Tone.warning, size: 64)),
      SizedBox(height: 16),
      T(
        'Library access needed',
        textAlign: TextAlign.center,
        style: TextStyle(
          fontSize: 17,
          fontWeight: FontWeight.w700,
          color: AppColors.heading,
        ),
      ),
      SizedBox(height: 16),
      NoticeBox(
        'Your account cannot open the how-to library yet. Ask your admin to allow library access for your role, then open this page again.',
        tone: Tone.warning,
        icon: LucideIcons.shieldAlert,
      ),
    ],
  );
}

class _FilterButton extends StatelessWidget {
  const _FilterButton({required this.count, required this.onPressed});

  final int count;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final active = count > 0;
    return Tooltip(
      message: context.tr('Filters'),
      child: Badge(
        isLabelVisible: active,
        label: Text('$count'),
        backgroundColor: AppColors.primary,
        offset: const Offset(-2, -4),
        child: Material(
          color: active ? AppColors.primarySoft : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
            side: BorderSide(
              color: active ? AppColors.primary : AppColors.borderStrong,
            ),
          ),
          child: InkWell(
            onTap: onPressed,
            borderRadius: BorderRadius.circular(AppRadius.md),
            child: SizedBox(
              width: 48,
              height: 48,
              child: Icon(
                LucideIcons.slidersHorizontal,
                size: 20,
                color: active ? AppColors.primary : AppColors.ink,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.icon,
    required this.swatch,
    required this.selected,
    required this.onTap,
    this.count,
    this.translate = false,
  });

  final String label;
  final IconData icon;
  final LibrarySwatch swatch;
  final bool selected;
  final VoidCallback onTap;
  final int? count;
  final bool translate;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontSize: 13,
      fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
      color: selected ? Colors.white : AppColors.ink,
    );
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Material(
        color: selected ? swatch.color : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(
            color: selected ? swatch.color : AppColors.borderStrong,
          ),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.md),
          child: Padding(
            padding: const EdgeInsets.only(left: 10, right: 10),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  icon,
                  size: 16,
                  color: selected ? Colors.white : swatch.color,
                ),
                const SizedBox(width: 7),
                translate ? T(label, style: style) : Text(label, style: style),
                if (count != null) ...[
                  const SizedBox(width: 7),
                  Container(
                    constraints: const BoxConstraints(minWidth: 20),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 5,
                      vertical: 1,
                    ),
                    decoration: BoxDecoration(
                      color: selected
                          ? Colors.white.withValues(alpha: .22)
                          : swatch.soft,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Text(
                      '$count',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: selected ? Colors.white : swatch.color,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ActiveFilter extends StatelessWidget {
  const _ActiveFilter({
    required this.icon,
    required this.label,
    required this.onRemove,
  });

  final IconData icon;
  final String label;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.primarySoft,
    borderRadius: BorderRadius.circular(AppRadius.sm),
    child: InkWell(
      onTap: onRemove,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: Container(
        constraints: const BoxConstraints(minHeight: 34),
        padding: const EdgeInsets.fromLTRB(10, 0, 8, 0),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 13, color: AppColors.primary),
            const SizedBox(width: 5),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 160),
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: AppColors.primary,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(width: 6),
            const Icon(LucideIcons.x, size: 14, color: AppColors.primary),
          ],
        ),
      ),
    ),
  );
}

class _ResultsHeader extends StatelessWidget {
  const _ResultsHeader({
    required this.title,
    required this.description,
    required this.total,
    required this.searching,
    this.filtered = false,
  });

  final String? title;
  final String? description;
  final int total;
  final bool searching;

  /// Device, brand or content filters are applied.
  final bool filtered;

  @override
  Widget build(BuildContext context) {
    final caption =
        description ??
        (searching
            ? 'Best matches, featured first'
            : 'Featured guides first, then the newest');
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              title == null
                  ? T(
                      searching
                          ? 'Search results'
                          : (filtered ? 'Matching guides' : 'All guides'),
                      style: _titleStyle,
                    )
                  : Text(title!, style: _titleStyle),
              const SizedBox(height: 2),
              T(
                caption,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 12, color: AppColors.muted),
              ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(
            color: AppColors.navy,
            borderRadius: BorderRadius.circular(AppRadius.sm),
          ),
          child: Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: '$total',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                TextSpan(
                  text: ' ${context.tr(total == 1 ? 'guide' : 'guides')}',
                  style: const TextStyle(
                    color: AppColors.navyText,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
            style: const TextStyle(color: Colors.white, fontSize: 12.5),
          ),
        ),
      ],
    );
  }

  static const _titleStyle = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: AppColors.heading,
  );
}
