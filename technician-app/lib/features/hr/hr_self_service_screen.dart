import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';

/// Tabs of the HR screen, also opened directly from the side menu.
class HrTab {
  static const overview = 0;
  static const leave = 1;
  static const vouchers = 2;
  static const payslips = 3;
  static const holidays = 4;
}

String _date(dynamic raw, [String pattern = 'd MMM yyyy']) {
  final date = DateTime.tryParse(raw?.toString() ?? '');
  return date == null
      ? (raw?.toString() ?? '—')
      : DateFormat(pattern).format(date);
}

String _money(dynamic raw) {
  final value = num.tryParse(raw?.toString() ?? '');
  return value == null
      ? '₹${raw ?? 0}'
      : NumberFormat.currency(
          locale: 'en_IN',
          symbol: '₹',
          decimalDigits: 0,
        ).format(value);
}

Tone _statusTone(String value) => switch (value) {
  'approved' || 'paid' => Tone.success,
  'rejected' || 'cancelled' => Tone.danger,
  _ => Tone.warning,
};

class HrSelfServiceScreen extends StatefulWidget {
  const HrSelfServiceScreen({super.key, this.initialTab = HrTab.overview});

  final int initialTab;

  @override
  State<HrSelfServiceScreen> createState() => _HrSelfServiceScreenState();
}

class _HrSelfServiceScreenState extends State<HrSelfServiceScreen> {
  Map<String, dynamic>? data;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final response = await context.read<ApiClient>().dio.get(
        'hr-self-service',
      );
      data = Map<String, dynamic>.from(response.data);
    } catch (exception) {
      error = apiErrorMessage(exception, 'Unable to load HR details.');
    }
    if (mounted) setState(() => loading = false);
  }

  Widget _tab(IconData icon, String label) => Tab(
    height: 46,
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [Icon(icon, size: 16), const SizedBox(width: 7), T(label)],
    ),
  );

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 5,
    initialIndex: widget.initialTab,
    child: Scaffold(
      appBar: AppBar(
        title: const T('My HR'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: loading ? null : load,
            icon: const Icon(LucideIcons.refreshCw),
          ),
          const SizedBox(width: 4),
        ],
        bottom: TabBar(
          isScrollable: true,
          tabs: [
            _tab(LucideIcons.idCard, 'Overview'),
            _tab(LucideIcons.calendarPlus, 'Leave'),
            _tab(LucideIcons.receiptIndianRupee, 'Vouchers'),
            _tab(LucideIcons.walletCards, 'Payslips'),
            _tab(LucideIcons.calendarDays, 'Holidays'),
          ],
        ),
      ),
      body: loading && data == null
          ? const LoadingView()
          : data == null
          ? EmptyState(
              icon: LucideIcons.cloudOff,
              title: 'Unable to load HR details',
              message: error,
              actionLabel: 'Retry',
              onAction: load,
            )
          : TabBarView(
              children: [
                _Overview(data: data!, onRefresh: load),
                _LeavePanel(data: data!, onSaved: load),
                _VoucherPanel(
                  items: List<dynamic>.from(data?['vouchers'] ?? []),
                  onSaved: load,
                ),
                _Payslips(
                  items: List<dynamic>.from(data?['payslips'] ?? []),
                  onRefresh: load,
                ),
                _Holidays(
                  items: List<dynamic>.from(data?['holidays'] ?? []),
                  onRefresh: load,
                ),
              ],
            ),
    ),
  );
}

class _Overview extends StatelessWidget {
  const _Overview({required this.data, required this.onRefresh});

