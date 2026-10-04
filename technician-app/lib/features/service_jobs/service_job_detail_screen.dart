import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'customer_approval_screen.dart';
import 'estimate_screen.dart';
import 'inspection_screen.dart';
import 'payment_screen.dart';
import 'service_execution_screen.dart';

class ServiceJobDetailScreen extends StatefulWidget {
  const ServiceJobDetailScreen({super.key, required this.serviceJobId});

  final int serviceJobId;

  @override
  State<ServiceJobDetailScreen> createState() => _ServiceJobDetailScreenState();
}

class _ServiceJobDetailScreenState extends State<ServiceJobDetailScreen> {
  Map<String, dynamic>? job;
  Map<String, dynamic> masters = {};
  bool loading = true;
  bool submitting = false;
  String? error;

  Dio get api => context.read<ApiClient>().dio;

  Future<void> load() async {
    if (mounted) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final response = await api.get('service-jobs/${widget.serviceJobId}');
      job = Map<String, dynamic>.from(response.data['job']);
      masters = Map<String, dynamic>.from(response.data['masters'] ?? {});
    } on DioException catch (exception) {
      error = apiErrorMessage(exception);
    }
    if (mounted) setState(() => loading = false);
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> postAction(String path, {Map<String, dynamic>? data}) async {
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      final response = await api.post(path, data: data);
      if (response.data['job'] != null) {
        job = Map<String, dynamic>.from(response.data['job']);
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(response.data['message']?.toString() ?? 'Saved.'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } on DioException catch (exception) {
      if (mounted) setState(() => error = apiErrorMessage(exception));
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  Future<void> handlePrimaryAction() async {
    final action = job?['primary_action']?['key']?.toString();
    switch (action) {
      case 'contact':
        await showContactSheet();
      case 'start_journey':
        await postAction('service-jobs/${widget.serviceJobId}/journey');
      case 'arrive':
        await markArrived();
      case 'start_inspection':
        await postAction('service-jobs/${widget.serviceJobId}/inspection');
        if (job?['status'] == 'inspection_in_progress') {
          await openInspection('pre');
        }
      case 'complete_inspection':
        await openInspection('pre');
      case 'estimate':
        await openPage(
          EstimateScreen(
            serviceJobId: widget.serviceJobId,
            services: List<dynamic>.from(masters['services'] ?? []),
          ),
        );
      case 'approval':
        final estimates = List<dynamic>.from(job?['estimates'] ?? []);
        if (estimates.isNotEmpty) {
          await openPage(
            CustomerApprovalScreen(
              serviceJobId: widget.serviceJobId,
              estimate: Map<String, dynamic>.from(estimates.first),
            ),
          );
        }
      case 'start_service':
        await postAction('service-jobs/${widget.serviceJobId}/service/start');
      case 'complete_service_work':
        await openPage(
          ServiceExecutionScreen(
            serviceJobId: widget.serviceJobId,
            job: job!,
            masters: masters,
          ),
        );
      case 'payment':
        await openPage(
          PaymentScreen(
            serviceJobId: widget.serviceJobId,
            job: job!,
            paymentMethods: List<dynamic>.from(
              masters['payment_methods'] ?? [],
            ),
          ),
        );
      case 'complete':
        await completeJob();
      default:
        break;
    }
    await load();
  }

  Future<void> openInspection(String phase) async {
    await openPage(
      InspectionScreen(
        serviceJobId: widget.serviceJobId,
        phase: phase,
        job: job!,
        conditions: List<dynamic>.from(masters['conditions'] ?? []),
      ),
    );
  }

  Future<void> openPage(Widget page) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(builder: (_) => page),
    );
  }

  Future<void> showContactSheet() async {
    final outcome = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Customer contact result',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context, 'confirm'),
                icon: const Icon(LucideIcons.calendarCheck),
                label: const Text('Confirm visit'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'reschedule'),
                icon: const Icon(LucideIcons.calendarClock),
                label: const Text('Reschedule'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'no_answer'),
                icon: const Icon(LucideIcons.phoneOff),
                label: const Text('No answer'),
              ),
              const SizedBox(height: 8),
              TextButton.icon(
                onPressed: () => Navigator.pop(context, 'customer_declined'),
                icon: const Icon(LucideIcons.circleX),
                label: const Text('Customer declined'),
              ),
              if (masters['capabilities']?['cancel'] == true) ...[
                const SizedBox(height: 8),
                TextButton.icon(
                  onPressed: () => Navigator.pop(context, 'cancel'),
                  icon: const Icon(LucideIcons.ban),
                  label: const Text('Cancel job'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
    if (outcome == null || !mounted) return;

    final data = <String, dynamic>{'outcome': outcome};
    if (outcome == 'reschedule') {
      final date = await showDatePicker(
        context: context,
        firstDate: DateTime.now(),
        lastDate: DateTime.now().add(const Duration(days: 365)),
        initialDate: DateTime.now().add(const Duration(days: 1)),
      );
      if (date == null || !mounted) return;
      final time = await showTimePicker(
        context: context,
        initialTime: TimeOfDay.now(),
      );
      if (time == null || !mounted) return;
      final reason = await chooseReason(
        'Reschedule reason',
        List<dynamic>.from(masters['reschedule_reasons'] ?? []),
      );
      if (reason == null || reason.trim().isEmpty) return;
      data['scheduled_at'] = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ).toIso8601String();
      data['reason'] = reason.trim();
    } else if (outcome == 'customer_declined' || outcome == 'cancel') {
      final reason = await chooseReason(
        outcome == 'cancel' ? 'Cancellation reason' : 'Decline reason',
        List<dynamic>.from(masters['cancellation_reasons'] ?? []),
      );
      if (reason == null || reason.trim().isEmpty) return;
      data['reason'] = reason.trim();
    }
    await postAction('service-jobs/${widget.serviceJobId}/contact', data: data);
  }

  Future<String?> chooseReason(String title, List<dynamic> options) async {
    if (options.isEmpty) {
      return askText(title, 'Enter the reason.');
    }
    return showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              ...options.map(
                (raw) => ListTile(
                  title: Text(raw['label']?.toString() ?? ''),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.pop(context, raw['label']?.toString()),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<String?> askText(String title, String hint) async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          maxLines: 3,
          autofocus: true,
          decoration: InputDecoration(hintText: hint),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const Text('Continue'),
          ),
        ],
      ),
    );
    controller.dispose();
    return result;
  }

