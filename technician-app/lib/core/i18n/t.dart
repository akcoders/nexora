import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'app_language.dart';

/// Drop-in replacement for [Text] that renders the English source string in the
/// technician's chosen interface language. Shows English (or the cached
/// translation) immediately and swaps in the translation once it arrives.
///
/// Usage: `T('Sign out', style: ...)` wherever `Text('Sign out', style: ...)` was.
class T extends StatelessWidget {
  const T(
    this.text, {
    super.key,
    this.style,
    this.textAlign,
    this.maxLines,
    this.overflow,
    this.softWrap,
  });

  final String text;
  final TextStyle? style;
  final TextAlign? textAlign;
  final int? maxLines;
  final TextOverflow? overflow;
  final bool? softWrap;

  @override
  Widget build(BuildContext context) {
    final language = context.watch<AppLanguageController>();
    final cached = language.cachedTranslation(text);
    if (cached != null) {
      return _text(cached);
    }
    return FutureBuilder<String>(
      future: language.translate(text),
      initialData: text,
      builder: (context, snapshot) => _text(snapshot.data ?? text),
    );
  }

  Text _text(String value) => Text(
    value,
    style: style,
    textAlign: textAlign,
    maxLines: maxLines,
    overflow: overflow,
    softWrap: softWrap,
  );
}

/// Convenience for places that need a translated string value (snack bars,
/// dialogs). Returns English when the translation is not cached yet.
extension TranslateContext on BuildContext {
  String tr(String text) =>
      read<AppLanguageController>().cachedTranslation(text) ?? text;

  Future<String> trAsync(String text) =>
      read<AppLanguageController>().translate(text);
}
