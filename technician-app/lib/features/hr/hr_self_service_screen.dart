import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';

class HrSelfServiceScreen extends StatefulWidget {
  const HrSelfServiceScreen({super.key});

  @override
  State<HrSelfServiceScreen> createState() => _HrSelfServiceScreenState();
}

class _HrSelfServiceScreenState extends State<HrSelfServiceScreen> {
  Map<String, dynamic>? data;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) setState(() => loading = true);
    try {
      final response = await context.read<ApiClient>().dio.get(
        'hr-self-service',
      );
      data = Map<String, dynamic>.from(response.data);
    } on DioException catch (error) {
      if (mounted) {
        _message(
          error.response?.data?['message']?.toString() ??
              'Unable to load HR details.',
        );
      }
    }
    if (mounted) setState(() => loading = false);
  }

  void _message(String value, {bool success = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(value),
        backgroundColor: success ? AppColors.success : null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 5,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('My HR'),
          actions: [
            IconButton(
              onPressed: load,
              icon: const Icon(LucideIcons.refreshCw),
            ),
          ],
          bottom: const TabBar(
            isScrollable: true,
            tabs: [
              Tab(text: 'Overview'),
              Tab(text: 'Holidays'),
              Tab(text: 'Leave'),
              Tab(text: 'Vouchers'),
              Tab(text: 'Payslips'),
            ],
          ),
        ),
        body: loading && data == null
            ? const Center(child: CircularProgressIndicator())
            : TabBarView(
                children: [
                  _Overview(data: data ?? const {}),
                  _Holidays(items: List<dynamic>.from(data?['holidays'] ?? [])),
                  _LeavePanel(
                    data: data ?? const {},
                    onSaved: load,
                    message: _message,
                  ),
                  _VoucherPanel(
                    items: List<dynamic>.from(data?['vouchers'] ?? []),
                    onSaved: load,
                    message: _message,
                  ),
                  _Payslips(items: List<dynamic>.from(data?['payslips'] ?? [])),
                ],
              ),
      ),
    );
  }
}