  Future<void> markArrived() async {
    Map<String, dynamic> data = {'arrival_method': 'manual'};
    try {
      if (await Geolocator.isLocationServiceEnabled()) {
        var permission = await Geolocator.checkPermission();
        if (permission == LocationPermission.denied) {
          permission = await Geolocator.requestPermission();
        }
        if (permission == LocationPermission.always ||
            permission == LocationPermission.whileInUse) {
          final position = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
            ),
          );
          data = {
            'arrival_method': 'auto',
            'latitude': position.latitude,
            'longitude': position.longitude,
          };
        }
      }
    } catch (_) {
      // GPS is optional for arrival and manual fallback must remain available.
    }
    await postAction('service-jobs/${widget.serviceJobId}/arrival', data: data);
  }

  Future<void> completeJob() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(LucideIcons.badgeCheck, color: AppColors.success),
        title: const Text('Complete service?'),
        content: const Text(
          'The job will be closed and a customer feedback link will be generated.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Complete service'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await postAction('service-jobs/${widget.serviceJobId}/complete');
    }
  }

  Future<void> callCustomer() async {
    final phone = job?['customer_phone']?.toString();
    if (phone != null) await launchUrl(Uri(scheme: 'tel', path: phone));
  }

  Future<void> openDirections() async {
    final latitude = job?['latitude'];
    final longitude = job?['longitude'];
    final query = latitude != null && longitude != null
        ? '$latitude,$longitude'
        : Uri.encodeComponent(job?['service_address']?.toString() ?? '');
    await launchUrl(
      Uri.parse('https://www.google.com/maps/search/?api=1&query=$query'),
      mode: LaunchMode.externalApplication,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (job == null) {
      return Scaffold(
        appBar: AppBar(),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(error ?? 'Unable to open this service job.'),
          ),
        ),
      );
    }

    final scheduledAt = DateTime.tryParse(
      job?['scheduled_at']?.toString() ?? '',
    )?.toLocal();
    final equipment = job?['equipment'] as Map<String, dynamic>?;
    final primaryLabel = job?['primary_action']?['label']?.toString();
    final status = job?['status']?.toString() ?? 'assigned';

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(job?['job_no']?.toString() ?? 'Service Job'),
            Text(
              job?['status_label']?.toString() ?? status,
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(onPressed: load, icon: const Icon(LucideIcons.refreshCw)),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.fromLTRB(
            18,
            8,
            18,
            primaryLabel == null ? 30 : 105,
          ),
          children: [
            _Progress(status: status),
            const SizedBox(height: 18),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      job?['customer']?['name']?.toString() ?? 'Customer',
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      job?['complaint']?.toString() ?? '',
                      style: const TextStyle(
                        color: AppColors.muted,
                        height: 1.45,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: callCustomer,
                            icon: const Icon(LucideIcons.phone, size: 18),
                            label: const Text('Call'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: openDirections,
                            icon: const Icon(LucideIcons.navigation, size: 18),
                            label: const Text('Directions'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  children: [
                    _DetailRow(
                      icon: LucideIcons.calendarClock,
                      label: 'Schedule',
                      value: scheduledAt == null
                          ? 'Not scheduled'
                          : DateFormat(
                              'EEEE, d MMM · hh:mm a',
                            ).format(scheduledAt),
                    ),
                    _DetailRow(
                      icon: LucideIcons.mapPin,
                      label: 'Address',
                      value: job?['service_address']?.toString() ?? '—',
                    ),
                    _DetailRow(
                      icon: LucideIcons.airVent,
                      label: 'Equipment',
                      value: equipment == null
                          ? 'Not linked'
                          : [
                              equipment['equipment_type'],
                              equipment['brand'],
                              equipment['model'],
                              equipment['capacity'],
                              equipment['location'],
                            ].where((value) => value != null).join(' · '),
                      last: true,
                    ),
                  ],
                ),
              ),
            ),
            if ((job?['inspections'] as List?)?.isNotEmpty ?? false) ...[
              const SizedBox(height: 18),
              _SummaryCard(job: job!),
            ],
            if (status == 'completed') ...[
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(
                            LucideIcons.badgeCheck,
                            color: AppColors.success,
                          ),
                          SizedBox(width: 10),
                          Text(
                            'Service completed',
                            style: TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'The service report, proforma and feedback request are available from the ERP.',
                        style: TextStyle(color: AppColors.muted, height: 1.4),
                      ),
                      if (job?['feedback_url'] != null) ...[
                        const SizedBox(height: 10),
                        OutlinedButton.icon(
                          onPressed: () => launchUrl(
                            Uri.parse(job!['feedback_url'].toString()),
                            mode: LaunchMode.externalApplication,
                          ),
                          icon: const Icon(LucideIcons.star, size: 17),
                          label: const Text('Open customer feedback link'),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
            if (error != null) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  error!,
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
            ],
          ],
        ),
      ),
      bottomSheet: primaryLabel == null
          ? null
          : SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
                ),
                child: FilledButton.icon(
                  onPressed: submitting ? null : handlePrimaryAction,
                  icon: submitting
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(LucideIcons.arrowRight),
                  label: Text(submitting ? 'Please wait…' : primaryLabel),
                ),
              ),
            ),
    );
  }
}