  final Map<String, dynamic> data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final balances = List<dynamic>.from(data['leave_balances'] ?? []);
    final user = Map<String, dynamic>.from(data['user'] ?? {});
    final profile = Map<String, dynamic>.from(user['employee_profile'] ?? {});
    final premises = List<dynamic>.from(user['premises'] ?? []);
    final name = user['name']?.toString() ?? '';
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  AppAvatar(name: name, size: 52),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 17,
                            color: AppColors.heading,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          [
                            user['employee_code'],
                            user['designation'],
                          ].where((value) => value != null).join(' · '),
                          style: const TextStyle(
                            color: AppColors.muted,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          SectionCard(
            title: 'Employment',
            icon: LucideIcons.briefcaseBusiness,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Column(
              children: [
                InfoRow(
                  label: 'Department',
                  value: user['department']?.toString() ?? '',
                ),
                InfoRow(
                  label: 'Joining date',
                  value: profile['date_of_joining'] == null
                      ? ''
                      : _date(profile['date_of_joining']),
                ),
                InfoRow(
                  label: 'Employment',
                  value: toBeginningOfSentenceCase(
                    (profile['employment_type']?.toString() ?? '').replaceAll(
                      '_',
                      ' ',
                    ),
                  ),
                ),
                InfoRow(
                  label: 'Premises',
                  value: premises
                      .map((item) => item['name'])
                      .whereType<Object>()
                      .join(', '),
                  last: true,
                ),
              ],
            ),
          ),
          const SectionLabel('Leave balance'),
          if (balances.isEmpty)
            const Card(
              child: EmptyState(
                compact: true,
                icon: LucideIcons.calendarX2,
                title: 'No leave balance',
                message: 'Leave types assigned to you by HR appear here.',
              ),
            )
          else
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.55,
              children: [
                for (final item in balances)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item['name']?.toString() ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                          const Spacer(),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                '${item['available']}',
                                style: const TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.success,
                                  height: 1,
                                ),
                              ),
                              const SizedBox(width: 4),
                              const T(
                                'available',
                                style: TextStyle(
                                  color: AppColors.muted,
                                  fontSize: 11.5,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            '${context.tr('Used')} ${item['used']} · ${context.tr('Pending')} ${item['pending']}',
                            style: const TextStyle(
                              color: AppColors.muted,
                              fontSize: 11.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

class _Holidays extends StatelessWidget {
  const _Holidays({required this.items, required this.onRefresh});

  final List<dynamic> items;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: items.isEmpty
        ? const EmptyState(
            icon: LucideIcons.calendarDays,
            title: 'No holidays published',
            message: 'The holiday calendar from HR appears here.',
          )
        : ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (_, index) {
              final item = items[index];
              final date = DateTime.tryParse(
                item['holiday_date']?.toString() ?? '',
              );
              final past =
                  date != null &&
                  DateUtils.dateOnly(
                    date,
                  ).isBefore(DateUtils.dateOnly(DateTime.now()));
              return Card(
                color: past ? AppColors.surface2 : Colors.white,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      Container(
                        width: 50,
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        decoration: BoxDecoration(
                          color: past
                              ? AppColors.slateSoft
                              : AppColors.primarySoft,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                        child: Column(
                          children: [
                            Text(
                              date == null
                                  ? '—'
                                  : DateFormat('dd').format(date),
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                color: past
                                    ? AppColors.slate
                                    : AppColors.primary,
                              ),
                            ),
                            Text(
                              date == null
                                  ? ''
                                  : DateFormat(
                                      'MMM',
                                    ).format(date).toUpperCase(),
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                color: past
                                    ? AppColors.slate
                                    : AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item['name']?.toString() ?? '',
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                                fontSize: 14,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              date == null
                                  ? ''
                                  : DateFormat('EEEE').format(date),
                              style: const TextStyle(
                                color: AppColors.muted,
                                fontSize: 12.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (item['optional'] == true)
                        const Pill('Optional', tone: Tone.info),
                    ],
                  ),
                ),
              );
            },
          ),
  );
}

class _LeavePanel extends StatelessWidget {
  const _LeavePanel({required this.data, required this.onSaved});

  final Map<String, dynamic> data;
  final Future<void> Function() onSaved;

  @override
  Widget build(BuildContext context) {
    final requests = List<dynamic>.from(data['leave_requests'] ?? []);
    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: onSaved,
            child: requests.isEmpty
                ? const EmptyState(
                    icon: LucideIcons.calendarPlus,
                    title: 'No leave requests',
                    message:
                        'Requests you apply for appear here with their approval status.',
                  )
                : ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                    itemCount: requests.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final item = requests[index];
                      final status = item['status']?.toString() ?? 'pending';
                      return Card(
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Row(
                            children: [
                              IconTile(
                                LucideIcons.calendarRange,
                                tone: _statusTone(status),
                                size: 40,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      item['leave_type']?['name']?.toString() ??
                                          'Leave',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w600,
                                        fontSize: 14,
                                      ),
                                    ),
                                    const SizedBox(height: 3),
                                    Text(
                                      '${_date(item['from_date'], 'd MMM')} – ${_date(item['to_date'])} · ${item['total_days']} ${context.tr('days')}',
                                      style: const TextStyle(
                                        color: AppColors.muted,
                                        fontSize: 12.5,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Pill(
                                status.replaceAll('_', ' '),
                                tone: _statusTone(status),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ),
        BottomActionBar(
          child: FilledButton.icon(
            onPressed: () => _leaveSheet(context),
            icon: const Icon(LucideIcons.calendarPlus, size: 18),
            label: const T('Apply for leave'),
          ),
        ),
      ],
    );
  }

  Future<void> _leaveSheet(BuildContext context) async {
    final reason = TextEditingController();
    DateTime? from;
    DateTime? to;
    int? typeId;
    XFile? document;
    final types = List<dynamic>.from(data['leave_types'] ?? []);
    final submit = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setState) => Padding(
          padding: EdgeInsets.fromLTRB(
            20,
            0,
            20,
            20 + MediaQuery.viewInsetsOf(sheetContext).bottom,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SheetHeader(
                  icon: LucideIcons.calendarPlus,
                  title: 'Apply for leave',
                  subtitle: 'Your manager is notified as soon as you submit.',
                ),
                DropdownButtonFormField<int>(
                  initialValue: typeId,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: sheetContext.tr('Leave type *'),
                  ),
                  items: [
                    for (final item in types)
                      DropdownMenuItem<int>(
                        value: item['id'] as int,
                        child: Text(item['name'].toString()),
                      ),
                  ],
                  onChanged: (value) => setState(() => typeId = value),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _DateBox(
                        label: 'From *',
                        value: from,
                        onTap: () async {
                          final value = await showDatePicker(
                            context: sheetContext,
                            firstDate: DateTime.now(),
                            lastDate: DateTime.now().add(
                              const Duration(days: 730),
                            ),
                            initialDate: from ?? DateTime.now(),
                          );
                          if (value != null) {
                            setState(() {
                              from = value;
                              if (to != null && to!.isBefore(value)) to = value;
                            });
                          }
                        },
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DateBox(
                        label: 'To *',
                        value: to,
                        onTap: () async {
                          final value = await showDatePicker(
                            context: sheetContext,
                            firstDate: from ?? DateTime.now(),
                            lastDate: DateTime.now().add(
                              const Duration(days: 730),
                            ),
                            initialDate: to ?? from ?? DateTime.now(),
                          );
                          if (value != null) setState(() => to = value);
                        },
                      ),
                    ),
                  ],
                ),
                if (from != null && to != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    '${to!.difference(from!).inDays + 1} ${sheetContext.tr('calendar days')}',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                TextField(
                  controller: reason,
                  minLines: 3,
                  maxLines: 5,
                  onChanged: (_) => setState(() {}),
                  scrollPadding: const EdgeInsets.only(bottom: 120),
                  textCapitalization: TextCapitalization.sentences,
                  decoration: InputDecoration(
                    labelText: sheetContext.tr('Reason *'),
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picked = await ImagePicker().pickImage(
                      source: ImageSource.gallery,
                      imageQuality: 80,
                    );
                    if (picked != null) setState(() => document = picked);
                  },
                  icon: Icon(
                    document == null
                        ? LucideIcons.paperclip
                        : LucideIcons.fileCheck,
                    size: 17,
                  ),
                  label: Text(
                    document?.name ??
                        sheetContext.tr('Attach document (if required)'),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed:
                      typeId == null ||
                          from == null ||
                          to == null ||
                          reason.text.trim().isEmpty
                      ? null
                      : () => Navigator.pop(sheetContext, true),
                  icon: const Icon(LucideIcons.send, size: 17),
                  label: const T('Submit request'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (submit != true || !context.mounted) return;
    try {
      await context.read<ApiClient>().dio.post(
        'leave-requests',
        data: FormData.fromMap({
          'leave_type_id': typeId,
          'from_date': DateFormat('yyyy-MM-dd').format(from!),
          'to_date': DateFormat('yyyy-MM-dd').format(to!),
          'reason': reason.text.trim(),
          if (document != null)
            'document': await MultipartFile.fromFile(
              document!.path,
              filename: document!.name,
            ),
        }),
      );
      if (context.mounted) {
        showAppSnack(context, 'Leave request submitted.', tone: Tone.success);
      }
      await onSaved();
    } catch (error) {
      if (context.mounted) {
        showAppSnack(
          context,
          apiErrorMessage(error, 'Unable to submit leave.'),
          tone: Tone.danger,
        );
      }
    }
  }
}

class _VoucherPanel extends StatelessWidget {
  const _VoucherPanel({required this.items, required this.onSaved});

  final List<dynamic> items;
  final Future<void> Function() onSaved;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Expanded(
        child: RefreshIndicator(
          onRefresh: onSaved,
          child: items.isEmpty
              ? const EmptyState(
                  icon: LucideIcons.receiptIndianRupee,
                  title: 'No expense vouchers',
                  message:
                      'Submit fuel, travel and site expenses with a photo of the receipt.',
                )
              : ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final item = items[index];
                    final status = item['status']?.toString() ?? 'pending';
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          children: [
                            IconTile(
                              LucideIcons.receipt,
                              tone: _statusTone(status),
                              size: 40,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    item['category']?.toString() ?? 'Expense',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                      fontSize: 14,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    '${item['voucher_no'] ?? ''} · ${_date(item['expense_date'])}',
                                    style: const TextStyle(
                                      color: AppColors.muted,
                                      fontSize: 12.5,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(
                                  _money(item['amount']),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 14.5,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Pill(
                                  status.replaceAll('_', ' '),
                                  tone: _statusTone(status),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
      ),
      BottomActionBar(
        child: FilledButton.icon(
          onPressed: () => _voucherSheet(context),
          icon: const Icon(LucideIcons.receiptIndianRupee, size: 18),
          label: const T('Add expense voucher'),
        ),
      ),
    ],
  );

  Future<void> _voucherSheet(BuildContext context) async {
    final amount = TextEditingController();
    final description = TextEditingController();
    final category = TextEditingController();
    DateTime date = DateTime.now();
    XFile? receipt;
    final submit = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setState) {
          final ready =
              receipt != null &&
              category.text.trim().isNotEmpty &&
              (num.tryParse(amount.text) ?? 0) > 0 &&
              description.text.trim().isNotEmpty;
          return Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              0,
              20,
              20 + MediaQuery.viewInsetsOf(sheetContext).bottom,
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SheetHeader(
                    icon: LucideIcons.receiptIndianRupee,
                    title: 'Expense voucher',
                    subtitle: 'A receipt photo is required for every voucher.',
                  ),
                  TextField(
                    controller: category,
                    onChanged: (_) => setState(() {}),
                    textCapitalization: TextCapitalization.sentences,
                    decoration: InputDecoration(
                      labelText: sheetContext.tr('Category *'),
                      hintText: sheetContext.tr(
                        'Fuel, travel, food, material…',
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: amount,
                          onChanged: (_) => setState(() {}),
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'^\d*\.?\d{0,2}'),
                            ),
                          ],
                          decoration: InputDecoration(
                            labelText: sheetContext.tr('Amount *'),
                            prefixText: '₹ ',
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _DateBox(
                          label: 'Expense date',
                          value: date,
                          onTap: () async {
                            final value = await showDatePicker(
                              context: sheetContext,
                              firstDate: DateTime.now().subtract(
                                const Duration(days: 365),
                              ),
                              lastDate: DateTime.now(),
                              initialDate: date,
                            );
                            if (value != null) setState(() => date = value);
                          },
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: description,
                    onChanged: (_) => setState(() {}),
                    minLines: 2,
                    maxLines: 4,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: InputDecoration(
                      labelText: sheetContext.tr('Description *'),
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (receipt != null)
                    Container(
                      height: 140,
                      margin: const EdgeInsets.only(bottom: 10),
                      clipBehavior: Clip.antiAlias,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                      child: Image.file(
                        File(receipt!.path),
                        fit: BoxFit.cover,
                        width: double.infinity,
                      ),
                    ),
                  OutlinedButton.icon(
                    onPressed: () async {
                      final value = await ImagePicker().pickImage(
                        source: ImageSource.camera,
                        imageQuality: 80,
                      );
                      if (value != null) setState(() => receipt = value);
                    },
                    icon: const Icon(LucideIcons.camera, size: 17),
                    label: T(
                      receipt == null
                          ? 'Photograph receipt *'
                          : 'Retake receipt photo',
                    ),
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: ready
                        ? () => Navigator.pop(sheetContext, true)
                        : null,
                    icon: const Icon(LucideIcons.send, size: 17),
                    label: const T('Submit voucher'),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
    if (submit != true || receipt == null || !context.mounted) return;
    try {
      await context.read<ApiClient>().dio.post(
        'expense-vouchers',
        data: FormData.fromMap({
          'expense_date': DateFormat('yyyy-MM-dd').format(date),
          'category': category.text.trim(),
          'amount': amount.text.trim(),
          'description': description.text.trim(),
          'receipt': await MultipartFile.fromFile(
            receipt!.path,
            filename: receipt!.name,
          ),
        }),
      );
      if (context.mounted) {
        showAppSnack(context, 'Expense voucher submitted.', tone: Tone.success);
      }
      await onSaved();
    } catch (error) {
      if (context.mounted) {
        showAppSnack(
          context,
          apiErrorMessage(error, 'Unable to submit voucher.'),
          tone: Tone.danger,
        );
      }
    }
  }
}

class _Payslips extends StatelessWidget {
  const _Payslips({required this.items, required this.onRefresh});

  final List<dynamic> items;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: items.isEmpty
        ? const EmptyState(
            icon: LucideIcons.walletCards,
            title: 'No salary slips yet',
            message:
                'Your monthly salary slips appear here once payroll is processed.',
          )
        : ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final item = items[index];
              return Card(
                child: InkWell(
                  onTap: () async {
                    final rawUrl = item['download_url']?.toString();
                    if (rawUrl == null || rawUrl.isEmpty) return;
                    final opened = await launchUrl(
                      Uri.parse(rawUrl),
                      mode: LaunchMode.externalApplication,
                    );
                    if (!opened && context.mounted) {
                      showAppSnack(
                        context,
                        'Unable to open salary slip.',
                        tone: Tone.danger,
                      );
                    }
                  },
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            const IconTile(
                              LucideIcons.walletCards,
                              tone: Tone.success,
                              size: 40,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                item['payroll_run']?['payroll_month']
                                        ?.toString() ??
                                    'Payslip',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14.5,
                                ),
                              ),
                            ),
                            Text(
                              _money(item['net_amount']),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 16,
                                color: AppColors.success,
                              ),
                            ),
                          ],
                        ),
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 10),
                          child: Divider(),
                        ),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${context.tr('Gross')} ${_money(item['gross_amount'])} · ${context.tr('Deductions')} ${_money(item['deduction_amount'])}',
                                style: const TextStyle(
                                  color: AppColors.muted,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                            const Icon(
                              LucideIcons.fileDown,
                              size: 15,
                              color: AppColors.primary,
                            ),
                            const SizedBox(width: 4),
                            const T(
                              'PDF',
                              style: TextStyle(
                                color: AppColors.primary,
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
  );
}

class _DateBox extends StatelessWidget {
  const _DateBox({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final DateTime? value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(AppRadius.md),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: context.tr(label),
        suffixIcon: const Icon(LucideIcons.calendar, size: 17),
      ),
      child: Text(
        value == null ? '' : DateFormat('d MMM yyyy').format(value!),
        style: const TextStyle(fontSize: 14),
      ),
    ),
  );
}
