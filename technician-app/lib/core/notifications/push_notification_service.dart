import 'package:onesignal_flutter/onesignal_flutter.dart';

import '../network/api_client.dart';

class PushNotificationService {
  PushNotificationService(this.api);

  final ApiClient api;
  bool _configured = false;

  Future<void> initialize() async {
    try {
      final response = await api.dio.get('app-config');
      final appId = response.data['onesignal_app_id']?.toString() ?? '';
      if (appId.isEmpty) return;
      OneSignal.initialize(appId);
      _configured = true;
    } catch (_) {
      _configured = false;
    }
  }

  Future<void> identify(dynamic userId) async {
    try {
      if (!_configured) await initialize();
      if (!_configured || userId == null) return;
      await OneSignal.login(userId.toString());
      await OneSignal.Notifications.requestPermission(false);
    } catch (_) {}
  }

  Future<void> logout() async {
    try {
      if (_configured) await OneSignal.logout();
    } catch (_) {}
  }
}