String apiErrorMessage(DioException exception) {
  final data = exception.response?.data;
  if (data is Map && data['errors'] is Map) {
    final errors = data['errors'] as Map;
    if (errors.isNotEmpty) {
      final messages = errors.values.first;
      if (messages is List && messages.isNotEmpty) {
        return messages.first.toString();
      }
    }
  }
  return data is Map && data['message'] != null
      ? data['message'].toString()
      : 'Something went wrong. Your entered data is safe. Please try again.';
}

class _Progress extends StatelessWidget {
  const _Progress({required this.status});
  final String status;

  static const stages = [
    [
      'Visit',
      'assigned,contacted,confirmed,rescheduled,no_answer,on_the_way,arrived',
    ],
    ['Inspect', 'inspection_in_progress,inspection_completed'],
    ['Approve', 'estimate_created,estimate_pending_approval,customer_approved'],
    ['Service', 'service_in_progress,service_completed'],
    ['Payment', 'payment_pending,partially_paid,paid'],
    ['Done', 'completed'],
  ];

  @override
  Widget build(BuildContext context) {
    var current = stages.indexWhere(
      (stage) => stage[1].split(',').contains(status),
    );
    if (current < 0) current = 0;
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 16),
        child: Row(
          children: List.generate(stages.length, (index) {
            final active = index == current;
            final done = index < current;
            final color = done
                ? AppColors.success
                : active
                ? AppColors.primary
                : const Color(0xFFCBD5E1);
            return Expanded(
              child: Column(
                children: [
                  Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: active || done ? color : Colors.white,
                      border: Border.all(color: color, width: 2),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      done ? LucideIcons.check : LucideIcons.circle,
                      size: 14,
                      color: active || done ? Colors.white : color,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    stages[index][0],
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: color,
                      fontSize: 9,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            );
          }),
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.last = false,
  });
  final IconData icon;
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.only(bottom: last ? 0 : 14, top: last ? 14 : 0),
    margin: EdgeInsets.only(bottom: last ? 0 : 14),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: Color(0xFFEEF2F7))),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: AppColors.primary, size: 19),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(color: AppColors.muted, fontSize: 11),
              ),
              const SizedBox(height: 3),
              Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ],
    ),
  );
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.job});
  final Map<String, dynamic> job;

  @override
  Widget build(BuildContext context) {
    final inspections = List<dynamic>.from(job['inspections'] ?? []);
    final services = List<dynamic>.from(job['performed_services'] ?? []);
    final materials = List<dynamic>.from(job['materials'] ?? []);
    final photos = List<dynamic>.from(job['photos'] ?? []);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Service summary',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                _Count(label: 'Checklists', value: inspections.length),
                _Count(label: 'Services', value: services.length),
                _Count(label: 'Materials', value: materials.length),
                _Count(label: 'Photos', value: photos.length),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Count extends StatelessWidget {
  const _Count({required this.label, required this.value});
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
        ),
        Text(
          label,
          style: const TextStyle(color: AppColors.muted, fontSize: 10),
        ),
      ],
    ),
  );
}
