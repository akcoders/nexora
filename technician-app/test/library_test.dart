import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:nexora_technician/core/i18n/app_language.dart';
import 'package:nexora_technician/core/network/api_client.dart';
import 'package:nexora_technician/core/theme/app_theme.dart';
import 'package:nexora_technician/core/widgets/ui.dart';
import 'package:nexora_technician/features/library/library_api.dart';
import 'package:nexora_technician/features/library/library_article_screen.dart';
import 'package:nexora_technician/features/library/library_body.dart';
import 'package:nexora_technician/features/library/library_models.dart';
import 'package:nexora_technician/features/library/library_screen.dart';
import 'package:nexora_technician/features/library/library_widgets.dart';
import 'package:provider/provider.dart';

Widget harness(Widget child) {
  const storage = FlutterSecureStorage();
  return ChangeNotifierProvider(
    create: (_) => AppLanguageController(ApiClient(storage), storage),
    child: MaterialApp(home: child),
  );
}

const filtersJson = {
  'categories': [
    {
      'id': 1,
      'name': 'Installation',
      'slug': 'installation',
      'icon': 'hammer',
      'color': 'primary',
      'description': 'Mounting, piping and commissioning.',
      'count': 3,
    },
    {
      'id': 6,
      'name': 'Tools & instruments',
      'slug': 'tools-instruments',
      'icon': 'gauge',
      'color': 'teal',
      'description': null,
      'count': 1,
    },
  ],
  'device_types': [
    {'code': 'split_ac', 'label': 'Split AC'},
    {'code': 'chiller', 'label': 'Chiller'},
  ],
  'manufacturers': [
    {'code': 'daikin', 'label': 'Daikin'},
  ],
  'content_types': [
    {'key': 'article', 'label': 'Written guide', 'icon': 'notebook-text'},
    {'key': 'video', 'label': 'Video', 'icon': 'youtube'},
  ],
};

Map<String, dynamic> summaryJson({
  int id = 7,
  String title = 'Vacuum and charge a split AC',
  bool cover = false,
}) => {
  'id': id,
  'title': title,
  'summary': 'Pull vacuum to 500 microns before releasing the charge.',
  'category': {
    'id': 1,
    'name': 'Installation',
    'slug': 'installation',
    'icon': 'hammer',
    'color': 'primary',
  },
  'device_type': {'code': 'split_ac', 'label': 'Split AC'},
  'manufacturer': null,
  'model_numbers': 'FTKF50, FTKM60',
  'tags': ['vacuum', 'r32'],
  'status': 'published',
  'is_featured': true,
  'has_steps': true,
  'counts': {'video': 2, 'document': 1, 'link': 0},
  'cover_url': cover
      ? 'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg'
      : null,
  'views_count': 12,
  'published_at': '2026-11-01T10:00:00+05:30',
  'updated_at': '2026-11-02T09:30:00+05:30',
};

class FakeLibraryApi extends LibraryApi {
  FakeLibraryApi({this.pages = const [], this.forbidden = false})
    : super(Dio());

  final List<LibraryPage> pages;
  final bool forbidden;
  final queries = <LibraryQuery>[];
  var filterCalls = 0;

  DioException get _forbidden => DioException(
    requestOptions: RequestOptions(path: 'library'),
    response: Response(
      requestOptions: RequestOptions(path: 'library'),
      statusCode: 403,
      data: {'message': 'You do not have access to the library.'},
    ),
  );

  @override
  Future<LibraryFilterOptions> filters() async {
    filterCalls++;
    if (forbidden) throw _forbidden;
    return LibraryFilterOptions.fromJson(filtersJson);
  }

  @override
  Future<LibraryPage> guides(LibraryQuery query, {int page = 1}) async {
    if (forbidden) throw _forbidden;
    queries.add(query);
    if (query.isFiltered) return const LibraryPage(items: []);
    return pages.isEmpty ? const LibraryPage(items: []) : pages[page - 1];
  }

