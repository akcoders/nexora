import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../network/api_client.dart';

/// One language the technician can switch the app to.
class AppLanguage {
  const AppLanguage({required this.name, required this.code});

  final String name;
  final String code;

  static const english = AppLanguage(name: 'English', code: 'en');

  factory AppLanguage.fromJson(Map<String, dynamic> json) => AppLanguage(
    name: json['name']?.toString() ?? 'English',
    code: json['code']?.toString() ?? 'en',
  );
}

/// Holds the interface language, the languages recorded on the employee
/// master (`user.language.known` from the API) and a small translation cache.
///
/// Strings are translated on demand with the free Google Translate endpoint
/// (`translate.googleapis.com`, client=gtx). English is the source language of
/// every string in the app, so `translate('Sign out')` returns the string as is
/// while English is active and a cached translation afterwards.
class AppLanguageController extends ChangeNotifier {
  AppLanguageController(this.api, this.storage)
    : _translator = Dio(
        BaseOptions(
          baseUrl: 'https://translate.googleapis.com/',
          connectTimeout: const Duration(seconds: 8),
          receiveTimeout: const Duration(seconds: 8),
        ),
      );

  static const _storageKey = 'app_language';
  static const _cacheKey = 'app_language_cache';

  final ApiClient api;
  final FlutterSecureStorage storage;
  final Dio _translator;

  AppLanguage _current = AppLanguage.english;
  List<AppLanguage> _known = const [AppLanguage.english];
  final Map<String, Map<String, String>> _cache = {};
  final Map<String, Future<String>> _inFlight = {};
  bool _restored = false;

  AppLanguage get current => _current;
  List<AppLanguage> get known => _known;
  bool get isEnglish => _current.code == 'en';

  /// Restore the last chosen language and translation cache from storage.
  Future<void> restore() async {
    if (_restored) return;
    _restored = true;
    try {
      final saved = await storage.read(key: _storageKey);
      if (saved != null && saved.isNotEmpty) {
        final decoded = jsonDecode(saved) as Map<String, dynamic>;
        _current = AppLanguage.fromJson(decoded);
      }
      final cached = await storage.read(key: _cacheKey);
      if (cached != null && cached.isNotEmpty) {
        final decoded = jsonDecode(cached) as Map<String, dynamic>;
        decoded.forEach((code, entries) {
          _cache[code] = Map<String, String>.from(entries as Map);
        });
      }
    } catch (_) {
      // Corrupt storage: fall back to English.
      _current = AppLanguage.english;
    }
    notifyListeners();
  }

  /// Apply the languages and preference that came with the signed-in user.
  void syncFromUser(Map<String, dynamic>? user) {
    final language = user?['language'];
    if (language is! Map) return;
    final known = (language['known'] as List?)
        ?.whereType<Map>()
        .map((item) => AppLanguage.fromJson(Map<String, dynamic>.from(item)))
        .toList();
    if (known != null && known.isNotEmpty) {
      _known = known;
    }
    final preferredCode = language['preferred_code']?.toString();
    final preferredName = language['preferred']?.toString();
    // The language chosen on the employee master (or last picked in any app) wins.
    if (preferredCode != null && preferredCode != _current.code) {
      _current = AppLanguage(
        name: preferredName ?? preferredCode,
        code: preferredCode,
      );
      _persistCurrent();
    }
    notifyListeners();
  }

  /// Switch the interface language and remember it on the server too.
  Future<void> select(AppLanguage language) async {
    _current = language;
    notifyListeners();
    await _persistCurrent();
    try {
      await api.dio.patch('auth/language', data: {'language': language.name});
    } catch (_) {
      // Offline: the local choice still applies and syncs on the next change.
    }
  }

  /// Translate an English UI string into the active language.
  Future<String> translate(String text) async {
    if (isEnglish || text.trim().isEmpty) return text;
    final code = _current.code;
    final cached = _cache[code]?[text];
    if (cached != null) return cached;
    final key = '$code::$text';
    return _inFlight[key] ??= _fetch(code, text).whenComplete(() {
      _inFlight.remove(key);
    });
  }

  /// Cached translation when one is already known (no network).
  String? cachedTranslation(String text) {
    if (isEnglish) return text;
    return _cache[_current.code]?[text];
  }

  Future<String> _fetch(String code, String text) async {
    try {
      final response = await _translator.get(
        'translate_a/single',
        queryParameters: {
          'client': 'gtx',
          'sl': 'en',
          'tl': code,
          'dt': 't',
          'q': text,
        },
      );
      final data = response.data;
      final decoded = data is String ? jsonDecode(data) : data;
      final segments = (decoded as List).first as List;
      final translated = segments
          .map((segment) => (segment as List).first?.toString() ?? '')
          .join();
      if (translated.isEmpty) return text;
      (_cache[code] ??= {})[text] = translated;
      _persistCache();
      return translated;
    } catch (_) {
      return text;
    }
  }

  Future<void> _persistCurrent() async {
    try {
      await storage.write(
        key: _storageKey,
        value: jsonEncode({'name': _current.name, 'code': _current.code}),
      );
    } catch (_) {}
  }

  Future<void> _persistCache() async {
    try {
      await storage.write(key: _cacheKey, value: jsonEncode(_cache));
    } catch (_) {}
  }

  Future<void> clear() async {
    _current = AppLanguage.english;
    _known = const [AppLanguage.english];
    notifyListeners();
    try {
      await storage.delete(key: _storageKey);
    } catch (_) {}
  }
}
