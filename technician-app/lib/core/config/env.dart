import 'package:flutter/foundation.dart';

class Env {
  const Env._();

  static const String prodUrl = 'https://nexora.webignitors.in/api/v1/';
  static const String emulatorUrl = 'http://10.0.2.2:8000/api/v1/';
  static const String configuredUrl = String.fromEnvironment('NEXORA_API_URL');

  static String get baseUrl {
    if (kReleaseMode) return prodUrl;
    return configuredUrl.isNotEmpty ? configuredUrl : emulatorUrl;
  }
}
