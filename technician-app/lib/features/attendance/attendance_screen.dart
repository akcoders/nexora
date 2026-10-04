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
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final api = context.read<ApiClient>().dio;
      final responses = await Future.wait([
        api.get('attendance/today'),
        api.get('premises'),
      ]);
      today = responses[0].data['attendance'];
      premises = List<dynamic>.from(responses[1].data['data'] ?? []);
    } catch (_) {}
    if (mounted) setState(() => loading = false);
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
    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    );
  }

  Future<void> checkIn() async {
    if (premises.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No premises is assigned to your account.'),
        ),
      );
      return;
    }
    final remark = TextEditingController();
    XFile? photo;
    final proceed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Attendance check-in'),
          content: Column(
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
                        child: Image.file(File(photo!.path), fit: BoxFit.cover),
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
                  if (picked != null) {
                    setDialogState(() => photo = picked);
                  }
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
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Continue'),
            ),
          ],
        ),
      ),
    );
    if (proceed != true || photo == null || !mounted) return;
    final api = context.read<ApiClient>();
    _busy('Getting precise location…');
    try {
      final position = await locate();
      await api.dio.post(
        'attendance/check-in',
        data: FormData.fromMap({
          'premises_id': premises.first['id'],
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
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Check-in recorded successfully.'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } on DioException catch (error) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error.response?.data?['message']?.toString() ??
                  'Check-in failed.',
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  Future<void> checkOut() async {
    final api = context.read<ApiClient>();
    _busy('Recording checkout…');
    try {
      final position = await locate();
      await api.dio.post(
        'attendance/check-out',
        data: {'latitude': position.latitude, 'longitude': position.longitude},
      );
      if (!mounted) return;
      Navigator.pop(context);
      await load();
    } catch (error) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  void _busy(String message) => showDialog(
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
    return Scaffold(
      appBar: AppBar(title: const Text('Attendance')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(18),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      children: [
                        Container(
                          width: 62,
                          height: 62,
                          decoration: BoxDecoration(
                            color:
                                (today == null
                                        ? AppColors.warning
                                        : AppColors.success)
                                    .withValues(alpha: .12),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            today == null
                                ? LucideIcons.clock3
                                : LucideIcons.checkCircle2,
                            color: today == null
                                ? AppColors.warning
                                : AppColors.success,
                            size: 30,
                          ),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          today == null
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
                        if (today != null) ...[
                          const Divider(height: 34),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceAround,
                            children: [
                              _Time(
                                label: 'Check in',
                                keyName: 'checked_in_at',
                              ),
                              _Time(
                                label: 'Check out',
                                keyName: 'checked_out_at',
                              ),
                            ].map((widget) => widget.withData(today!)).toList(),
                          ),
                        ],
                        const SizedBox(height: 22),
                        if (today == null)
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
                ),
                const SizedBox(height: 20),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Attendance rules',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 14),
                        const _Rule(
                          icon: LucideIcons.camera,
                          text: 'A live selfie is required at check-in.',
                        ),
                        const _Rule(
                          icon: LucideIcons.mapPin,
                          text: 'Precise GPS verifies the assigned premises.',
                        ),
                        const _Rule(
                          icon: LucideIcons.clock3,
                          text:
                              'Outside check-ins require a remark and review.',
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}

class _Time extends StatelessWidget {
  const _Time({required this.label, required this.keyName, this.data});
  final String label;
  final String keyName;
  final Map<String, dynamic>? data;
  _Time withData(Map<String, dynamic> value) =>
      _Time(label: label, keyName: keyName, data: value);
  @override
  Widget build(BuildContext context) {
    final raw = data?[keyName]?.toString();
    final date = raw == null ? null : DateTime.tryParse(raw);
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

class _Rule extends StatelessWidget {
  const _Rule({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(
      children: [
        Icon(icon, size: 18, color: AppColors.primary),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(color: AppColors.muted, fontSize: 13),
          ),
        ),
      ],
    ),
  );
}
