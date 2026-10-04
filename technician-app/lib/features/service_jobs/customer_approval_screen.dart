import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'service_job_detail_screen.dart';
import 'signature_capture.dart';

class CustomerApprovalScreen extends StatefulWidget {
  const CustomerApprovalScreen({
    super.key,
    required this.serviceJobId,
    required this.estimate,
  });

  final int serviceJobId;
  final Map<String, dynamic> estimate;

  @override
  State<CustomerApprovalScreen> createState() => _CustomerApprovalScreenState();
}

class _CustomerApprovalScreenState extends State<CustomerApprovalScreen> {
  final customerName = TextEditingController();
  Uint8List? signature;
  bool customerConfirmed = false;
  bool submitting = false;
  String? error;

  @override
  void dispose() {
    customerName.dispose();
    super.dispose();
  }

  Future<void> approve() async {
    if (!customerConfirmed) {
      setState(() => error = 'Ask the customer to review the estimate first.');
      return;
    }
    if (customerName.text.trim().isEmpty || signature == null) {
      setState(() => error = 'Customer name and signature are required.');
      return;
    }
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await context.read<ApiClient>().dio.post(
        'service-jobs/${widget.serviceJobId}/estimates/${widget.estimate['id']}/decision',
        data: FormData.fromMap({
          'decision': 'accept',
          'customer_name': customerName.text.trim(),
          'signature': MultipartFile.fromBytes(
            signature!,
            filename: 'estimate-approval.png',
          ),
        }),
      );
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (exception) {
      if (mounted) {
        setState(() {
          error = apiErrorMessage(exception);
          submitting = false;
        });
      }
    }
  }

  Future<void> otherDecision(String decision) async {
    final controller = TextEditingController();
    final remark = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          decision == 'request_change'
              ? 'Request estimate changes'
              : 'Decline estimate',
        ),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLines: 4,
          decoration: const InputDecoration(
            hintText: 'Enter the customer’s reason',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (remark == null || remark.isEmpty || !mounted) return;
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await context.read<ApiClient>().dio.post(
        'service-jobs/${widget.serviceJobId}/estimates/${widget.estimate['id']}/decision',
        data: {'decision': decision, 'remark': remark},
      );
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (exception) {
      if (mounted) {
        setState(() {
          error = apiErrorMessage(exception);
          submitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = List<dynamic>.from(widget.estimate['items'] ?? []);
    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');

    return Scaffold(
      appBar: AppBar(title: const Text('Customer approval')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 30),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Service estimate',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      Text(
                        'Version ${widget.estimate['version'] ?? 1}',
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  ...items.map((raw) {
                    final item = Map<String, dynamic>.from(raw);
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              '${item['description']} × ${item['quantity']}',
                            ),
                          ),
                          Text(
                            money.format(
                              double.tryParse(
                                    item['line_total']?.toString() ?? '',
                                  ) ??
                                  0,
                            ),
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ],
                      ),
                    );
                  }),
                  const Divider(height: 28),
                  _AmountRow(
                    label: 'Subtotal',
                    value: money.format(_number(widget.estimate['subtotal'])),
                  ),
                  _AmountRow(
                    label: 'Tax',
                    value: money.format(_number(widget.estimate['tax_amount'])),
                  ),
                  if (_number(widget.estimate['discount_amount']) > 0)
                    _AmountRow(
                      label: 'Discount',
                      value:
                          '-${money.format(_number(widget.estimate['discount_amount']))}',
                    ),
                  const Divider(height: 24),
                  _AmountRow(
                    label: 'Total',
                    value: money.format(
                      _number(widget.estimate['final_amount']),
                    ),
                    total: true,
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
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  CheckboxListTile(
                    value: customerConfirmed,
                    onChanged: submitting
                        ? null
                        : (value) => setState(
                            () => customerConfirmed = value ?? false,
                          ),
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    title: const Text(
                      'Customer reviewed the services, price and tax',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: customerName,
                    enabled: !submitting,
                    textCapitalization: TextCapitalization.words,
                    decoration: const InputDecoration(
                      labelText: 'Customer / approver name',
                      prefixIcon: Icon(LucideIcons.userRound),
                    ),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: submitting
                        ? null
                        : () async {
                            final result = await showSignatureCapture(
                              context,
                              title: 'Estimate approval signature',
                            );
                            if (result != null) {
                              setState(() => signature = result);
                            }
                          },
                    icon: Icon(
                      signature == null
                          ? LucideIcons.penLine
                          : LucideIcons.circleCheck,
                      color: signature == null ? null : AppColors.success,
                    ),
                    label: Text(
                      signature == null
                          ? 'Capture customer signature'
                          : 'Signature captured · Tap to redo',
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      error!,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ],
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: submitting ? null : approve,
                    icon: submitting
                        ? const SizedBox.square(
                            dimension: 17,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(LucideIcons.badgeCheck),
                    label: Text(
                      submitting ? 'Submitting…' : 'Accept and approve',
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: submitting
                              ? null
                              : () => otherDecision('request_change'),
                          child: const Text('Request changes'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: TextButton(
                          onPressed: submitting
                              ? null
                              : () => otherDecision('decline'),
                          child: const Text(
                            'Decline',
                            style: TextStyle(color: AppColors.danger),
                          ),
                        ),
                      ),
                    ],
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

double _number(dynamic value) => double.tryParse(value?.toString() ?? '') ?? 0;

class _AmountRow extends StatelessWidget {
  const _AmountRow({
    required this.label,
    required this.value,
    this.total = false,
  });

  final String label;
  final String value;
  final bool total;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: total ? AppColors.ink : AppColors.muted,
              fontWeight: total ? FontWeight.w800 : FontWeight.w500,
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: total ? 20 : 14,
            fontWeight: total ? FontWeight.w900 : FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}
