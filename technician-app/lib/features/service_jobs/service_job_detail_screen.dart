import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'customer_approval_screen.dart';
import 'estimate_screen.dart';
import 'inspection_screen.dart';
import 'payment_screen.dart';
import 'service_execution_screen.dart';
import '../../core/i18n/t.dart';

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
        showAppSnack(
          context,
          response.data['message']?.toString() ?? 'Saved.',
          tone: Tone.success,
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
              const T(
                'Customer contact result',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context, 'confirm'),
                icon: const Icon(LucideIcons.calendarCheck),
                label: const T('Confirm visit'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'reschedule'),
                icon: const Icon(LucideIcons.calendarClock),
                label: const T('Reschedule'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'no_answer'),
                icon: const Icon(LucideIcons.phoneOff),
                label: const T('No answer'),
              ),
              const SizedBox(height: 8),
              TextButton.icon(
                onPressed: () => Navigator.pop(context, 'customer_declined'),
                icon: const Icon(LucideIcons.circleX),
                label: const T('Customer declined'),
              ),
              if (masters['capabilities']?['cancel'] == true) ...[
                const SizedBox(height: 8),
                TextButton.icon(
                  onPressed: () => Navigator.pop(context, 'cancel'),
                  icon: const Icon(LucideIcons.ban),
                  label: const T('Cancel job'),
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
                    fontWeight: FontWeight.w700,
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
            child: const T('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const T('Continue'),
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
        title: const T('Complete service?'),
        content: const T(
          'The job will be closed and a customer feedback link will be generated.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const T('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const T('Complete service'),
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
  @override
  Widget build(BuildContext context) {
    if (loading && job == null) {
      return const Scaffold(body: LoadingView(message: 'Opening job…'));
    }
    if (job == null) {
      return Scaffold(
        appBar: AppBar(title: const T('Service job')),
        body: EmptyState(
          icon: LucideIcons.cloudOff,
          title: 'Unable to open this service job',
          message: error,
          actionLabel: 'Retry',
          onAction: load,
        ),
      );
    }

    final scheduledAt = DateTime.tryParse(
      job?['scheduled_at']?.toString() ?? '',
    )?.toLocal();
    final equipment = job?['equipment'] is Map
        ? Map<String, dynamic>.from(job!['equipment'])
        : null;
    final primaryLabel = job?['primary_action']?['label']?.toString();
    final status = job?['status']?.toString() ?? 'assigned';
    final priority = job?['priority']?.toString() ?? 'normal';

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(job?['job_no']?.toString() ?? 'Service job'),
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
          IconButton(
            tooltip: 'Refresh',
            onPressed: load,
            icon: const Icon(LucideIcons.refreshCw),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
          children: [
            _Progress(status: status),
            if (error != null) ...[
              const SizedBox(height: 12),
              NoticeBox(error!),
            ],
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        Pill(
                          job?['status_label']?.toString() ?? status,
                          tone: Tone.primary,
                        ),
                        if (priority != 'normal')
                          Pill(
                            priority.replaceAll('_', ' '),
                            tone:
                                priority == 'emergency' ||
                                    priority == 'very_high'
                                ? Tone.danger
                                : Tone.warning,
                          ),
                        if (job?['service_type']?['name'] != null)
                          Pill(
                            job!['service_type']['name'].toString(),
                            tone: Tone.info,
                          ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      job?['customer']?['name']?.toString() ?? 'Customer',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: AppColors.heading,
                      ),
                    ),
                    if ((job?['complaint']?.toString() ?? '').isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        job!['complaint'].toString(),
                        style: const TextStyle(
                          color: AppColors.ink,
                          height: 1.45,
                        ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: job?['customer_phone'] == null
                                ? null
                                : callCustomer,
                            icon: const Icon(LucideIcons.phone, size: 17),
                            label: const T('Call'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: openDirections,
                            icon: const Icon(LucideIcons.navigation, size: 17),
                            label: const T('Directions'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            SectionCard(
              title: 'Visit details',
              icon: LucideIcons.calendarClock,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Column(
                children: [
                  InfoRow(
                    label: 'Schedule',
                    value: scheduledAt == null
                        ? 'Not scheduled'
                        : DateFormat(
                            'EEE, d MMM · hh:mm a',
                          ).format(scheduledAt),
                  ),
                  InfoRow(
                    label: 'Contact',
                    value: job?['customer_phone']?.toString() ?? '',
                  ),
                  InfoRow(
                    label: 'Address',
                    value: job?['service_address']?.toString() ?? '',
                    stacked: true,
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            SectionCard(
              title: 'Equipment',
              icon: LucideIcons.airVent,
              padding: EdgeInsets.symmetric(
                horizontal: 16,
                vertical: equipment == null ? 16 : 4,
              ),
              child: equipment == null
                  ? const T(
                      'No equipment linked to this job.',
                      style: TextStyle(color: AppColors.muted),
                    )
                  : Column(
                      children: [
                        InfoRow(
                          label: 'Type',
                          value: equipment['equipment_type']?.toString() ?? '',
                        ),
                        InfoRow(
                          label: 'Brand / model',
                          value: [
                            equipment['brand'],
                            equipment['model'],
                          ].where((value) => value != null).join(' · '),
                        ),
                        InfoRow(
                          label: 'Capacity',
                          value: equipment['capacity']?.toString() ?? '',
                        ),
                        InfoRow(
                          label: 'Location',
                          value: equipment['location']?.toString() ?? '',
                          last: true,
                        ),
                      ],
                    ),
            ),
            if ((job?['inspections'] as List?)?.isNotEmpty ?? false) ...[
              const SizedBox(height: 12),
              _SummaryCard(job: job!),
            ],
            if (status == 'completed') ...[
              const SizedBox(height: 12),
              SectionCard(
                title: 'Service completed',
                icon: LucideIcons.badgeCheck,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const T(
                      'The service report, proforma and feedback request are available from the ERP.',
                      style: TextStyle(color: AppColors.muted, height: 1.45),
                    ),
                    if (job?['feedback_url'] != null) ...[
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: () => launchUrl(
                          Uri.parse(job!['feedback_url'].toString()),
                          mode: LaunchMode.externalApplication,
                        ),
                        icon: const Icon(LucideIcons.star, size: 17),
                        label: const T('Open customer feedback link'),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
      bottomNavigationBar: primaryLabel == null
          ? null
          : BottomActionBar(
              child: BusyButton(
                label: primaryLabel,
                icon: LucideIcons.arrowRight,
                busy: submitting,
                onPressed: handlePrimaryAction,
              ),
            ),
    );
  }
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
    return StageTracker(
      stages: [for (final stage in stages) stage[0]],
      current: current,
      allDone: status == 'completed',
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.job});

  final Map<String, dynamic> job;

  @override
  Widget build(BuildContext context) {
    final counts = [
      (
        'Checklists',
        List<dynamic>.from(job['inspections'] ?? []).length,
        LucideIcons.clipboardCheck,
      ),
      (
        'Services',
        List<dynamic>.from(job['performed_services'] ?? []).length,
        LucideIcons.wrench,
      ),
      (
        'Materials',
        List<dynamic>.from(job['materials'] ?? []).length,
        LucideIcons.package,
      ),
      (
        'Photos',
        List<dynamic>.from(job['photos'] ?? []).length,
        LucideIcons.image,
      ),
    ];
    return SectionCard(
      title: 'Service summary',
      icon: LucideIcons.chartColumn,
      child: Row(
        children: [
          for (final (label, value, icon) in counts)
            Expanded(
              child: Column(
                children: [
                  Icon(icon, size: 18, color: AppColors.primary),
                  const SizedBox(height: 6),
                  Text(
                    '$value',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: AppColors.heading,
                    ),
                  ),
                  T(
                    label,
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