class _Overview extends StatelessWidget {
  const _Overview({required this.data});
  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final balances = List<dynamic>.from(data['leave_balances'] ?? []);
    final user = Map<String, dynamic>.from(data['user'] ?? {});
    final profile = Map<String, dynamic>.from(user['employee_profile'] ?? {});
    final premises = List<dynamic>.from(user['premises'] ?? []);
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  user['name']?.toString() ?? '',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 20,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${user['employee_code'] ?? ''} · ${user['designation'] ?? ''}',
                  style: const TextStyle(color: AppColors.muted),
                ),
                const Divider(height: 28),
                _Info(label: 'Department', value: user['department']),
                _Info(label: 'Joining date', value: profile['date_of_joining']),
                _Info(label: 'Employment', value: profile['employment_type']),
                _Info(
                  label: 'Premises',
                  value: premises.map((item) => item['name']).join(', '),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          'Leave balance',
          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        ...balances.map(
          (item) => Card(
            child: ListTile(
              leading: const CircleAvatar(
                backgroundColor: Color(0xFFDBEAFE),
                child: Icon(LucideIcons.calendarDays, color: AppColors.primary),
              ),
              title: Text(item['name']?.toString() ?? ''),
              subtitle: Text(
                'Used ${item['used']} · Pending ${item['pending']}',
              ),
              trailing: Text(
                '${item['available']}',
                style: const TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                  color: AppColors.success,
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _Holidays extends StatelessWidget {
  const _Holidays({required this.items});
  final List<dynamic> items;
  @override
  Widget build(BuildContext context) => ListView.separated(
    padding: const EdgeInsets.all(18),
    itemCount: items.length,
    separatorBuilder: (_, _) => const SizedBox(height: 9),
    itemBuilder: (_, index) {
      final item = items[index];
      final date = DateTime.tryParse(item['holiday_date']?.toString() ?? '');
      return Card(
        child: ListTile(
          leading: CircleAvatar(
            child: Text(date == null ? '—' : DateFormat('dd').format(date)),
          ),
          title: Text(item['name']?.toString() ?? ''),
          subtitle: Text(
            date == null ? '' : DateFormat('EEEE, d MMMM yyyy').format(date),
          ),
          trailing: item['optional'] == true
              ? const Chip(label: Text('Optional'))
              : null,
        ),
      );
    },
  );
}

class _LeavePanel extends StatelessWidget {
  const _LeavePanel({
    required this.data,
    required this.onSaved,
    required this.message,
  });
  final Map<String, dynamic> data;
  final Future<void> Function() onSaved;
  final void Function(String, {bool success}) message;
  @override
  Widget build(BuildContext context) {
    final requests = List<dynamic>.from(data['leave_requests'] ?? []);
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        FilledButton.icon(
          onPressed: () => _leaveDialog(context),
          icon: const Icon(LucideIcons.calendarPlus),
          label: const Text('Apply for leave'),
        ),
        const SizedBox(height: 16),
        ...requests.map(
          (item) => Card(
            child: ListTile(
              title: Text(item['leave_type']?['name']?.toString() ?? 'Leave'),
              subtitle: Text(
                '${item['from_date']} – ${item['to_date']} · ${item['total_days']} days',
              ),
              trailing: _Status(item['status']?.toString() ?? 'pending'),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _leaveDialog(BuildContext context) async {
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
      showDragHandle: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => Padding(
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
                const Text(
                  'Apply for leave',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<int>(
                  initialValue: typeId,
                  decoration: const InputDecoration(labelText: 'Leave type'),
                  items: types
                      .map(
                        (item) => DropdownMenuItem<int>(
                          value: item['id'] as int,
                          child: Text(item['name'].toString()),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() => typeId = value),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () async {
                          final value = await showDatePicker(
                            context: context,
                            firstDate: DateTime.now(),
                            lastDate: DateTime.now().add(
                              const Duration(days: 730),
                            ),
                            initialDate: from ?? DateTime.now(),
                          );
                          if (value != null) setState(() => from = value);
                        },
                        child: Text(
                          from == null
                              ? 'From date'
                              : DateFormat('dd MMM yyyy').format(from!),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () async {
                          final value = await showDatePicker(
                            context: context,
                            firstDate: from ?? DateTime.now(),
                            lastDate: DateTime.now().add(
                              const Duration(days: 730),
                            ),
                            initialDate: to ?? from ?? DateTime.now(),
                          );
                          if (value != null) setState(() => to = value);
                        },
                        child: Text(
                          to == null
                              ? 'To date'
                              : DateFormat('dd MMM yyyy').format(to!),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: reason,
                  maxLines: 3,
                  scrollPadding: const EdgeInsets.only(bottom: 120),
                  decoration: const InputDecoration(labelText: 'Reason *'),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picked = await ImagePicker().pickImage(
                      source: ImageSource.gallery,
                      imageQuality: 80,
                    );
                    if (picked != null) setState(() => document = picked);
                  },
                  icon: const Icon(LucideIcons.paperclip),
                  label: Text(
                    document?.name ?? 'Attach document (if required)',
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed:
                      typeId == null ||
                          from == null ||
                          to == null ||
                          reason.text.trim().isEmpty
                      ? null
                      : () => Navigator.pop(context, true),
                  child: const Text('Submit request'),
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
      message('Leave request submitted.', success: true);
      await onSaved();
    } on DioException catch (error) {
      message(
        error.response?.data?['message']?.toString() ??
            'Unable to submit leave.',
      );
    }
  }
}

class _VoucherPanel extends StatelessWidget {
  const _VoucherPanel({
    required this.items,
    required this.onSaved,
    required this.message,
  });
  final List<dynamic> items;
  final Future<void> Function() onSaved;
  final void Function(String, {bool success}) message;
  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(18),
    children: [
      FilledButton.icon(
        onPressed: () => _dialog(context),
        icon: const Icon(LucideIcons.receiptIndianRupee),
        label: const Text('Add expense voucher'),
      ),
      const SizedBox(height: 16),
      ...items.map(
        (item) => Card(
          child: ListTile(
            title: Text('${item['voucher_no']} · ₹${item['amount']}'),
            subtitle: Text('${item['expense_date']} · ${item['category']}'),
            trailing: _Status(item['status']?.toString() ?? 'pending'),
          ),
        ),
      ),
    ],
  );
  Future<void> _dialog(BuildContext context) async {
    final amount = TextEditingController(),
        description = TextEditingController(),
        category = TextEditingController();
    DateTime date = DateTime.now();
    XFile? receipt;
    final submit = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => Padding(
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
                const Text(
                  'Expense voucher',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: category,
                  decoration: const InputDecoration(labelText: 'Category *'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: amount,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Amount *',
                    prefixText: '₹ ',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: description,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Description *'),
                ),
                const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: () async {
                    final value = await showDatePicker(
                      context: context,
                      firstDate: DateTime.now().subtract(
                        const Duration(days: 365),
                      ),
                      lastDate: DateTime.now(),
                      initialDate: date,
                    );
                    if (value != null) setState(() => date = value);
                  },
                  child: Text(DateFormat('dd MMM yyyy').format(date)),
                ),
                OutlinedButton.icon(
                  onPressed: () async {
                    final value = await ImagePicker().pickImage(
                      source: ImageSource.camera,
                      imageQuality: 80,
                    );
                    if (value != null) setState(() => receipt = value);
                  },
                  icon: const Icon(LucideIcons.camera),
                  label: Text(receipt?.name ?? 'Photograph receipt *'),
                ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: receipt == null
                      ? null
                      : () => Navigator.pop(context, true),
                  child: const Text('Submit voucher'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (submit != true || receipt == null || !context.mounted) return;
    try {
      await context.read<ApiClient>().dio.post(
        'expense-vouchers',
        data: FormData.fromMap({
          'expense_date': DateFormat('yyyy-MM-dd').format(date),
          'category': category.text,
          'amount': amount.text,
          'description': description.text,
          'receipt': await MultipartFile.fromFile(
            receipt!.path,
            filename: receipt!.name,
          ),
        }),
      );
      message('Expense voucher submitted.', success: true);
      await onSaved();
    } on DioException catch (error) {
      message(
        error.response?.data?['message']?.toString() ??
            'Unable to submit voucher.',
      );
    }
  }
}

class _Payslips extends StatelessWidget {
  const _Payslips({required this.items});
  final List<dynamic> items;
  @override
  Widget build(BuildContext context) => ListView.separated(
    padding: const EdgeInsets.all(18),
    itemCount: items.length,
    separatorBuilder: (_, _) => const SizedBox(height: 10),
    itemBuilder: (_, i) {
      final item = items[i];
      return Card(
        child: ListTile(
          leading: const CircleAvatar(
            backgroundColor: Color(0xFFDCFCE7),
            child: Icon(LucideIcons.walletCards, color: AppColors.success),
          ),
          title: Text(
            item['payroll_run']?['payroll_month']?.toString() ?? 'Payslip',
          ),
          subtitle: Text(
            'Gross ₹${item['gross_amount']} · Deductions ₹${item['deduction_amount']}',
          ),
          trailing: Text(
            '₹${item['net_amount']}',
            style: const TextStyle(
              fontWeight: FontWeight.w900,
              color: AppColors.success,
            ),
          ),
        ),
      );
    },
  );
}

class _Info extends StatelessWidget {
  const _Info({required this.label, required this.value});
  final String label;
  final dynamic value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Expanded(
          child: Text(label, style: const TextStyle(color: AppColors.muted)),
        ),
        Expanded(
          child: Text(
            value?.toString() ?? '—',
            textAlign: TextAlign.end,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _Status extends StatelessWidget {
  const _Status(this.value);
  final String value;
  @override
  Widget build(BuildContext context) {
    final color = value == 'approved' || value == 'paid'
        ? AppColors.success
        : value == 'rejected'
        ? AppColors.danger
        : AppColors.warning;
    return Text(
      value.replaceAll('_', ' ').toUpperCase(),
      style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 11),
    );
  }
}
