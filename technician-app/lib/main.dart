import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:provider/provider.dart';

import 'core/i18n/app_language.dart';
import 'core/network/api_client.dart';
import 'core/notifications/push_notification_service.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/login_screen.dart';
import 'features/home/app_shell.dart';
import 'features/splash/splash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  const storage = FlutterSecureStorage();
  final api = ApiClient(storage);
  final push = PushNotificationService(api);
  final language = AppLanguageController(api, storage);
  await Future.wait([push.initialize(), language.restore()]);
  runApp(NexoraApp(storage: storage, api: api, push: push, language: language));
}

class NexoraApp extends StatelessWidget {
  const NexoraApp({
    super.key,
    required this.storage,
    required this.api,
    required this.push,
    required this.language,
  });

  final FlutterSecureStorage storage;
  final ApiClient api;
  final PushNotificationService push;
  final AppLanguageController language;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<FlutterSecureStorage>.value(value: storage),
        Provider<ApiClient>.value(value: api),
        Provider<PushNotificationService>.value(value: push),
        ChangeNotifierProvider<AppLanguageController>.value(value: language),
        ChangeNotifierProvider(
          create: (_) => AuthController(api, storage, push, language),
        ),
      ],
      child: MaterialApp(
        title: 'Nexora',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        restorationScopeId: 'nexora',
        home: const SplashScreen(),
        routes: {
          '/login': (_) => const LoginScreen(),
          '/home': (_) => const AppShell(),
        },
      ),
    );
  }
}
