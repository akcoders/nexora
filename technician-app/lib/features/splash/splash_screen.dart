import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_info.dart';
import '../../core/i18n/t.dart';
import '../../core/theme/app_theme.dart';
import '../auth/auth_controller.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    final auth = context.read<AuthController>();
    await auth.restore();
    if (!mounted) return;
    Navigator.pushReplacementNamed(
      context,
      auth.authenticated ? '/home' : '/login',
    );
  }

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
    value: AppTheme.overlay.copyWith(
      statusBarIconBrightness: Brightness.light,
      statusBarBrightness: Brightness.dark,
      systemNavigationBarColor: AppColors.navy,
      systemNavigationBarIconBrightness: Brightness.light,
    ),
    child: Scaffold(
      body: Container(
        width: double.infinity,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [AppColors.navy, AppColors.navy2],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              const Spacer(flex: 3),
              Container(
                    width: 88,
                    height: 88,
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.navyActive.withValues(alpha: .25),
                          blurRadius: 36,
                          spreadRadius: 4,
                        ),
                      ],
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.asset(
                        'assets/images/classic-app-icon.png',
                        fit: BoxFit.cover,
                      ),
                    ),
                  )
                  .animate()
                  .fadeIn(duration: 500.ms)
                  .scale(
                    begin: const Offset(.85, .85),
                    curve: Curves.easeOutBack,
                  ),
              const SizedBox(height: 26),
              const Text(
                'NEXORA',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 4,
                ),
              ).animate().fadeIn(delay: 200.ms).slideY(begin: .3),
              const SizedBox(height: 6),
              const T(
                'FIELD SERVICE',
                style: TextStyle(
                  color: AppColors.navyText,
                  fontSize: 12,
                  letterSpacing: 2.4,
                  fontWeight: FontWeight.w600,
                ),
              ).animate().fadeIn(delay: 350.ms),
              const Spacer(flex: 2),
              const SizedBox(
                width: 140,
                child: LinearProgressIndicator(
                  color: AppColors.navyActive,
                  backgroundColor: Colors.white12,
                  minHeight: 3,
                ),
              ),
              const SizedBox(height: 28),
              const T(
                'Reliable service, connected teams',
                style: TextStyle(color: AppColors.navyMuted, fontSize: 12),
              ),
              const SizedBox(height: 4),
              const Text(
                'v${AppInfo.version}',
                style: TextStyle(color: AppColors.navyMuted, fontSize: 11),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    ),
  );
}
