import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';

// Null-safe readers for the loosely typed JSON the API returns.

String? _string(dynamic value) {
  if (value == null) return null;
  final text = value.toString().trim();
  return text.isEmpty ? null : text;
}

int _int(dynamic value, [int fallback = 0]) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

bool _bool(dynamic value) =>
    value == true || value == 1 || value == '1' || value == 'true';

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map(Map<String, dynamic>.from).toList()
    : const [];

DateTime? _date(dynamic value) =>
    DateTime.tryParse(value?.toString() ?? '')?.toLocal();

/// Colour pair for the tone keys the web admin lets the owner pick for a
/// category (`primary`, `info`, `success`, `warning`, `danger`, `purple`,
/// `teal`, `secondary`).
@immutable
class LibrarySwatch {
  const LibrarySwatch(this.color, this.soft);

  final Color color;
  final Color soft;

  static LibrarySwatch of(Tone tone) => LibrarySwatch(tone.color, tone.soft);

  static const teal = LibrarySwatch(AppColors.accent, Color(0xFFDDF3F2));

  static LibrarySwatch fromKey(String? key) => switch (key) {
    'teal' => teal,
    'secondary' || 'slate' => of(Tone.slate),
    _ => of(toneFromName(key)),
  };
}

/// Lucide icon for the kebab-case names stored on categories and resources.
/// Unknown names fall back to an open book.
IconData libraryIcon(String? name) => switch (name) {
  'hammer' => LucideIcons.hammer,
  'shield-check' => LucideIcons.shieldCheck,
  'stethoscope' => LucideIcons.stethoscope,
  'wrench' => LucideIcons.wrench,
  'hard-hat' => LucideIcons.hardHat,
  'gauge' => LucideIcons.gauge,
  'book-open' => LucideIcons.bookOpen,
  'book-open-text' => LucideIcons.bookOpenText,
  'graduation-cap' => LucideIcons.graduationCap,
  'notebook-text' => LucideIcons.notebookText,
  'youtube' ||
  'video' ||
  'circle-play' ||
  'square-play' => LucideIcons.circlePlay,
  'file-text' => LucideIcons.fileText,
  'file-type' => LucideIcons.fileType,
  'file-spreadsheet' => LucideIcons.fileSpreadsheet,
  'presentation' => LucideIcons.presentation,
  'file-image' => LucideIcons.fileImage,
  'file' => LucideIcons.file,
  'link' => LucideIcons.link,
  'globe' => LucideIcons.globe,
  'air-vent' => LucideIcons.airVent,
  'fan' => LucideIcons.fan,
  'wind' => LucideIcons.wind,
  'thermometer' => LucideIcons.thermometer,
  'snowflake' => LucideIcons.snowflake,
  'zap' => LucideIcons.zap,
  'plug' => LucideIcons.plug,
  'cpu' => LucideIcons.cpu,
  'settings' => LucideIcons.settings,
  'cog' => LucideIcons.cog,
  'tool-case' || 'toolbox' => LucideIcons.briefcaseBusiness,
  'clipboard-check' => LucideIcons.clipboardCheck,
  'clipboard-list' => LucideIcons.clipboardList,
  'list-checks' => LucideIcons.listChecks,
  'triangle-alert' || 'alert-triangle' => LucideIcons.triangleAlert,
  'lightbulb' => LucideIcons.lightbulb,
  'droplets' => LucideIcons.droplets,
  'flame' => LucideIcons.flame,
  'factory' => LucideIcons.factory,
  'building-2' => LucideIcons.building2,
  'users' => LucideIcons.users,
  'shield' => LucideIcons.shield,
  'info' => LucideIcons.info,
  'circle-help' || 'circle-question-mark' => LucideIcons.circleQuestionMark,
  'library' || 'library-big' => LucideIcons.libraryBig,
  _ => LucideIcons.bookOpen,
};

