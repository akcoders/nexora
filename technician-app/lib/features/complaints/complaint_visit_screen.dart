import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'complaint_visit_list.dart';
import 'complaint_visit_report_screen.dart';

String formatStamp(dynamic raw, [String pattern = 'd MMM yyyy, hh:mm a']) {
  final date = DateTime.tryParse(raw?.toString() ?? '');
  return date == null ? '—' : DateFormat(pattern).format(date.toLocal());
}

/// Visit timeline steps, in the order a technician moves through them.
const visitSteps = [
  (
    field: 'accepted_at',
    label: 'Accept visit',
    done: 'Accepted',
    icon: LucideIcons.thumbsUp,
  ),
  (
    field: 'en_route_at',
    label: 'Start journey',
    done: 'En route',
    icon: LucideIcons.navigation,
  ),
  (
    field: 'arrived_at',
    label: 'Reached site',
    done: 'Reached site',
    icon: LucideIcons.mapPinCheck,
  ),
  (
    field: 'started_at',
    label: 'Start work',
    done: 'Work started',
    icon: LucideIcons.play,
  ),
];

class ComplaintVisitScreen extends StatefulWidget {
  const ComplaintVisitScreen({super.key, required this.visitId});

  final int visitId;

  @override
  State<ComplaintVisitScreen> createState() => _ComplaintVisitScreenState();
}

class _ComplaintVisitScreenState extends State<ComplaintVisitScreen> {
  Map<String, dynamic>? visit;
  Map<String, dynamic> complaint = {};
  Map<String, dynamic> form = {};
  List<dynamic> previousVisits = [];
  bool canReport = false;
  bool loading = true;
  bool saving = false;
  String? error;

