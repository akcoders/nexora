import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

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
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to refresh attendance.')),
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
    try {
      await locate();
    } catch (error) {
      if (!silent) _message(error.toString());
    }
  }

  double? _distance(dynamic premise) {
    final position = currentPosition;
    if (position == null) return null;
    return Geolocator.distanceBetween(
      position.latitude,
      position.longitude,
      double.parse(premise['latitude'].toString()),
      double.parse(premise['longitude'].toString()),
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

  Future<void> checkIn() async {
    if (premises.isEmpty) {
      _message('No premises is assigned to your account.');
      return;
    }
    final remark = TextEditingController();
    XFile? photo;
    final proceed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Attendance check-in'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  height: 150,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: photo == null
                      ? const Icon(
                          LucideIcons.camera,
                          size: 44,
                          color: AppColors.primary,
                        )
                      : ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: Image.file(
                            File(photo!.path),
                            fit: BoxFit.cover,
                          ),
                        ),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picked = await ImagePicker().pickImage(
                      source: ImageSource.camera,
                      preferredCameraDevice: CameraDevice.front,
                      imageQuality: 78,
                    );
                    if (picked != null) setDialogState(() => photo = picked);
                  },
                  icon: const Icon(LucideIcons.camera),
                  label: Text(
                    photo == null ? 'Take live selfie' : 'Retake selfie',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: remark,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Remark (required if outside)',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: photo == null
                  ? null
                  : () => Navigator.pop(context, true),
              child: const Text('Check in'),
            ),
          ],
        ),
      ),
    );
    if (proceed != true || photo == null || !mounted) return;

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
          'remark': remark.text,
          'selfie': await MultipartFile.fromFile(
            photo!.path,
            filename: photo!.name,
          ),
        }),
      );
      if (!mounted) return;
      Navigator.pop(context);
      await load();
      _message('Check-in recorded successfully.', success: true);
    } on DioException catch (error) {
      if (mounted) Navigator.pop(context);
      _message(
        error.response?.data?['message']?.toString() ?? 'Check-in failed.',
      );
    } catch (error) {
      if (mounted) Navigator.pop(context);
      _message(error.toString());
    }
  }

  Future<void> checkOut() async {
    final remark = TextEditingController();
    XFile? photo;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          icon: const Icon(LucideIcons.logOut, color: AppColors.danger),
          title: const Text('Confirm checkout'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  height: 140,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: photo == null
                      ? const Icon(
                          LucideIcons.camera,
                          size: 42,
                          color: AppColors.primary,
                        )
                      : ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: Image.file(
                            File(photo!.path),
                            fit: BoxFit.cover,
                          ),
                        ),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picked = await ImagePicker().pickImage(
                      source: ImageSource.camera,
                      preferredCameraDevice: CameraDevice.front,
                      imageQuality: 78,
                    );
                    if (picked != null) setDialogState(() => photo = picked);
                  },
                  icon: const Icon(LucideIcons.camera),
                  label: Text(
                    photo == null ? 'Take checkout selfie' : 'Retake selfie',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: remark,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Remark (required if outside)',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: photo == null
                  ? null
                  : () => Navigator.pop(context, true),
              child: const Text('Check out'),
            ),
          ],
        ),
      ),
    );
    if (confirmed != true || photo == null || !mounted) return;

    final api = context.read<ApiClient>().dio;
    _busy('Recording checkout…');
    try {
      final position = await locate();
      await api.post(
        'attendance/check-out',
        data: FormData.fromMap({
          'latitude': position.latitude,
          'longitude': position.longitude,
          'remark': remark.text,
          'selfie': await MultipartFile.fromFile(
            photo!.path,
            filename: photo!.name,
          ),
        }),
      );
      if (!mounted) return;
      Navigator.pop(context);
      await load();
      _message('Checkout recorded successfully.', success: true);
    } on DioException catch (error) {
      if (mounted) Navigator.pop(context);
      _message(
        error.response?.data?['message']?.toString() ?? 'Checkout failed.',
      );
    } catch (error) {
      if (mounted) Navigator.pop(context);
      _message(error.toString());
    }
  }

  void _message(String message, {bool success = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: success ? AppColors.success : null,
      ),
    );
  }

  void _busy(String message) => showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (_) => AlertDialog(
      content: Row(
        children: [
          const CircularProgressIndicator(),
          const SizedBox(width: 18),
          Expanded(child: Text(message)),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final checkedOut = today?['checked_out_at'] != null;
    final checkedIn = today != null;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Attendance'),
        actions: [
          IconButton(onPressed: load, icon: const Icon(LucideIcons.refreshCw)),
        ],
      ),
      body: loading && attendanceByDate.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(18),
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    decoration: BoxDecoration(
                      color: checkedIn ? const Color(0xFFEAF8EF) : Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(
                        color: checkedIn
                            ? const Color(0xFFBBF7D0)
                            : const Color(0xFFE8EEF6),
                      ),
                    ),
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      children: [
                        Icon(
                          checkedIn
                              ? LucideIcons.checkCircle2
                              : LucideIcons.clock3,
                          color: checkedIn
                              ? AppColors.success
                              : AppColors.warning,
                          size: 42,
                        ),
                        const SizedBox(height: 12),
                        Text(
                          !checkedIn
                              ? 'Not checked in'
                              : checkedOut
                              ? 'Shift completed'
                              : 'You are checked in',
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          DateFormat(
                            'EEEE, d MMMM yyyy',
                          ).format(DateTime.now()),
                          style: const TextStyle(color: AppColors.muted),
                        ),
                        if (checkedIn) ...[
                          const Divider(height: 32),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceAround,
                            children: [
                              _Time(
                                label: 'Check in',
                                value: today?['checked_in_at'],
                              ),
                              _Time(
                                label: 'Check out',
                                value: today?['checked_out_at'],
                              ),
                            ],
                          ),
                        ],
                        const SizedBox(height: 20),
                        if (!checkedIn)
                          FilledButton.icon(
                            onPressed: checkIn,
                            icon: const Icon(LucideIcons.camera),
                            label: const Text('Take selfie & check in'),
                          )
                        else if (!checkedOut)
                          FilledButton.icon(
                            onPressed: checkOut,
                            icon: const Icon(LucideIcons.logOut),
                            label: const Text('Check out'),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Expanded(
                                child: Text(
                                  'Assigned premises',
                                  style: TextStyle(fontWeight: FontWeight.w800),
                                ),
                              ),
                              IconButton(
                                onPressed: () => _refreshPosition(),
                                icon: const Icon(LucideIcons.locateFixed),
                              ),
                            ],
                          ),
                          ...sortedPremises.asMap().entries.map((entry) {
                            final distance = _distance(entry.value);
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              leading: Icon(
                                entry.key == 0
                                    ? LucideIcons.mapPinCheck
                                    : LucideIcons.mapPin,
                                color: entry.key == 0
                                    ? AppColors.success
                                    : AppColors.primary,
                              ),
                              title: Text(
                                entry.value['name']?.toString() ?? 'Premises',
                              ),
                              subtitle: Text(
                                entry.value['address']?.toString() ?? '',
                              ),
                              trailing: Text(
                                distance == null
                                    ? 'Locate'
                                    : '${(distance / 1000).toStringAsFixed(2)} km',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            );
                          }),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  _AttendanceCalendar(
                    month: visibleMonth,
                    attendanceByDate: attendanceByDate,
                    rules: rules,
                    onPrevious: () => changeMonth(-1),
                    onNext: () => changeMonth(1),
                  ),
                ],
              ),
            ),
    );
  }
}

