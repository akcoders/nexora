import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:nexora_technician/core/i18n/app_language.dart';
import 'package:nexora_technician/core/network/api_client.dart';
import 'package:nexora_technician/core/widgets/ui.dart';
import 'package:nexora_technician/features/home/app_drawer.dart';
import 'package:provider/provider.dart';

/// Wraps [child] with the language controller every `T` widget reads.
Widget harness(Widget child) {
  const storage = FlutterSecureStorage();
  return ChangeNotifierProvider(
    create: (_) => AppLanguageController(ApiClient(storage), storage),
    child: MaterialApp(home: child),
  );
}

void main() {
  testWidgets('side menu lists every section and reports the tapped item', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    MenuDestination? tapped;
    await tester.pumpWidget(
      harness(
        Scaffold(
          body: AppDrawer(
            user: const {
              'name': 'Ravi Kumar',
              'employee_code': 'EMP-007',
              'designation': 'HVAC Technician',
            },
            selected: MenuDestination.complaints,
            unreadCount: 3,
            languageName: 'English',
            onSelect: (destination) => tapped = destination,
          ),
        ),
      ),
    );

    expect(find.text('Ravi Kumar'), findsOneWidget);
    expect(find.text('EMP-007 · HVAC Technician'), findsOneWidget);
    // Unread badge next to Notifications.
    expect(find.text('3'), findsOneWidget);
    for (final section in [
      'MAIN',
      'WORK',
      'ATTENDANCE',
      'HR & PAYROLL',
      'ACCOUNT',
    ]) {
      expect(find.text(section), findsOneWidget);
    }
    for (final item in [
      'Dashboard',
      'Notifications',
      'Service jobs',
      'Complaint visits',
      'Internal workflow',
      'Completed work',
      'My attendance',
      'HR overview',
      'Leave requests',
      'Expense vouchers',
      'Salary slips',
      'Holiday calendar',
      'My profile',
      'App language',
      'Sign out',
    ]) {
      await tester.scrollUntilVisible(
        find.text(item),
        80,
        scrollable: find.byType(Scrollable).last,
      );
      expect(find.text(item), findsOneWidget, reason: item);
    }
    await tester.tap(find.text('Salary slips'));
    expect(tapped, MenuDestination.payslips);
  });

  testWidgets('empty state runs its action and pills are uppercase', (
    tester,
  ) async {
    var retried = false;
    await tester.pumpWidget(
      harness(
        Scaffold(
          body: Column(
            children: [
              const Pill('Pending', tone: Tone.warning),
              const StageTracker(
                stages: ['Visit', 'Inspect', 'Done'],
                current: 1,
              ),
              Expanded(
                child: EmptyState(
                  icon: LucideIcons.cloudOff,
                  title: 'Unable to load',
                  actionLabel: 'Retry',
                  onAction: () => retried = true,
                ),
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('PENDING'), findsOneWidget);
    expect(find.text('Inspect'), findsOneWidget);
    await tester.tap(find.text('Retry'));
    expect(retried, isTrue);
  });

  test('backend tone names map to tones', () {
    expect(toneFromName('success'), Tone.success);
    expect(toneFromName('secondary'), Tone.slate);
    expect(toneFromName(null), Tone.slate);
  });
}