  Dio get api => context.read<ApiClient>().dio;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = visit == null;
      error = null;
    });
    try {
      final response = await api.get('complaint-visits/${widget.visitId}');
      final data = Map<String, dynamic>.from(response.data);
      visit = Map<String, dynamic>.from(data['visit'] ?? {});
      complaint = Map<String, dynamic>.from(data['complaint'] ?? {});
      form = Map<String, dynamic>.from(data['form'] ?? {});
      previousVisits = List<dynamic>.from(data['previous_visits'] ?? []);
      canReport = data['can_report'] == true;
    } catch (exception) {
      error = apiErrorMessage(
        exception,
        'Unable to open this complaint visit.',
      );
    }
    if (mounted) setState(() => loading = false);
  }

  /// Next timeline step that has not been stamped yet.
  ({String field, String label, String done, IconData icon})? get nextStep {
    for (final step in visitSteps) {
      if (visit?[step.field] == null) return step;
    }
    return null;
  }

  Future<void> stamp(String field, String label) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: T(label),
        content: Text(
          '${context.tr('The current time will be recorded')}: ${DateFormat('d MMM, hh:mm a').format(DateTime.now())}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const T('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const T('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => saving = true);
    try {
      final response = await api.post(
        'complaints/${complaint['id']}/visits/${widget.visitId}/report',
        data: {field: DateTime.now().toIso8601String()},
      );
      if (response.data['visit'] is Map) {
        visit = Map<String, dynamic>.from(response.data['visit']);
      }
      if (mounted) {
        showAppSnack(context, '$label recorded.', tone: Tone.success);
      }
      await load();
    } catch (exception) {
      if (mounted) {
        showAppSnack(context, apiErrorMessage(exception), tone: Tone.danger);
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> openReport() async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ComplaintVisitReportScreen(
          visit: visit!,
          complaint: complaint,
          form: form,
        ),
      ),
    );
    if (saved == true) await load();
  }

  Future<void> call(String? phone) async {
    if (phone == null || phone.trim().isEmpty) return;
    await launchUrl(Uri(scheme: 'tel', path: phone.replaceAll(' ', '')));
  }

  Future<void> directions() async {
    final address = [complaint['site_location'], complaint['address']]
        .where((value) => value != null && value.toString().trim().isNotEmpty)
        .join(', ');
    if (address.isEmpty) return;
    await launchUrl(
      Uri.parse(
        'https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(address)}',
      ),
      mode: LaunchMode.externalApplication,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(body: LoadingView(message: 'Opening visit…'));
    }
    if (visit == null) {
      return Scaffold(
        appBar: AppBar(title: const T('Complaint visit')),
        body: EmptyState(
          icon: LucideIcons.cloudOff,
          title: 'Unable to open this visit',
          message: error,
          actionLabel: 'Retry',
          onAction: load,
        ),
      );
    }

    final status = visit!['status']?.toString();
    final completed = status == 'completed';
    final stepIndex = visitSteps.indexWhere(
      (step) => visit![step.field] == null,
    );
    final next = nextStep;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${complaint['complaint_no'] ?? 'Complaint'} · ${visit!['label'] ?? ''}',
            ),
            Text(
              complaint['customer']?['name']?.toString() ??
                  complaint['customer_name']?.toString() ??
                  '',
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
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
          children: [
            StageTracker(
              stages: const [
                'Scheduled',
                'Accepted',
                'En route',
                'On site',
                'Working',
                'Done',
              ],
              // Stage 0 (scheduled) is always done; all four stamps means work is under way.
              current: stepIndex < 0 ? visitSteps.length : stepIndex + 1,
              allDone: completed,
            ),
            if (error != null) ...[
              const SizedBox(height: 12),
              NoticeBox(error!),
            ],
            const SizedBox(height: 12),
            _CustomerCard(
              complaint: complaint,
              onCall: call,
              onDirections: directions,
            ),
            const SizedBox(height: 12),
            SectionCard(
              title: 'Visit details',
              icon: LucideIcons.calendarClock,
              trailing: Pill(
                visit!['status_label']?.toString() ?? 'Scheduled',
                tone: visitStatusTone(status),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Column(
                children: [
                  InfoRow(
                    label: 'Appointment',
                    value: formatStamp(visit!['appointment_at']),
                  ),
                  InfoRow(
                    label: 'Your role',
                    value: visit!['role'] == 'assistant'
                        ? 'Assistant'
                        : 'Lead technician',
                  ),
                  InfoRow(
                    label: 'Team',
                    value: List<dynamic>.from(visit!['team'] ?? []).join(', '),
                  ),
                  if ((visit!['reason_for_visit']?.toString() ?? '').isNotEmpty)
                    InfoRow(
                      label: 'Reason for visit',
                      value: visit!['reason_for_visit'].toString(),
                      stacked: true,
                    ),
                  InfoRow(
                    label: 'Special instructions',
                    value: visit!['special_instructions']?.toString() ?? 'None',
                    stacked: true,
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            SectionCard(
              title: 'Visit timeline',
              icon: LucideIcons.listChecks,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Column(
                children: [
                  for (final step in visitSteps)
                    _TimelineRow(
                      label: step.done,
                      icon: step.icon,
                      value: visit![step.field],
                    ),
                  _TimelineRow(
                    label: 'Work finished',
                    icon: LucideIcons.flagTriangleRight,
                    value: visit!['ended_at'],
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            _EquipmentCard(complaint: complaint),
            if ((complaint['description']?.toString() ?? '').isNotEmpty) ...[
              const SizedBox(height: 12),
              SectionCard(
                title: 'Complaint description',
                icon: LucideIcons.messageSquareText,
                child: Text(
                  complaint['description'].toString(),
                  style: const TextStyle(height: 1.5),
                ),
              ),
            ],
            if (completed ||
                (visit!['problem_diagnosis']?.toString() ?? '').isNotEmpty) ...[
              const SizedBox(height: 12),
              _ReportSummary(visit: visit!),
            ],
            if (previousVisits.isNotEmpty) ...[
              const SizedBox(height: 12),
              SectionCard(
                title: 'Previous visits',
                icon: LucideIcons.history,
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (final (index, raw) in previousVisits.indexed)
                      _PreviousVisit(
                        visit: Map<String, dynamic>.from(raw),
                        last: index == previousVisits.length - 1,
                      ),
                  ],
                ),
              ),
            ],
            if (!canReport && !completed) ...[
              const SizedBox(height: 12),
              const NoticeBox(
                'You can view this visit. Only the assigned team can update it while it is open.',
                tone: Tone.info,
              ),
            ],
          ],
        ),
      ),
      bottomNavigationBar: !canReport || completed
          ? null
          : BottomActionBar(
              child: next != null
                  ? Row(
                      children: [
                        if (visit!['accepted_at'] != null) ...[
                          OutlinedButton.icon(
                            onPressed: saving ? null : openReport,
                            icon: const Icon(LucideIcons.fileText, size: 18),
                            label: const T('Report'),
                          ),
                          const SizedBox(width: 10),
                        ],
                        Expanded(
                          child: BusyButton(
                            label: next.label,
                            icon: next.icon,
                            busy: saving,
                            onPressed: () => stamp(next.field, next.done),
                          ),
                        ),
                      ],
                    )
                  : BusyButton(
                      label: 'Fill visit report',
                      icon: LucideIcons.clipboardPen,
                      busy: saving,
                      onPressed: openReport,
                    ),
            ),
    );
  }
}

class _CustomerCard extends StatelessWidget {
  const _CustomerCard({
    required this.complaint,
    required this.onCall,
    required this.onDirections,
  });

  final Map<String, dynamic> complaint;
  final Future<void> Function(String?) onCall;
  final Future<void> Function() onDirections;

  @override
  Widget build(BuildContext context) {
    final priority = complaint['priority']?.toString();
    final address = [complaint['site_location'], complaint['address']]
        .where((value) => value != null && value.toString().trim().isNotEmpty)
        .join('\n');
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                if (priority != null)
                  Pill(
                    complaint['priority_label']?.toString() ?? priority,
                    tone: priorityTone(priority),
                  ),
                if (complaint['call_type_label'] != null)
                  Pill(
                    complaint['call_type_label'].toString(),
                    tone: Tone.info,
                  ),
                if (complaint['status_label'] != null)
                  Pill(complaint['status_label'].toString(), tone: Tone.slate),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              complaint['customer']?['name']?.toString() ??
                  complaint['customer_name']?.toString() ??
                  'Customer',
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.heading,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              complaint['nature_of_complaint']?.toString() ?? '',
              style: const TextStyle(color: AppColors.ink, fontSize: 13.5),
            ),
            const SizedBox(height: 12),
            InfoRow(
              icon: LucideIcons.userRound,
              label: 'Contact',
              value: [
                complaint['contact_person'],
                complaint['contact_no'],
              ].whereType<Object>().join(' · '),
              stacked: true,
            ),
            if (complaint['alternate_no'] != null)
              InfoRow(
                icon: LucideIcons.phone,
                label: 'Alternate no.',
                value: complaint['alternate_no'].toString(),
              ),
            InfoRow(
              icon: LucideIcons.mapPin,
              label: 'Site',
              value: address,
              stacked: true,
              last: true,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: complaint['contact_no'] == null
                        ? null
                        : () => onCall(complaint['contact_no']?.toString()),
                    icon: const Icon(LucideIcons.phone, size: 17),
                    label: const T('Call'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: address.isEmpty ? null : onDirections,
                    icon: const Icon(LucideIcons.navigation, size: 17),
                    label: const T('Directions'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EquipmentCard extends StatelessWidget {
  const _EquipmentCard({required this.complaint});

  final Map<String, dynamic> complaint;

  @override
  Widget build(BuildContext context) {
    final rows =
        <(String, dynamic)>[
              (
                'Equipment type',
                complaint['equipment_type_label'] ??
                    complaint['equipment_type'],
              ),
              ('Tag', complaint['equipment_tag']),
              (
                'Make / model',
                [
                  complaint['make'],
                  complaint['model'],
                ].where((value) => value != null).join(' · '),
              ),
              ('Capacity', complaint['capacity']),
              ('Serial no.', complaint['serial_no']),
              ('Unit location', complaint['unit_location']),
              ('Service category', complaint['service_category_label']),
            ]
            .where(
              (row) => row.$2 != null && row.$2.toString().trim().isNotEmpty,
            )
            .toList();
    return SectionCard(
      title: 'Equipment',
      icon: LucideIcons.airVent,
      padding: EdgeInsets.symmetric(
        horizontal: 16,
        vertical: rows.isEmpty ? 16 : 4,
      ),
      child: rows.isEmpty
          ? const T(
              'No equipment linked to this complaint.',
              style: TextStyle(color: AppColors.muted),
            )
          : Column(
              children: [
                for (final (index, row) in rows.indexed)
                  InfoRow(
                    label: row.$1,
                    value: row.$2.toString(),
                    last: index == rows.length - 1,
                  ),
              ],
            ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({
    required this.label,
    required this.icon,
    required this.value,
    this.last = false,
  });

  final String label;
  final IconData icon;
  final dynamic value;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final done = value != null;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 28,
            child: Column(
              children: [
                const SizedBox(height: 8),
                Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: done ? AppColors.successSoft : AppColors.surface2,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: done ? AppColors.success : AppColors.borderStrong,
                    ),
                  ),
                  child: Icon(
                    done ? LucideIcons.check : icon,
                    size: 12,
                    color: done ? AppColors.success : AppColors.subtle,
                  ),
                ),
                if (!last)
                  Expanded(
                    child: Container(width: 1.5, color: AppColors.border),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                children: [
                  Expanded(
                    child: T(
                      label,
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13.5,
                        color: done ? AppColors.heading : AppColors.muted,
                      ),
                    ),
                  ),
                  Text(
                    done ? formatStamp(value, 'd MMM, hh:mm a') : 'Pending',
                    style: TextStyle(
                      fontSize: 12.5,
                      color: done ? AppColors.ink : AppColors.subtle,
                    ),
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

class _ReportSummary extends StatelessWidget {
  const _ReportSummary({required this.visit});

  final Map<String, dynamic> visit;

  @override
  Widget build(BuildContext context) {
    final parts = List<dynamic>.from(visit['parts'] ?? []);
    final attachments = List<dynamic>.from(visit['attachments'] ?? []);
    final outcome = visit['outcome']?.toString();
    return SectionCard(
      title: 'Visit report',
      icon: LucideIcons.fileCheck,
      trailing: visit['outcome_label'] == null
          ? null
          : Pill(
              visit['outcome_label'].toString(),
              tone: outcome == 'resolved'
                  ? Tone.success
                  : outcome == 'pending'
                  ? Tone.danger
                  : Tone.purple,
            ),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InfoRow(
            label: 'Problem diagnosis',
            value: visit['problem_diagnosis']?.toString() ?? '',
            stacked: true,
          ),
          InfoRow(
            label: 'Work performed',
            value: visit['work_performed']?.toString() ?? '',
            stacked: true,
          ),
          if (visit['root_cause'] != null)
            InfoRow(label: 'Root cause', value: visit['root_cause'].toString()),
          if (visit['final_result'] != null)
            InfoRow(
              label: 'Unit after visit',
              value: visit['final_result'] == 'working'
                  ? 'Working OK'
                  : 'Not working',
              valueColor: visit['final_result'] == 'working'
                  ? AppColors.success
                  : AppColors.danger,
            ),
          if (visit['labour_hours'] != null)
            InfoRow(
              label: 'Labour hours',
              value: visit['labour_hours'].toString(),
            ),
          if (parts.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const T(
                    'Parts',
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                  const SizedBox(height: 6),
                  for (final part in parts)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Row(
                        children: [
                          Pill(
                            part['kind'] == 'recommended'
                                ? 'Recommended'
                                : 'Used',
                            tone: part['kind'] == 'recommended'
                                ? Tone.warning
                                : Tone.success,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              part['part_name']?.toString() ?? '',
                              style: const TextStyle(fontSize: 13),
                            ),
                          ),
                          Text(
                            '${part['quantity'] ?? ''} ${part['uom'] ?? ''}',
                            style: const TextStyle(
                              fontSize: 12.5,
                              color: AppColors.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          if (attachments.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final file in attachments)
                    ActionChip(
                      avatar: const Icon(LucideIcons.paperclip, size: 14),
                      label: Text(
                        file['name']?.toString() ?? 'Attachment',
                        overflow: TextOverflow.ellipsis,
                      ),
                      onPressed: file['url'] == null
                          ? null
                          : () => launchUrl(
                              Uri.parse(file['url'].toString()),
                              mode: LaunchMode.externalApplication,
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

class _PreviousVisit extends StatelessWidget {
  const _PreviousVisit({required this.visit, required this.last});

  final Map<String, dynamic> visit;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: AppColors.border)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              visit['label']?.toString() ?? '',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(width: 8),
            Pill(
              visit['outcome_label']?.toString() ??
                  visit['status_label']?.toString() ??
                  '',
              tone: visitStatusTone(visit['status']?.toString()),
            ),
            const Spacer(),
            Text(
              formatStamp(visit['ended_at'], 'd MMM yyyy'),
              style: const TextStyle(color: AppColors.muted, fontSize: 12),
            ),
          ],
        ),
        if (visit['technician_name'] != null) ...[
          const SizedBox(height: 4),
          Text(
            visit['technician_name'].toString(),
            style: const TextStyle(color: AppColors.muted, fontSize: 12),
          ),
        ],
        if ((visit['problem_diagnosis']?.toString() ?? '').isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            visit['problem_diagnosis'].toString(),
            style: const TextStyle(fontSize: 13, height: 1.4),
          ),
        ],
        if ((visit['work_performed']?.toString() ?? '').isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            visit['work_performed'].toString(),
            style: const TextStyle(
              fontSize: 12.5,
              color: AppColors.muted,
              height: 1.4,
            ),
          ),
        ],
      ],
    ),
  );
}
