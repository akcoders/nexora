import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key, required this.onOpenMenu});

  final VoidCallback onOpenMenu;

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  Map<String, dynamic>? today;
  List<dynamic> premises = [];
  Map<String, String> attendanceByDate = {};
  Map<String, dynamic> rules = {};
  Position? currentPosition;
  DateTime visibleMonth = DateTime(DateTime.now().year, DateTime.now().month);
  bool loading = true;
  bool locating = false;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) setState(() => loading = true);
    try {
      final api = context.read<ApiClient>().dio;
      final responses = await Future.wait([
        api.get('attendance/today'),
        api.get('premises'),
        api.get(
          'attendance',
          queryParameters: {
            'month': DateFormat('yyyy-MM').format(visibleMonth),
          },
        ),
      ]);
      today = responses[0].data['attendance'];
      premises = List<dynamic>.from(responses[1].data['data'] ?? []);
      rules = Map<String, dynamic>.from(
        responses[2].data['rules'] ?? responses[0].data['rules'] ?? {},
      );
      attendanceByDate = {
        for (final item in List<dynamic>.from(responses[2].data['data'] ?? []))
          item['attendance_date'].toString().substring(0, 10):
              item['display_status']?.toString() ?? 'present',
      };
      await _refreshPosition(silent: true);
    } catch (error) {
      if (mounted) {
        showAppSnack(
          context,
          'Unable to refresh attendance.',
          tone: Tone.danger,
        );
      }
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> changeMonth(int delta) async {
    visibleMonth = DateTime(visibleMonth.year, visibleMonth.month + delta);
    await load();
  }

  Future<Position> locate() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw Exception('Turn on location services.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw Exception('Location permission is required.');
    }
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    );
    currentPosition = position;
    if (mounted) setState(() {});
    return position;
  }

  Future<void> _refreshPosition({bool silent = false}) async {
    if (mounted) setState(() => locating = true);
    try {
      await locate();
    } catch (error) {
      if (!silent && mounted) {
        showAppSnack(context, apiErrorMessage(error), tone: Tone.danger);
      }
    } finally {
      if (mounted) setState(() => locating = false);
    }
  }

  double? _distance(dynamic premise) {
    final position = currentPosition;
    final latitude = double.tryParse(premise['latitude']?.toString() ?? '');
    final longitude = double.tryParse(premise['longitude']?.toString() ?? '');
    if (position == null || latitude == null || longitude == null) return null;
    return Geolocator.distanceBetween(
      position.latitude,
      position.longitude,
      latitude,
      longitude,
    );
  }

  List<dynamic> get sortedPremises {
    final values = [...premises];
    if (currentPosition != null) {
      values.sort(
        (a, b) => (_distance(a) ?? double.infinity).compareTo(
          _distance(b) ?? double.infinity,
        ),
      );
    }
    return values;
  }

  bool _inside(dynamic premise) {
    final distance = _distance(premise);
    final radius = double.tryParse(premise['radius_meters']?.toString() ?? '');
    return distance != null && radius != null && distance <= radius;
  }

  Future<({XFile photo, String remark})?> _selfieSheet({
    required bool checkout,
  }) {
    final nearest = sortedPremises.isEmpty ? null : sortedPremises.first;
    return showModalBottomSheet<({XFile photo, String remark})>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SelfieSheet(
        checkout: checkout,
        premisesName: nearest?['name']?.toString(),
        outside:
            nearest == null ||
            (_distance(nearest) != null && !_inside(nearest)),
      ),
    );
  }

  Future<void> checkIn() async {
    if (premises.isEmpty) {
      showAppSnack(
        context,
        'No premises is assigned to your account.',
        tone: Tone.danger,
      );
      return;
    }
    final result = await _selfieSheet(checkout: false);
    if (result == null || !mounted) return;
    final api = context.read<ApiClient>().dio;
    _busy('Getting precise location…');
    try {
      final position = await locate();
      await api.post(
        'attendance/check-in',
        data: FormData.fromMap({
          'premises_id': sortedPremises.first['id'],
          'latitude': position.latitude,
          'longitude': position.longitude,
          'remark': result.remark,
          'selfie': await MultipartFile.fromFile(
            result.photo.path,
            filename: result.photo.name,
          ),
        }),
      );
      if (!mounted) return;
      Navigator.pop(context);
      await load();
      if (mounted) {
        showAppSnack(
          context,
          'Check-in recorded successfully.',
          tone: Tone.success,
        );
      }
    } catch (error) {
      if (mounted) {
        Navigator.pop(context);
        showAppSnack(
          context,
          apiErrorMessage(error, 'Check-in failed.'),
          tone: Tone.danger,
        );
      }
    }
  }

  Future<void> checkOut() async {
    final result = await _selfieSheet(checkout: true);
    if (result == null || !mounted) return;
    final api = context.read<ApiClient>().dio;
    _busy('Recording checkout…');
    try {
      final position = await locate();
      await api.post(
        'attendance/check-out',
        data: FormData.fromMap({
          'latitude': position.latitude,
          'longitude': position.longitude,
          'remark': result.remark,
          'selfie': await MultipartFile.fromFile(
            result.photo.path,
            filename: result.photo.name,
          ),
        }),
      );
      if (!mounted) return;
      Navigator.pop(context);
      await load();
      if (mounted) {
        showAppSnack(
          context,
          'Checkout recorded successfully.',
          tone: Tone.success,
        );
      }
    } catch (error) {
      if (mounted) {
        Navigator.pop(context);
        showAppSnack(
          context,
          apiErrorMessage(error, 'Checkout failed.'),
          tone: Tone.danger,
        );
      }
    }
  }

  void _busy(String message) => showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (_) => PopScope(
      canPop: false,
      child: AlertDialog(
        content: Row(
          children: [
            const SizedBox.square(
              dimension: 24,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            ),
            const SizedBox(width: 18),
            Expanded(child: T(message)),
          ],
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final checkedIn = today != null;
    final checkedOut = today?['checked_out_at'] != null;
    final nearest = sortedPremises;
    return Scaffold(
      appBar: AppBar(
        leading: MenuButton(onPressed: widget.onOpenMenu),
        title: const T('Attendance'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: loading ? null : load,
            icon: const Icon(LucideIcons.refreshCw),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: loading && attendanceByDate.isEmpty && today == null
          ? const LoadingView()
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                children: [
                  _TodayCard(
                    attendance: today,
                    checkedIn: checkedIn,
                    checkedOut: checkedOut,
                    onCheckIn: checkIn,
                    onCheckOut: checkOut,
                  ),
                  const SizedBox(height: 12),
                  _MonthSummary(
                    month: visibleMonth,
                    attendanceByDate: attendanceByDate,
                  ),
                  const SizedBox(height: 12),
                  SectionCard(
                    title: 'Assigned premises',
                    subtitle: currentPosition == null
                        ? 'Location not available yet'
                        : 'Sorted by distance from you',
                    icon: LucideIcons.building2,
                    trailing: IconButton(
                      tooltip: 'Update my location',
                      onPressed: locating ? null : () => _refreshPosition(),
                      icon: locating
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(
                              LucideIcons.locateFixed,
                              size: 19,
                              color: AppColors.primary,
                            ),
                    ),
                    padding: EdgeInsets.zero,
                    child: nearest.isEmpty
                        ? const EmptyState(
                            compact: true,
                            icon: LucideIcons.mapPinOff,
                            title: 'No premises assigned',
                            message:
                                'Ask HR to assign your work premises before marking attendance.',
                          )
                        : Column(
                            children: [
                              for (final (index, premise) in nearest.indexed)
                                _PremiseRow(
                                  premise: premise,
                                  distance: _distance(premise),
                                  inside: _inside(premise),
                                  nearest:
                                      index == 0 && currentPosition != null,
                                  last: index == nearest.length - 1,
                                ),
                            ],
                          ),
                  ),
                  const SizedBox(height: 12),
                  _AttendanceCalendar(
                    month: visibleMonth,
                    attendanceByDate: attendanceByDate,
                    rules: rules,
                    loading: loading,
                    onPrevious: () => changeMonth(-1),
                    onNext: () => changeMonth(1),
                  ),
                ],
              ),
            ),
    );
  }
}

class _TodayCard extends StatelessWidget {
  const _TodayCard({
    required this.attendance,
    required this.checkedIn,
    required this.checkedOut,
    required this.onCheckIn,
    required this.onCheckOut,
  });

  final Map<String, dynamic>? attendance;
  final bool checkedIn;
  final bool checkedOut;
  final VoidCallback onCheckIn;
  final VoidCallback onCheckOut;

  static DateTime? _date(dynamic value) =>
      DateTime.tryParse(value?.toString() ?? '')?.toLocal();

  @override
  Widget build(BuildContext context) {
    final inAt = _date(attendance?['checked_in_at']);
    final outAt = _date(attendance?['checked_out_at']);
    final worked = inAt == null
        ? null
        : (outAt ?? DateTime.now()).difference(inAt);
    final tone = !checkedIn
        ? Tone.warning
        : (checkedOut ? Tone.slate : Tone.success);
    final title = !checkedIn
        ? 'Not checked in'
        : (checkedOut ? 'Shift completed' : 'You are on duty');
    return Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: tone.soft,
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            child: Row(
              children: [
                Icon(
                  !checkedIn
                      ? LucideIcons.clock3
                      : (checkedOut
                            ? LucideIcons.circleCheckBig
                            : LucideIcons.badgeCheck),
                  color: tone.color,
                  size: 26,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      T(
                        title,
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: tone.color,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        DateFormat('EEEE, d MMMM yyyy').format(DateTime.now()),
                        style: const TextStyle(
                          color: AppColors.ink,
                          fontSize: 12.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    _TimeBox(
                      label: 'Check in',
                      value: inAt == null
                          ? '—'
                          : DateFormat('hh:mm a').format(inAt),
                    ),
                    const SizedBox(width: 10),
                    _TimeBox(
                      label: 'Check out',
                      value: outAt == null
                          ? '—'
                          : DateFormat('hh:mm a').format(outAt),
                    ),
                    const SizedBox(width: 10),
                    _TimeBox(
                      label: 'Worked',
                      value: worked == null
                          ? '—'
                          : '${worked.inHours}h ${worked.inMinutes.remainder(60)}m',
                    ),
                  ],
                ),
                if (!checkedOut) ...[
                  const SizedBox(height: 14),
                  FilledButton.icon(
                    onPressed: checkedIn ? onCheckOut : onCheckIn,
                    style: checkedIn
                        ? FilledButton.styleFrom(
                            backgroundColor: AppColors.danger,
                          )
                        : null,
                    icon: Icon(
                      checkedIn ? LucideIcons.logOut : LucideIcons.scanFace,
                      size: 19,
                    ),
                    label: T(
                      checkedIn
                          ? 'Selfie & check out'
                          : 'Take selfie & check in',
                    ),
                  ),
                  const SizedBox(height: 8),
                  const T(
                    'A live selfie and your GPS location are recorded with each punch.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.muted, fontSize: 11.5),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TimeBox extends StatelessWidget {
  const _TimeBox({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 6),
      decoration: BoxDecoration(
        color: AppColors.surface2,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: AppColors.heading,
            ),
          ),
          const SizedBox(height: 2),
          T(
            label,
            style: const TextStyle(color: AppColors.muted, fontSize: 11.5),
          ),
        ],
      ),
    ),
  );
}

class _PremiseRow extends StatelessWidget {
  const _PremiseRow({
    required this.premise,
    required this.distance,
    required this.inside,
    required this.nearest,
    required this.last,
  });

  final dynamic premise;
  final double? distance;
  final bool inside;
  final bool nearest;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: AppColors.border)),
    ),
    child: Row(
      children: [
        IconTile(
          inside ? LucideIcons.mapPinCheck : LucideIcons.mapPin,
          tone: inside ? Tone.success : (nearest ? Tone.primary : Tone.slate),
          size: 36,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Flexible(
                    child: Text(
                      premise['name']?.toString() ?? 'Premises',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                  ),
                  if (nearest) ...[
                    const SizedBox(width: 6),
                    Pill(
                      inside ? 'Inside' : 'Nearest',
                      tone: inside ? Tone.success : Tone.primary,
                    ),
                  ],
                ],
              ),
              if ((premise['address']?.toString() ?? '').isNotEmpty)
                Text(
                  premise['address'].toString(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Text(
          distance == null
              ? '—'
              : distance! < 1000
              ? '${distance!.round()} m'
              : '${(distance! / 1000).toStringAsFixed(1)} km',
          style: TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 13,
            color: inside ? AppColors.success : AppColors.heading,
          ),
        ),
      ],
    ),
  );
}