class _Time extends StatelessWidget {
  const _Time({required this.label, required this.value});

  final String label;
  final dynamic value;

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(value?.toString() ?? '');
    return Column(
      children: [
        Text(
          date == null ? '—' : DateFormat('hh:mm a').format(date.toLocal()),
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
        ),
        Text(
          label,
          style: const TextStyle(color: AppColors.muted, fontSize: 12),
        ),
      ],
    );
  }
}

class _AttendanceCalendar extends StatelessWidget {
  const _AttendanceCalendar({
    required this.month,
    required this.attendanceByDate,
    required this.rules,
    required this.onPrevious,
    required this.onNext,
  });

  final DateTime month;
  final Map<String, String> attendanceByDate;
  final Map<String, dynamic> rules;
  final VoidCallback onPrevious;
  final VoidCallback onNext;

  static const statuses = <String, ({Color color, Color soft, String label})>{
    'present': (
      color: AppColors.success,
      soft: Color(0xFFDCFCE7),
      label: 'Present',
    ),
    'half_day': (
      color: AppColors.warning,
      soft: Color(0xFFFEF3C7),
      label: 'Half day',
    ),
    'absent': (
      color: AppColors.danger,
      soft: Color(0xFFFEE2E2),
      label: 'Absent',
    ),
    'pending_review': (
      color: Color(0xFF7C3AED),
      soft: Color(0xFFEDE9FE),
      label: 'Pending review',
    ),
  };