/// Icon and swatch for a document by file extension.
({IconData icon, LibrarySwatch swatch}) documentStyle(String? extension) =>
    switch (extension?.toLowerCase()) {
      'pdf' => (
        icon: LucideIcons.fileText,
        swatch: LibrarySwatch.of(Tone.danger),
      ),
      'doc' || 'docx' || 'txt' => (
        icon: LucideIcons.fileType,
        swatch: LibrarySwatch.of(Tone.primary),
      ),
      'xls' || 'xlsx' || 'csv' => (
        icon: LucideIcons.fileSpreadsheet,
        swatch: LibrarySwatch.of(Tone.success),
      ),
      'ppt' || 'pptx' => (
        icon: LucideIcons.presentation,
        swatch: LibrarySwatch.of(Tone.orange),
      ),
      'jpg' || 'jpeg' || 'png' || 'webp' => (
        icon: LucideIcons.fileImage,
        swatch: LibrarySwatch.of(Tone.purple),
      ),
      _ => (icon: LucideIcons.file, swatch: LibrarySwatch.of(Tone.slate)),
    };

/// YouTube video id from an embed, watch, short or youtu.be link.
String? youtubeIdFromUrl(String? url) {
  if (url == null || url.isEmpty) return null;
  final match = RegExp(
    r'(?:youtu\.be/|/embed/|/shorts/|/live/|/v/|[?&]v=)([A-Za-z0-9_-]{11})',
  ).firstMatch(url);
  return match?.group(1);
}

String youtubeWatchUrl(String videoId) =>
    'https://www.youtube.com/watch?v=$videoId';

String youtubeThumbnailUrl(String videoId) =>
    'https://i.ytimg.com/vi/$videoId/hqdefault.jpg';

/// Code + label pair from a master list (device type, manufacturer).
@immutable
class LibraryOption {
  const LibraryOption({required this.code, required this.label});

  final String code;
  final String label;

  static LibraryOption? tryParse(dynamic json) {
    final map = _map(json);
    final code = _string(map['code']);
    if (code == null) return null;
    return LibraryOption(code: code, label: _string(map['label']) ?? code);
  }

  @override
  bool operator ==(Object other) =>
      other is LibraryOption && other.code == code;

  @override
  int get hashCode => code.hashCode;
}

@immutable
class LibraryCategory {
  const LibraryCategory({
    required this.id,
    required this.name,
    this.slug,
    this.icon,
    this.color,
    this.description,
    this.count = 0,
  });

  final int id;
  final String name;
  final String? slug;
  final String? icon;
  final String? color;
  final String? description;
  final int count;

  IconData get iconData => libraryIcon(icon);
  LibrarySwatch get swatch => LibrarySwatch.fromKey(color);

  static LibraryCategory? tryParse(dynamic json) {
    final map = _map(json);
    final id = _int(map['id'], -1);
    if (id < 0) return null;
    return LibraryCategory(
      id: id,
      name: _string(map['name']) ?? 'General',
      slug: _string(map['slug']),
      icon: _string(map['icon']),
      color: _string(map['color']),
      description: _string(map['description']),
      count: _int(map['count']),
    );
  }

  static const fallback = LibraryCategory(id: 0, name: 'General');
}

/// Content filter: written guide, video, document or link.
@immutable
class LibraryContentType {
  const LibraryContentType({required this.key, required this.label, this.icon});

  final String key;
  final String label;
  final String? icon;

  IconData get iconData => switch (key) {
    'article' => LucideIcons.notebookText,
    'video' => LucideIcons.circlePlay,
    'document' => LucideIcons.fileText,
    'link' => LucideIcons.link,
    _ => libraryIcon(icon),
  };

  static const defaults = [
    LibraryContentType(key: 'article', label: 'Written guide'),
    LibraryContentType(key: 'video', label: 'Video'),
    LibraryContentType(key: 'document', label: 'Document'),
    LibraryContentType(key: 'link', label: 'Web link'),
  ];

  static LibraryContentType? tryParse(dynamic json) {
    final map = _map(json);
    final key = _string(map['key']);
    if (key == null) return null;
    return LibraryContentType(
      key: key,
      label: _string(map['label']) ?? key,
      icon: _string(map['icon']),
    );
  }
}

/// Everything the browse screen can filter by (`GET library/filters`).
@immutable
class LibraryFilterOptions {
  const LibraryFilterOptions({
    this.categories = const [],
    this.deviceTypes = const [],
    this.manufacturers = const [],
    this.contentTypes = LibraryContentType.defaults,
  });

