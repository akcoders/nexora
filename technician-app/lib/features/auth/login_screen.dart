import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_info.dart';
import '../../core/i18n/t.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'auth_controller.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final formKey = GlobalKey<FormState>();
  // Demo credentials are only prefilled in debug builds.
  final email = TextEditingController(
    text: kDebugMode ? 'technician@nexora.test' : '',
  );
  final password = TextEditingController(text: kDebugMode ? 'password' : '');
  bool obscure = true;
  String? error;

  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    FocusScope.of(context).unfocus();
    if (!formKey.currentState!.validate()) return;
    setState(() => error = null);
    final message = await context.read<AuthController>().login(
      email.text.trim(),
      password.text,
    );
    if (!mounted) return;
    if (message == null) {
      Navigator.pushNamedAndRemoveUntil(context, '/home', (_) => false);
    } else {
      setState(() => error = message);
    }
  }

  void forgotPassword() => showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: const Icon(LucideIcons.keyRound, color: AppColors.primary),
      title: const T('Forgot your password?'),
      content: const T(
        'Passwords for field staff are managed by your administrator. Contact HR or your service manager to reset it.',
      ),
      actions: [
        FilledButton(
          onPressed: () => Navigator.pop(dialogContext),
          child: const T('OK'),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final loading = context.watch<AuthController>().loading;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: AppTheme.overlay.copyWith(
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: SingleChildScrollView(
          child: Column(
            children: [
              Container(
                width: double.infinity,
                padding: EdgeInsets.fromLTRB(
                  24,
                  MediaQuery.paddingOf(context).top + 36,
                  24,
                  64,
                ),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      AppColors.navy,
                      AppColors.navy2,
                      Color(0xFF1F3A6B),
                    ],
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 56,
                      height: 56,
                      padding: const EdgeInsets.all(3),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(AppRadius.lg),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        child: Image.asset(
                          'assets/images/classic-app-icon.png',
                          fit: BoxFit.cover,
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),
                    const Text(
                      'NEXORA',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 3,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const T(
                      'Field service · Attendance · HR',
                      style: TextStyle(
                        color: AppColors.navyText,
                        fontSize: 13.5,
                      ),
                    ),
                  ],
                ),
              ),
              Transform.translate(
                offset: const Offset(0, -36),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Card(
                    elevation: 0,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
                      child: Form(
                        key: formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const T(
                              'Sign in',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w700,
                                color: AppColors.heading,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const T(
                              'Use your work email to view jobs, complaints and attendance.',
                              style: TextStyle(
                                color: AppColors.muted,
                                fontSize: 13,
                                height: 1.45,
                              ),
                            ),
                            const SizedBox(height: 20),
                            if (error != null) ...[
                              NoticeBox(error!),
                              const SizedBox(height: 14),
                            ],
                            TextFormField(
                              controller: email,
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              autofillHints: const [AutofillHints.email],
                              decoration: InputDecoration(
                                labelText: context.tr('Work email'),
                                prefixIcon: const Icon(
                                  LucideIcons.mail,
                                  size: 19,
                                ),
                              ),
                              validator: (value) =>
                                  value != null && value.contains('@')
                                  ? null
                                  : context.tr('Enter a valid email'),
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: password,
                              obscureText: obscure,
                              textInputAction: TextInputAction.done,
                              autofillHints: const [AutofillHints.password],
                              onFieldSubmitted: (_) =>
                                  loading ? null : submit(),
                              decoration: InputDecoration(
                                labelText: context.tr('Password'),
                                prefixIcon: const Icon(
                                  LucideIcons.lock,
                                  size: 19,
                                ),
                                suffixIcon: IconButton(
                                  tooltip: obscure
                                      ? 'Show password'
                                      : 'Hide password',
                                  onPressed: () =>
                                      setState(() => obscure = !obscure),
                                  icon: Icon(
                                    obscure
                                        ? LucideIcons.eye
                                        : LucideIcons.eyeOff,
                                    size: 19,
                                  ),
                                ),
                              ),
                              validator: (value) => (value?.length ?? 0) >= 8
                                  ? null
                                  : context.tr(
                                      'Password must have at least 8 characters',
                                    ),
                            ),
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton(
                                onPressed: forgotPassword,
                                child: const T('Forgot password?'),
                              ),
                            ),
                            const SizedBox(height: 6),
                            BusyButton(
                              label: 'Sign in',
                              busyLabel: 'Signing in…',
                              icon: LucideIcons.logIn,
                              busy: loading,
                              onPressed: submit,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    LucideIcons.shieldCheck,
                    size: 14,
                    color: AppColors.subtle,
                  ),
                  SizedBox(width: 6),
                  T(
                    'Secure access for Nexora field teams',
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              const Text(
                'v${AppInfo.version}',
                style: TextStyle(color: AppColors.subtle, fontSize: 11),
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}
