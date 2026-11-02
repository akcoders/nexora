import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import 'complaint_visit_screen.dart';

/// Loads complaint visits assigned to the signed-in technician (or assistant).
Future<List<dynamic>> fetchComplaintVisits(
  BuildContext context, {
  String scope = 'open',
}) async {
  final response = await context.read<ApiClient>().dio.get(
    'complaint-visits',
    queryParameters: {'scope': scope},
  );
  return List<dynamic>.from(response.data['data'] ?? []);
}

Tone visitStatusTone(String? status) => switch (status) {
  'completed' => Tone.success,
  'cancelled' => Tone.slate,
  'accepted' => Tone.primary,
  'en_route' || 'on_site' => Tone.warning,
  _ => Tone.info,
};

Tone priorityTone(String? priority) => switch (priority) {
  'critical' => Tone.danger,
  'high' => Tone.warning,
  'medium' => Tone.info,
  _ => Tone.slate,
};

class ComplaintVisitList extends StatefulWidget {
  const ComplaintVisitList({super.key, this.scope = 'open'});

  final String scope;

  @override
  State<ComplaintVisitList> createState() => _ComplaintVisitListState();
}

class _ComplaintVisitListState extends State<ComplaintVisitList> {
  late Future<List<dynamic>> future = fetchComplaintVisits(
    context,
    scope: widget.scope,
  );

  Future<void> refresh() async {
    final next = fetchComplaintVisits(context, scope: widget.scope);
    setState(() => future = next);
    await next.catchError((_) => <dynamic>[]);
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<List<dynamic>>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState == ConnectionState.waiting) {
        return const LoadingView();
      }
      if (snapshot.hasError) {
        return RefreshIndicator(
          onRefresh: refresh,
          child: EmptyState(
            icon: LucideIcons.cloudOff,
            title: 'Unable to load complaint visits',
            message: apiErrorMessage(
              snapshot.error!,
              'Check your connection and try again.',
            ),
            actionLabel: 'Retry',
            onAction: refresh,
          ),
        );
      }
      final visits = snapshot.data ?? [];
      if (visits.isEmpty) {
        return RefreshIndicator(
          onRefresh: refresh,
          child: const EmptyState(
            icon: LucideIcons.clipboardCheck,
            title: 'No open complaint visits',
            message:
                'Complaint visits assigned to you by the service desk appear here.',
          ),
        );
      }
      return RefreshIndicator(
        onRefresh: refresh,
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
          itemCount: visits.length + 1,
          separatorBuilder: (_, index) =>
              SizedBox(height: index == 0 ? 12 : 10),
          itemBuilder: (context, index) {
            if (index == 0) {
              final critical = visits.where((visit) {
                final priority = visit['complaint']?['priority'];
                return priority == 'critical' || priority == 'high';
              }).length;
              return ListSummary(
                title: 'Complaint visits',
                subtitle: 'Visits assigned to you or your team',
                count: visits.length,
                caption: critical == 0 ? null : '$critical high priority',
              );
            }
            final visit = Map<String, dynamic>.from(visits[index - 1]);
            return ComplaintVisitCard(
              visit: visit,
              onTap: () async {
                await Navigator.push<void>(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        ComplaintVisitScreen(visitId: visit['id'] as int),
                  ),
                );
                if (mounted) await refresh();
              },
            );
          },
        ),
      );
    },
  );
}

/// Slim header above a work list: title, caption and a count.
class ListSummary extends StatelessWidget {
  const ListSummary({
    super.key,
    required this.title,
    required this.subtitle,
    required this.count,
    this.caption,
  });

  final String title;
  final String subtitle;
  final int count;
  final String? caption;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: AppColors.heading,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              style: const TextStyle(fontSize: 12, color: AppColors.muted),
            ),
          ],
        ),
      ),
      if (caption != null) ...[
        Pill(caption!, tone: Tone.danger),
        const SizedBox(width: 8),
      ],
      Container(
        constraints: const BoxConstraints(minWidth: 34),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: AppColors.navy,
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        child: Text(
          '$count',
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
        ),
      ),
    ],
  );
}

class ComplaintVisitCard extends StatelessWidget {
  const ComplaintVisitCard({
    super.key,
    required this.visit,
    required this.onTap,
  });

  final Map<String, dynamic> visit;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final complaint = Map<String, dynamic>.from(visit['complaint'] ?? {});
    final appointment = DateTime.tryParse(
      visit['appointment_at']?.toString() ?? '',
    )?.toLocal();
    final ended = DateTime.tryParse(
      visit['ended_at']?.toString() ?? '',
    )?.toLocal();
    final completed = visit['status'] == 'completed';
    final priority = complaint['priority']?.toString();
    return Card(
      child: InkWell(
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            border: Border(
              left: BorderSide(color: priorityTone(priority).color, width: 3),
            ),
          ),
          padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        Pill(
                          visit['status_label']?.toString() ?? 'Scheduled',
                          tone: visitStatusTone(visit['status']?.toString()),
                        ),
                        if (priority != null)
                          Pill(
                            complaint['priority_label']?.toString() ?? priority,
                            tone: priorityTone(priority),
                          ),
                        if (visit['role'] == 'assistant')
                          const Pill('Assistant', tone: Tone.purple),
                      ],
                    ),
                  ),
                  Text(
                    '${complaint['complaint_no'] ?? ''} · ${visit['label'] ?? ''}',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                complaint['customer_name']?.toString() ?? 'Customer',
                style: const TextStyle(
                  fontSize: 15.5,
                  fontWeight: FontWeight.w700,
                  color: AppColors.heading,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                complaint['nature_of_complaint']?.toString() ??
                    'Complaint visit',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.ink, fontSize: 13),
              ),
              if ((complaint['equipment_label']?.toString() ?? '').isNotEmpty ||
                  (complaint['site_location']?.toString() ?? '')
                      .isNotEmpty) ...[
                const SizedBox(height: 8),
                Wrap(
                  spacing: 14,
                  runSpacing: 4,
                  children: [
                    if ((complaint['equipment_label']?.toString() ?? '')
                        .isNotEmpty)
                      _Meta(
                        icon: LucideIcons.airVent,
                        text: complaint['equipment_label'].toString(),
                      ),
                    if ((complaint['site_location']?.toString() ?? '')
                        .isNotEmpty)
                      _Meta(
                        icon: LucideIcons.mapPin,
                        text: complaint['site_location'].toString(),
                      ),
                  ],
                ),
              ],
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 10),
                child: Divider(),
              ),
              Row(
                children: [
                  Icon(
                    completed
                        ? LucideIcons.circleCheckBig
                        : LucideIcons.calendarClock,
                    size: 15,
                    color: AppColors.muted,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      completed
                          ? (ended == null
                                ? 'Completed'
                                : 'Completed ${DateFormat('d MMM, hh:mm a').format(ended)}')
                          : (appointment == null
                                ? 'Appointment not set'
                                : DateFormat(
                                    'EEE, d MMM · hh:mm a',
                                  ).format(appointment)),
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  Text(
                    completed
                        ? (visit['outcome_label']?.toString() ?? 'View report')
                        : 'Open visit',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const Icon(
                    LucideIcons.chevronRight,
                    size: 16,
                    color: AppColors.primary,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: AppColors.secondary),
      const SizedBox(width: 5),
      ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 220),
        child: Text(
          text,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 12, color: AppColors.ink),
        ),
      ),
    ],
  );
}