  final List<LibraryCategory> categories;
  final List<LibraryOption> deviceTypes;
  final List<LibraryOption> manufacturers;
  final List<LibraryContentType> contentTypes;

  int get totalGuides =>
      categories.fold(0, (total, category) => total + category.count);

  factory LibraryFilterOptions.fromJson(dynamic json) {
    final map = _map(json);
    final content = _maps(
      map['content_types'],
    ).map(LibraryContentType.tryParse).whereType<LibraryContentType>().toList();
    return LibraryFilterOptions(
      categories: _maps(
        map['categories'],
      ).map(LibraryCategory.tryParse).whereType<LibraryCategory>().toList(),
      deviceTypes: _maps(
        map['device_types'],
      ).map(LibraryOption.tryParse).whereType<LibraryOption>().toList(),
      manufacturers: _maps(
        map['manufacturers'],
      ).map(LibraryOption.tryParse).whereType<LibraryOption>().toList(),
      contentTypes: content.isEmpty ? LibraryContentType.defaults : content,
    );
  }

  LibraryCategory? categoryById(int? id) {
    for (final category in categories) {
      if (category.id == id) return category;
    }
    return null;
  }

  String? deviceTypeLabel(String? code) => _label(deviceTypes, code);
  String? manufacturerLabel(String? code) => _label(manufacturers, code);

  String? contentLabel(String? key) {
    for (final type in contentTypes) {
      if (type.key == key) return type.label;
    }
    return key;
  }

  static String? _label(List<LibraryOption> options, String? code) {
    if (code == null) return null;
    for (final option in options) {
      if (option.code == code) return option.label;
    }
    return code;
  }
}

/// Current search text and filters on the browse screen.
@immutable
class LibraryQuery {
  const LibraryQuery({
    this.search = '',
    this.categoryId,
    this.deviceType,
    this.manufacturer,
    this.content,
  });

  final String search;
  final int? categoryId;
  final String? deviceType;
  final String? manufacturer;
  final String? content;

  /// Filters chosen in the sheet (category has its own chip row).
  int get sheetFilterCount => [
    deviceType,
    manufacturer,
    content,
  ].where((value) => value != null).length;

  bool get isFiltered =>
      search.trim().isNotEmpty || categoryId != null || sheetFilterCount > 0;

  LibraryQuery copyWith({
    String? search,
    int? Function()? categoryId,
    String? Function()? deviceType,
    String? Function()? manufacturer,
    String? Function()? content,
  }) => LibraryQuery(
    search: search ?? this.search,
    categoryId: categoryId == null ? this.categoryId : categoryId(),
    deviceType: deviceType == null ? this.deviceType : deviceType(),
    manufacturer: manufacturer == null ? this.manufacturer : manufacturer(),
    content: content == null ? this.content : content(),
  );

  LibraryQuery clearSheetFilters() =>
      LibraryQuery(search: search, categoryId: categoryId);

  Map<String, dynamic> toParameters() => {
    if (search.trim().isNotEmpty) 'q': search.trim(),
    'category': ?categoryId,
    'device_type': ?deviceType,
    'manufacturer': ?manufacturer,
    'content': ?content,
  };

  @override
  bool operator ==(Object other) =>
      other is LibraryQuery &&
      other.search == search &&
      other.categoryId == categoryId &&
      other.deviceType == deviceType &&
      other.manufacturer == manufacturer &&
      other.content == content;

  @override
  int get hashCode =>
      Object.hash(search, categoryId, deviceType, manufacturer, content);
}

/// Card data for one guide (list rows, related guides).
@immutable
class LibraryArticleSummary {
  const LibraryArticleSummary({
    required this.id,
    required this.title,
    this.summary,
    this.category = LibraryCategory.fallback,
    this.deviceType,
    this.manufacturer,
    this.modelNumbers,
    this.tags = const [],
    this.status = 'published',
    this.isFeatured = false,
    this.hasSteps = false,
    this.videoCount = 0,
    this.documentCount = 0,
    this.linkCount = 0,
    this.coverUrl,
    this.viewsCount = 0,
    this.publishedAt,
    this.updatedAt,
  });

