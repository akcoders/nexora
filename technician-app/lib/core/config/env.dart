class Env {
  const Env._();

  static const String prodUrl = 'https://nexora.webignitors.in/api/v1/';
  static const String configuredUrl = String.fromEnvironment('NEXORA_API_URL');

  static String get baseUrl {
    return configuredUrl.isNotEmpty ? configuredUrl : prodUrl;
  }
}