const _statuses = <String, ({Tone tone, String label})>{
  'present': (tone: Tone.success, label: 'Present'),
  'half_day': (tone: Tone.warning, label: 'Half day'),
  'absent': (tone: Tone.danger, label: 'Absent'),
  'pending_review': (tone: Tone.purple, label: 'Pending review'),
};

/// Status for a past day; days without a record count as absent.
String? _statusFor(DateTime date, Map<String, String> attendanceByDate) {
  if (DateUtils.dateOnly(date).isAfter(DateUtils.dateOnly(DateTime.now()))) {
    return null;
  }
  return attendanceByDate[DateFormat('yyyy-MM-dd').format(date)] ?? 'absent';
}

class _MonthSummary extends StatelessWidget {
  const _MonthSummary({required this.month, required this.attendanceByDate});

  final DateTime month;
  final Map<String, String> attendanceByDate;

  @override
  Widget build(BuildContext context) {
    final counts = {for (final key in _statuses.keys) key: 0};
    final days = DateUtils.getDaysInMonth(month.year, month.month);
    for (var day = 1; day <= days; day++) {
      final status = _statusFor(
        DateTime(month.year, month.month, day),
        attendanceByDate,
      );
      if (status != null) counts[status] = (counts[status] ?? 0) + 1;
    }
    return Row(
      children: [
        for (final (index, entry) in _statuses.entries.indexed) ...[
          if (index > 0) const SizedBox(width: 8),
          Expanded(
            child: Card(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  vertical: 10,
                  horizontal: 4,
                ),
                decoration: BoxDecoration(
                  border: Border(
                    top: BorderSide(color: entry.value.tone.color, width: 3),
                  ),
                ),
                child: Column(
                  children: [
                    Text(
                      '${counts[entry.key]}',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: entry.value.tone.color,
                      ),
                    ),
                    const SizedBox(height: 2),
                    T(
                      entry.key == 'pending_review'
                          ? 'In review'
                          : entry.value.label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _AttendanceCalendar extends StatelessWidget {
  const _AttendanceCalendar({
    required this.month,
    required this.attendanceByDate,
    required this.rules,
    required this.loading,
    required this.onPrevious,
    required this.onNext,
  });

  final DateTime month;
  final Map<String, String> attendanceByDate;
  final Map<String, dynamic> rules;
  final bool loading;
  final VoidCallback onPrevious;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    final first = DateTime(month.year, month.month);
    final days = DateUtils.getDaysInMonth(month.year, month.month);
    final leading = first.weekday - 1;
    final cells = ((leading + days + 6) ~/ 7) * 7;
    final canNext = month.isBefore(
      DateTime(DateTime.now().year, DateTime.now().month),
    );

    return SectionCard(
      title: 'Attendance calendar',
      icon: LucideIcons.calendarDays,
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: loading ? null : onPrevious,
            icon: const Icon(LucideIcons.chevronLeft, size: 18),
          ),
          Text(
            DateFormat('MMM yyyy').format(month),
            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: canNext && !loading ? onNext : null,
            icon: const Icon(LucideIcons.chevronRight, size: 18),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              for (final day in const ['M', 'T', 'W', 'T', 'F', 'S', 'S'])
                Expanded(
                  child: Center(
                    child: Text(
                      day,
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 7,
              mainAxisSpacing: 5,
              crossAxisSpacing: 5,
            ),
            itemCount: cells,
            itemBuilder: (context, index) {
              final day = index - leading + 1;
              if (day < 1 || day > days) return const SizedBox.shrink();
              final date = DateTime(month.year, month.month, day);
              final status = _statusFor(date, attendanceByDate);
              final palette = status == null ? null : _statuses[status];
              final isToday = DateUtils.isSameDay(date, DateTime.now());
              return Container(
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: palette?.tone.soft ?? AppColors.surface2,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                  border: isToday
                      ? Border.all(color: AppColors.primary, width: 1.6)
                      : null,
                ),
                child: Text(
                  '$day',
                  style: TextStyle(
                    color: palette?.tone.color ?? AppColors.subtle,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 14,
            runSpacing: 8,
            children: [
              for (final item in _statuses.values)
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 10,
                      height: 10,
                      decoration: BoxDecoration(
                        color: item.tone.color,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                    const SizedBox(width: 5),
                    T(
                      item.label,
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 11.5,
                      ),
                    ),
                  ],
                ),
            ],
          ),
          if (rules.isNotEmpty) ...[
            const SizedBox(height: 14),
            NoticeBox(
              'Full day: check in by ${rules['check_in_time']} + ${rules['check_in_grace_minutes']} min grace, check out at ${rules['checkout_time']} (early grace ${rules['checkout_grace_minutes']} min).',
              tone: Tone.info,
              icon: LucideIcons.info,
            ),
          ],
        ],
      ),
    );
  }
}

class _SelfieSheet extends StatefulWidget {
  const _SelfieSheet({
    required this.checkout,
    required this.premisesName,
    required this.outside,
  });

  final bool checkout;
  final String? premisesName;
  final bool outside;

  @override
  State<_SelfieSheet> createState() => _SelfieSheetState();
}

class _SelfieSheetState extends State<_SelfieSheet> {
  final remark = TextEditingController();
  XFile? photo;

  @override
  void dispose() {
    remark.dispose();
    super.dispose();
  }

  Future<void> capture() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.front,
      imageQuality: 78,
    );
    if (picked != null && mounted) setState(() => photo = picked);
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      0,
      20,
      20 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SheetHeader(
            icon: widget.checkout ? LucideIcons.logOut : LucideIcons.scanFace,
            title: widget.checkout
                ? 'Attendance check-out'
                : 'Attendance check-in',
            subtitle: widget.premisesName == null
                ? null
                : '${context.tr('Nearest premises')}: ${widget.premisesName}',
          ),
          InkWell(
            onTap: capture,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            child: Container(
              height: 210,
              decoration: BoxDecoration(
                color: AppColors.primarySoft,
                borderRadius: BorderRadius.circular(AppRadius.lg),
                border: Border.all(color: const Color(0xFFCFE0FA)),
              ),
              clipBehavior: Clip.antiAlias,
              child: photo == null
                  ? const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          LucideIcons.camera,
                          size: 40,
                          color: AppColors.primary,
                        ),
                        SizedBox(height: 10),
                        T(
                          'Tap to take a live selfie',
                          style: TextStyle(
                            color: AppColors.primary,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    )
                  : Image.file(
                      File(photo!.path),
                      fit: BoxFit.cover,
                      width: double.infinity,
                    ),
            ),
          ),
          if (photo != null)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: capture,
                icon: const Icon(LucideIcons.refreshCcw, size: 16),
                label: const T('Retake selfie'),
              ),
            )
          else
            const SizedBox(height: 14),
          if (widget.outside) ...[
            const NoticeBox(
              'You seem to be outside the premises radius. A remark is required.',
              tone: Tone.warning,
            ),
            const SizedBox(height: 12),
          ],
          TextField(
            controller: remark,
            minLines: 2,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: context.tr('Remark (required if outside)'),
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: photo == null
                ? null
                : () => Navigator.pop(context, (
                    photo: photo!,
                    remark: remark.text.trim(),
                  )),
            style: widget.checkout
                ? FilledButton.styleFrom(backgroundColor: AppColors.danger)
                : null,
            icon: Icon(
              widget.checkout ? LucideIcons.logOut : LucideIcons.logIn,
              size: 18,
            ),
            label: T(widget.checkout ? 'Check out' : 'Check in'),
          ),
        ],
      ),
    ),
  );
}