  final int id;
  final String title;
  final String? summary;
  final LibraryCategory category;
  final LibraryOption? deviceType;
  final LibraryOption? manufacturer;
  final String? modelNumbers;
  final List<String> tags;
  final String status;
  final bool isFeatured;
  final bool hasSteps;
  final int videoCount;
  final int documentCount;
  final int linkCount;
  final String? coverUrl;
  final int viewsCount;
  final DateTime? publishedAt;
  final DateTime? updatedAt;

  bool get isDraft => status == 'draft';

  static LibraryArticleSummary? tryParse(dynamic json) {
    final map = _map(json);
    final id = _int(map['id'], -1);
    if (id < 0) return null;
    return LibraryArticleSummary.fromMap(map);
  }

  factory LibraryArticleSummary.fromMap(Map<String, dynamic> map) {
    final counts = _map(map['counts']);
    final rawTags = map['tags'];
    return LibraryArticleSummary(
      id: _int(map['id']),
      title: _string(map['title']) ?? 'Untitled guide',
      summary: _string(map['summary']),
      category:
          LibraryCategory.tryParse(map['category']) ?? LibraryCategory.fallback,
      deviceType: LibraryOption.tryParse(map['device_type']),
      manufacturer: LibraryOption.tryParse(map['manufacturer']),
      modelNumbers: _string(map['model_numbers']),
      tags: rawTags is List
          ? rawTags.map(_string).whereType<String>().toList()
          : (_string(rawTags)?.split(',') ?? const [])
                .map((tag) => tag.trim())
                .where((tag) => tag.isNotEmpty)
                .toList(),
      status: _string(map['status']) ?? 'published',
      isFeatured: _bool(map['is_featured']),
      hasSteps: _bool(map['has_steps']),
      videoCount: _int(counts['video']),
      documentCount: _int(counts['document']),
      linkCount: _int(counts['link']),
      coverUrl: _string(map['cover_url']),
      viewsCount: _int(map['views_count']),
      publishedAt: _date(map['published_at']),
      updatedAt: _date(map['updated_at']),
    );
  }
}

@immutable
class LibraryVideo {
  const LibraryVideo({
    required this.id,
    required this.title,
    this.videoId,
    this.url,
    this.embedUrl,
    this.thumbnailUrl,
  });

  final int id;
  final String title;
  final String? videoId;
  final String? url;
  final String? embedUrl;
  final String? thumbnailUrl;

  /// Link that opens the YouTube app or browser.
  String? get watchUrl =>
      videoId != null ? youtubeWatchUrl(videoId!) : (url ?? embedUrl);

  String? get thumbnail =>
      thumbnailUrl ?? (videoId == null ? null : youtubeThumbnailUrl(videoId!));

  factory LibraryVideo.fromMap(Map<String, dynamic> map) {
    final url = _string(map['url']);
    final embed = _string(map['embed_url']);
    return LibraryVideo(
      id: _int(map['id']),
      title: _string(map['title']) ?? 'YouTube video',
      videoId:
          _string(map['video_id']) ??
          youtubeIdFromUrl(url) ??
          youtubeIdFromUrl(embed),
      url: url,
      embedUrl: embed,
      thumbnailUrl: _string(map['thumbnail_url']),
    );
  }
}

@immutable
class LibraryDocument {
  const LibraryDocument({
    required this.id,
    required this.title,
    this.fileName,
    this.extension,
    this.sizeLabel,
    this.mimeType,
    this.url,
  });

  final int id;
  final String title;
  final String? fileName;
  final String? extension;
  final String? sizeLabel;
  final String? mimeType;

  /// Temporary signed link to the file.
  final String? url;

  /// When the signed link stops working (its `expires` query parameter).
  DateTime? get expiresAt {
    final expires = int.tryParse(
      Uri.tryParse(url ?? '')?.queryParameters['expires'] ?? '',
    );
    return expires == null
        ? null
        : DateTime.fromMillisecondsSinceEpoch(expires * 1000);
  }

  /// True when the signed link has expired or is about to (one minute of
  /// margin for clock drift), so a fresh one must be fetched before opening.
  bool get linkExpired {
    final expiry = expiresAt;
    return expiry != null &&
        DateTime.now().isAfter(expiry.subtract(const Duration(minutes: 1)));
  }