  @override
  Widget build(BuildContext context) {
    final first = DateTime(month.year, month.month);
    final days = DateUtils.getDaysInMonth(month.year, month.month);
    final leading = first.weekday - 1;
    final cells = ((leading + days + 6) ~/ 7) * 7;
    final currentMonth = DateTime(DateTime.now().year, DateTime.now().month);
    final canNext = month.isBefore(currentMonth);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Attendance calendar',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                  ),
                ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: onPrevious,
                  icon: const Icon(Icons.chevron_left),
                ),
                Text(
                  DateFormat('MMM yyyy').format(month),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: canNext ? onNext : null,
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: ['M', 'T', 'W', 'T', 'F', 'S', 'S']
                  .map(
                    (day) => Expanded(
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
                  )
                  .toList(),
            ),
            const SizedBox(height: 8),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 7,
                mainAxisSpacing: 6,
                crossAxisSpacing: 6,
              ),
              itemCount: cells,
              itemBuilder: (context, index) {
                final day = index - leading + 1;
                if (day < 1 || day > days) return const SizedBox.shrink();
                final date = DateTime(month.year, month.month, day);
                final key = DateFormat('yyyy-MM-dd').format(date);
                final isFuture = DateUtils.dateOnly(
                  date,
                ).isAfter(DateUtils.dateOnly(DateTime.now()));
                final status = isFuture
                    ? null
                    : attendanceByDate[key] ?? 'absent';
                final palette = status == null ? null : statuses[status];
                final isToday = DateUtils.isSameDay(date, DateTime.now());
                return Container(
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: palette?.soft ?? const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(10),
                    border: isToday
                        ? Border.all(color: AppColors.primary, width: 1.5)
                        : null,
                  ),
                  child: Text(
                    '$day',
                    style: TextStyle(
                      color: palette?.color ?? AppColors.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                );
              },
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 12,
              runSpacing: 8,
              children: statuses.values
                  .map(
                    (item) => Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 9,
                          height: 9,
                          decoration: BoxDecoration(
                            color: item.color,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 5),
                        Text(
                          item.label,
                          style: const TextStyle(
                            color: AppColors.muted,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  )
                  .toList(),
            ),
            if (rules.isNotEmpty) ...[
              const Divider(height: 28),
              Text(
                'Full day: check in by ${rules['check_in_time']} + ${rules['check_in_grace_minutes']} min grace, check out at ${rules['checkout_time']} (early grace ${rules['checkout_grace_minutes']} min).',
                style: const TextStyle(color: AppColors.muted, fontSize: 11),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