  @override
  Future<LibraryArticle> guide(int id) async {
    if (forbidden) throw _forbidden;
    return LibraryArticle.fromJson({
      'data': {
        ...summaryJson(id: id),
        'full_summary': 'Full summary for the guide.',
        'body_html': '<ol><li>Fit the gauges</li><li>Pull vacuum</li></ol>',
        'videos': const [],
        'documents': const [],
        'links': const [],
        'related': const [],
        'author': {'name': 'Asha Admin'},
      },
    });
  }
}

void main() {
  group('library models', () {
    test('filters parse categories, masters and tone swatches', () {
      final options = LibraryFilterOptions.fromJson(filtersJson);
      expect(options.categories, hasLength(2));
      expect(options.totalGuides, 4);
      expect(options.categories.first.swatch.color, AppColors.primary);
      expect(options.categories.last.swatch.color, AppColors.accent);
      expect(options.deviceTypeLabel('chiller'), 'Chiller');
      expect(options.manufacturerLabel('daikin'), 'Daikin');
      expect(options.contentTypes.map((type) => type.key), [
        'article',
        'video',
      ]);
      // Missing lists fall back to safe defaults.
      final empty = LibraryFilterOptions.fromJson(null);
      expect(empty.categories, isEmpty);
      expect(empty.contentTypes, hasLength(4));
    });

    test('list page parses summaries and pagination meta', () {
      final page = LibraryPage.fromJson({
        'data': [
          summaryJson(),
          {'id': 'x'}, // Invalid row is skipped, not thrown.
          {'id': 9, 'title': null, 'tags': 'one, two'},
        ],
        'meta': {
          'current_page': 1,
          'last_page': 3,
          'per_page': 20,
          'total': 45,
        },
      });
      expect(page.items, hasLength(2));
      expect(page.hasMore, isTrue);
      expect(page.total, 45);
      final guide = page.items.first;
      expect(guide.category.name, 'Installation');
      expect(guide.deviceType?.label, 'Split AC');
      expect(guide.manufacturer, isNull);
      expect(guide.tags, ['vacuum', 'r32']);
      expect(guide.videoCount, 2);
      expect(guide.documentCount, 1);
      expect(guide.isFeatured, isTrue);
      expect(guide.hasSteps, isTrue);
      final sparse = page.items.last;
      expect(sparse.title, 'Untitled guide');
      expect(sparse.category, LibraryCategory.fallback);
      expect(sparse.tags, ['one', 'two']);
    });

    test('detail parses resources, related guides and authors', () {
      final article = LibraryArticle.fromJson({
        'data': {
          ...summaryJson(),
          'full_summary': null,
          'body_html': '<p>Step one</p>',
          'videos': [
            {
              'id': 1,
              'title': 'Charging demo',
              'video_id': null,
              'url': 'https://youtu.be/abcdefghijk',
              'embed_url': null,
              'thumbnail_url': null,
            },
          ],
          'documents': [
            {
              'id': 2,
              'title': 'Service manual',
              'file_name': 'manual.PDF',
              'extension': null,
              'size_label': '2.4 MB',
              'url': 'https://example.test/signed',
            },
          ],
          'links': [
            {'id': 3, 'title': null, 'url': 'https://www.daikin.co.in/support'},
            {'id': 4, 'title': 'Broken', 'url': null},
          ],
          'related': [summaryJson(id: 8, title: 'Flaring copper pipes')],
          'author': {'name': 'Asha Admin'},
          'updated_by': null,
        },
      });
      expect(article.id, 7);
      expect(article.hasBody, isTrue);
      expect(article.fullSummary, isNull);
      expect(article.videos.single.videoId, 'abcdefghijk');
      expect(
        article.videos.single.watchUrl,
        'https://www.youtube.com/watch?v=abcdefghijk',
      );
      expect(
        article.coverUrl,
        'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg',
      );
      expect(article.documents.single.extension, 'pdf');
      expect(article.links, hasLength(1));
      expect(article.links.single.host, 'daikin.co.in');
      expect(article.links.single.title, 'daikin.co.in');
      expect(article.related.single.title, 'Flaring copper pipes');
      expect(article.authorName, 'Asha Admin');
      expect(article.updatedByName, isNull);
      expect(
        LibraryArticle.fromJson({
          'data': {'id': 1, 'body_html': '<p>&nbsp;</p>'},
        }).hasBody,
        isFalse,
      );
    });

    test('signed document links know when they expire', () {
      String signed(DateTime at) =>
          'https://example.test/api/v1/library/files/4?expires=${at.millisecondsSinceEpoch ~/ 1000}&signature=abc';
      final now = DateTime.now();
      final fresh = LibraryDocument.fromMap({
        'id': 4,
        'url': signed(now.add(const Duration(minutes: 30))),
      });
      final stale = LibraryDocument.fromMap({
        'id': 4,
        'url': signed(now.subtract(const Duration(minutes: 1))),
      });
      final almost = LibraryDocument.fromMap({
        'id': 4,
        'url': signed(now.add(const Duration(seconds: 20))),
      });
      expect(fresh.linkExpired, isFalse);
      expect(stale.linkExpired, isTrue);
      expect(almost.linkExpired, isTrue);
      expect(
        LibraryDocument.fromMap({'id': 4, 'url': 'https://x.test/a.pdf'}),
        isA<LibraryDocument>().having((d) => d.linkExpired, 'expired', false),
      );
    });

    test('every category icon the web offers has an app icon', () {
      // LibraryCategoryRequest::ICONS on the backend.
      const webIcons = [
        'book-open', 'hammer', 'wrench', 'shield-check', 'stethoscope', //
        'hard-hat', 'gauge', 'graduation-cap', 'snowflake', 'fan',
        'thermometer', 'wind', 'air-vent', 'droplets', 'zap', 'plug', 'cog',
        'cpu', 'clipboard-check', 'triangle-alert', 'lightbulb', 'flame',
        'factory', 'circle-help',
      ];
      for (final name in webIcons) {
        expect(
          name == 'book-open' || libraryIcon(name) != LucideIcons.bookOpen,
          isTrue,
          reason: '$name falls back to the default icon',
        );
      }
    });

    test('youtube ids, thumbnails and query parameters', () {
      expect(
        youtubeIdFromUrl(
          'https://www.youtube-nocookie.com/embed/abcdefghijk?rel=0',
        ),
        'abcdefghijk',
      );
      expect(
        youtubeIdFromUrl('https://youtube.com/shorts/abcdefghijk'),
        'abcdefghijk',
      );
      expect(youtubeIdFromUrl('https://example.com/video'), isNull);
      expect(
        listThumbnail('https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg'),
        'https://i.ytimg.com/vi/abcdefghijk/mqdefault.jpg',
      );

      const query = LibraryQuery(
        search: '  E4 error ',
        categoryId: 3,
        manufacturer: 'daikin',
      );
      expect(query.toParameters(), {
        'q': 'E4 error',
        'category': 3,
        'manufacturer': 'daikin',
      });
      expect(query.sheetFilterCount, 1);
      final cleared = query.copyWith(manufacturer: () => null);
      expect(cleared.manufacturer, isNull);
      expect(cleared.categoryId, 3);
      expect(const LibraryQuery().isFiltered, isFalse);
      expect(query.clearSheetFilters().sheetFilterCount, 0);
    });
  });

  group('library widgets', () {
    testWidgets('article card shows category, title, equipment and counts', (
      tester,
    ) async {
      var tapped = false;
      final guide = LibraryArticleSummary.fromMap(summaryJson());
      await tester.pumpWidget(
        harness(
          Scaffold(
            body: Padding(
              padding: const EdgeInsets.all(16),
              child: LibraryArticleCard(
                guide: guide,
                onTap: () => tapped = true,
              ),
            ),
          ),
        ),
      );

      expect(find.text('INSTALLATION'), findsOneWidget);
      expect(find.byTooltip('Featured guide'), findsOneWidget);
      expect(find.text('Vacuum and charge a split AC'), findsOneWidget);
      expect(find.text('Split AC'), findsOneWidget);
      expect(find.text('2'), findsOneWidget); // videos
      expect(find.text('1'), findsOneWidget); // documents
      expect(find.byType(PlayBadge), findsNothing);
      await tester.tap(find.byType(LibraryArticleCard));
      expect(tapped, isTrue);
    });

    testWidgets('video guides get a play badge over the cover', (tester) async {
      final guide = LibraryArticleSummary.fromMap(summaryJson(cover: true));
      await tester.pumpWidget(
        harness(
          Scaffold(
            body: LibraryArticleCard(guide: guide, onTap: () {}),
          ),
        ),
      );
      expect(find.byType(PlayBadge), findsOneWidget);
      expect(find.text('2 videos'), findsOneWidget);
    });

    testWidgets('body renders checklists and YouTube embeds', (tester) async {
      await tester.pumpWidget(
        harness(
          const Scaffold(
            body: SingleChildScrollView(
              child: LibraryBody(
                html:
                    '<h2>Before you start</h2>'
                    '<ul class="nx-checklist">'
                    '<li data-checked="true">Isolate power</li>'
                    '<li data-checked="false">Check gauge</li>'
                    '</ul>'
                    '<iframe class="ql-video" src="https://www.youtube-nocookie.com/embed/abcdefghijk?rel=0"></iframe>',
              ),
            ),
          ),
        ),
      );
      await tester.pump();

      expect(find.byType(ChecklistItem), findsNWidgets(2));
      final items = tester
          .widgetList<ChecklistItem>(find.byType(ChecklistItem))
          .toList();
      expect(items.first.checked, isTrue);
      expect(items.last.checked, isFalse);
      expect(find.byType(InlineVideoCard), findsOneWidget);
      expect(find.text('Isolate power', findRichText: true), findsOneWidget);
    });
  });

  group('library body', () {
    testWidgets('numbered lists become steps with 4.1 style sub-steps', (
      tester,
    ) async {
      await tester.pumpWidget(
        harness(
          const Scaffold(
            body: SingleChildScrollView(
              child: LibraryBody(
                html:
                    '<ol><li>Isolate power</li><li>Open the panel</li>'
                    '<li class="ql-indent-1">Keep the screws safe</li>'
                    '<li class="ql-indent-1">Note the wiring</li>'
                    '<li><br></li>'
                    '<li>Remove the filter</li></ol>'
                    '<ul><li>Bullets keep their markers</li></ul>',
              ),
            ),
          ),
        ),
      );
      await tester.pump();

      final labels = tester
          .widgetList<StepBadge>(find.byType(StepBadge))
          .map((badge) => badge.label)
          .toList();
      expect(labels, ['1', '2', '2.1', '2.2', '3']);
      expect(find.byType(StepList), findsOneWidget);
      expect(find.text('Keep the screws safe', findRichText: true), findsOne);
      expect(
        find.text('Bullets keep their markers', findRichText: true),
        findsOne,
      );
    });

    testWidgets('quotes become tips and code wraps as selectable text', (
      tester,
    ) async {
      const command = 'Test mode: press TEMP + MODE together for 5 seconds';
      await tester.pumpWidget(
        harness(
          const Scaffold(
            body: SingleChildScrollView(
              child: LibraryBody(
                html:
                    '<blockquote>Never use high pressure on the fins.</blockquote>'
                    '<pre>$command</pre>',
              ),
            ),
          ),
        ),
      );
      await tester.pump();

      expect(find.byType(TipBox), findsOneWidget);
      expect(
        find.text('Never use high pressure on the fins.', findRichText: true),
        findsOne,
      );
      expect(find.byType(CodeBlock), findsOneWidget);
      expect(find.widgetWithText(SelectableText, command), findsOneWidget);
    });
  });

  group('library screen', () {
    testWidgets('lists guides with category chips and filters', (tester) async {
      tester.view.physicalSize = const Size(1170, 2532);
      tester.view.devicePixelRatio = 3;
      addTearDown(tester.view.reset);

      final api = FakeLibraryApi(
        pages: [
          LibraryPage(
            items: [
              LibraryArticleSummary.fromMap(summaryJson()),
              LibraryArticleSummary.fromMap(
                summaryJson(id: 8, title: 'Clean indoor coil'),
              ),
            ],
            total: 2,
          ),
        ],
      );
      await tester.pumpWidget(harness(LibraryScreen(api: api)));
      await tester.pump();
      await tester.pump();

      expect(find.text('How-to library'), findsOneWidget);
      expect(find.text('All guides'), findsWidgets);
      expect(find.text('Installation'), findsOneWidget); // category chip
      expect(
        find.text('Tools & instruments', skipOffstage: false),
        findsOneWidget,
      );
      expect(find.byType(LibraryArticleCard), findsNWidgets(2));
      expect(find.text('Clean indoor coil'), findsOneWidget);
      expect(api.filterCalls, 1, reason: 'filters are requested once');

      // Searching waits for the debounce, then shows the filtered empty state.
      await tester.enterText(find.byType(TextField), 'E4');
      await tester.pump(const Duration(milliseconds: 200));
      expect(api.queries, hasLength(1));
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      expect(api.queries.last.search, 'E4');
      expect(find.text('No guides match'), findsOneWidget);

      await tester.tap(find.text('Clear search'));
      await tester.pump();
      await tester.pump();
      expect(api.queries.last.isFiltered, isFalse);
      expect(find.byType(LibraryArticleCard), findsNWidgets(2));
      // Let the skeleton shimmer's start timer finish.
      await tester.pump(const Duration(milliseconds: 50));
    });

    testWidgets('a tag on a guide searches the library for it', (tester) async {
      tester.view.physicalSize = const Size(1170, 2532);
      tester.view.devicePixelRatio = 3;
      addTearDown(tester.view.reset);

      final api = FakeLibraryApi(
        pages: [
          LibraryPage(
            items: [LibraryArticleSummary.fromMap(summaryJson())],
            total: 1,
          ),
        ],
      );
      await tester.pumpWidget(harness(LibraryScreen(api: api)));
      await tester.pump();
      await tester.pump();

      await tester.tap(find.text('Vacuum and charge a split AC'));
      await tester.pumpAndSettle();
      expect(find.byType(LibraryArticleScreen), findsOneWidget);
      expect(find.byType(StepBadge), findsNWidgets(2));

      final tag = find.text('#r32');
      await tester.scrollUntilVisible(
        tag,
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(tag);
      await tester.pumpAndSettle();

      expect(find.byType(LibraryArticleScreen), findsNothing);
      expect(api.queries.last.search, 'r32');
      expect(find.widgetWithText(TextField, 'r32'), findsOneWidget);
      expect(find.text('No guides match'), findsOneWidget);
    });

    testWidgets('fits a small phone with large text', (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      tester.platformDispatcher.textScaleFactorTestValue = 1.3;
      addTearDown(tester.view.reset);
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);

      final api = FakeLibraryApi(
        pages: [
          LibraryPage(
            items: [
              LibraryArticleSummary.fromMap(summaryJson(cover: true)),
              LibraryArticleSummary.fromMap(
                summaryJson(id: 8, title: 'Clean indoor coil'),
              ),
            ],
            total: 2,
          ),
        ],
      );
      await tester.pumpWidget(harness(LibraryScreen(api: api)));
      await tester.pump();
      await tester.pump();
      expect(find.byType(LibraryArticleCard), findsWidgets);

      // Filter sheet.
      await tester.tap(find.byTooltip('Filters'));
      await tester.pumpAndSettle();
      expect(find.text('Filter guides'), findsOneWidget);
      await tester.tap(find.text('Split AC').last);
      await tester.pump();
      await tester.tap(find.text('Apply 1 filter'));
      await tester.pumpAndSettle();
      expect(api.queries.last.deviceType, 'split_ac');

      // Clear the filter, then open a guide.
      await tester.tap(find.text('Clear'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Clean indoor coil'));
      await tester.pumpAndSettle();
      expect(find.byType(LibraryArticleScreen), findsOneWidget);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -900));
      await tester.pumpAndSettle();
    });

    testWidgets('explains missing access on 403', (tester) async {
      await tester.pumpWidget(
        harness(LibraryScreen(api: FakeLibraryApi(forbidden: true))),
      );
      await tester.pump();
      await tester.pump();

      expect(find.text('Library access needed'), findsOneWidget);
      expect(find.byType(NoticeBox), findsOneWidget);
      expect(find.byType(LibraryArticleCard), findsNothing);
      await tester.pump(const Duration(milliseconds: 50));
    });
  });
}