  factory LibraryDocument.fromMap(Map<String, dynamic> map) {
    final fileName = _string(map['file_name']);
    final extension =
        _string(map['extension']) ??
        (fileName != null && fileName.contains('.')
            ? fileName.split('.').last.toLowerCase()
            : null);
    return LibraryDocument(
      id: _int(map['id']),
      title: _string(map['title']) ?? fileName ?? 'Document',
      fileName: fileName,
      extension: extension,
      sizeLabel: _string(map['size_label']),
      mimeType: _string(map['mime_type']),
      url: _string(map['url']),
    );
  }
}

@immutable
class LibraryLink {
  const LibraryLink({
    required this.id,
    required this.title,
    required this.url,
    this.host,
  });

  final int id;
  final String title;
  final String url;
  final String? host;

  static LibraryLink? tryParse(Map<String, dynamic> map) {
    final url = _string(map['url']);
    if (url == null) return null;
    final host =
        _string(map['host']) ??
        Uri.tryParse(url)?.host.replaceFirst(RegExp(r'^www\.'), '');
    return LibraryLink(
      id: _int(map['id']),
      title: _string(map['title']) ?? host ?? url,
      url: url,
      host: host,
    );
  }
}

/// Full guide (`GET library/{id}`).
@immutable
class LibraryArticle {
  const LibraryArticle({
    required this.summary,
    this.fullSummary,
    this.bodyHtml = '',
    this.videos = const [],
    this.documents = const [],
    this.links = const [],
    this.related = const [],
    this.authorName,
    this.updatedByName,
  });

  final LibraryArticleSummary summary;

  /// The owner's full summary (the card summary is a shortened excerpt that
  /// falls back to the start of the steps).
  final String? fullSummary;
  final String bodyHtml;
  final List<LibraryVideo> videos;
  final List<LibraryDocument> documents;
  final List<LibraryLink> links;
  final List<LibraryArticleSummary> related;
  final String? authorName;
  final String? updatedByName;

  int get id => summary.id;
  String get title => summary.title;

  bool get hasBody =>
      bodyHtml.replaceAll(RegExp(r'<[^>]*>|&nbsp;|\s'), '').isNotEmpty ||
      bodyHtml.contains('<img') ||
      bodyHtml.contains('<iframe');

  /// Thumbnail for the header: the first video, else the summary cover.
  String? get coverUrl =>
      (videos.isEmpty ? null : videos.first.thumbnail) ?? summary.coverUrl;

  factory LibraryArticle.fromJson(dynamic json) {
    final root = _map(json);
    final map = root['data'] is Map ? _map(root['data']) : root;
    return LibraryArticle(
      summary: LibraryArticleSummary.fromMap(map),
      fullSummary: map.containsKey('full_summary')
          ? _string(map['full_summary'])
          : _string(map['summary']),
      bodyHtml: _string(map['body_html']) ?? '',
      videos: _maps(map['videos']).map(LibraryVideo.fromMap).toList(),
      documents: _maps(map['documents']).map(LibraryDocument.fromMap).toList(),
      links: _maps(
        map['links'],
      ).map(LibraryLink.tryParse).whereType<LibraryLink>().toList(),
      related: _maps(map['related'])
          .map(LibraryArticleSummary.tryParse)
          .whereType<LibraryArticleSummary>()
          .toList(),
      authorName: _string(_map(map['author'])['name']),
      updatedByName: _string(_map(map['updated_by'])['name']),
    );
  }
}

/// One page of guides from `GET library`.
@immutable
class LibraryPage {
  const LibraryPage({
    required this.items,
    this.currentPage = 1,
    this.lastPage = 1,
    this.perPage = 20,
    this.total = 0,
  });

  final List<LibraryArticleSummary> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  factory LibraryPage.fromJson(dynamic json) {
    final map = _map(json);
    final items = _maps(map['data'])
        .map(LibraryArticleSummary.tryParse)
        .whereType<LibraryArticleSummary>()
        .toList();
    final meta = _map(map['meta']);
    return LibraryPage(
      items: items,
      currentPage: _int(meta['current_page'], 1),
      lastPage: _int(meta['last_page'], 1),
      perPage: _int(meta['per_page'], 20),
      total: _int(meta['total'], items.length),
    );
  }
}
