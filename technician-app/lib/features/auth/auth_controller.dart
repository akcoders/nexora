import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/network/api_client.dart';
import '../../core/notifications/push_notification_service.dart';

class AuthController extends ChangeNotifier {
  AuthController(this.api, this.storage, this.push);

  final ApiClient api;
  final FlutterSecureStorage storage;
  final PushNotificationService push;
  bool loading = false;
  bool authenticated = false;
  Map<String, dynamic>? user;

  Future<void> restore() async {
    final token = await storage.read(key: 'auth_token');
    if (token == null) return;
    try {
      final response = await api.dio.get('auth/me');
      user = Map<String, dynamic>.from(response.data['user']);
      authenticated = true;
      await push.identify(user?['id']);
    } catch (_) {
      await storage.delete(key: 'auth_token');
    }
    notifyListeners();
  }

  Future<String?> login(String email, String password) async {
    loading = true;
    notifyListeners();
    try {
      final response = await api.dio.post(
        'auth/login',
        data: {'email': email, 'password': password},
      );
      await storage.write(key: 'auth_token', value: response.data['token']);
      user = Map<String, dynamic>.from(response.data['user']);
      authenticated = true;
      await push.identify(user?['id']);
      return null;
    } on DioException catch (error) {
      final data = error.response?.data;
      if (data is Map && data['message'] != null) {
        return data['message'].toString();
      }
      return 'Unable to sign in. Check your connection and try again.';
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    try {
      await api.dio.post('auth/logout');
    } catch (_) {}
    await storage.delete(key: 'auth_token');
    await push.logout();
    authenticated = false;
    user = null;
    notifyListeners();
  }
}
