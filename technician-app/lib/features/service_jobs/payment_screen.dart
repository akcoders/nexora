import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'service_job_detail_screen.dart';

class PaymentScreen extends StatefulWidget {
  const PaymentScreen({
    super.key,
    required this.serviceJobId,
    required this.job,
    required this.paymentMethods,
  });

  final int serviceJobId;
  final Map<String, dynamic> job;
  final List<dynamic> paymentMethods;

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  late final TextEditingController amount = TextEditingController(
    text: pending.toStringAsFixed(2),
  );
  final reference = TextEditingController();
  final notes = TextEditingController();
  String? method;
  bool submitting = false;
  String? error;

  double value(dynamic raw) => double.tryParse(raw?.toString() ?? '') ?? 0;
  double get total => value(widget.job['final_amount']);
  double get paid => value(widget.job['amount_paid']);
  double get pending => value(widget.job['amount_pending']);

  @override
  void dispose() {
    amount.dispose();
    reference.dispose();
    notes.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    final paymentAmount = value(amount.text);
    if (method == null) {
      setState(() => error = 'Select a payment method.');
      return;
    }
    if (method != 'credit' && paymentAmount <= 0) {
      setState(() => error = 'Enter the amount received.');
      return;
    }
    if (['upi', 'card', 'online'].contains(method) &&
        reference.text.trim().isEmpty) {
      setState(() => error = 'Enter the transaction reference.');
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(LucideIcons.indianRupee, color: AppColors.success),
        title: const Text('Confirm payment entry'),
        content: Text(
          method == 'credit'
              ? 'Mark this amount as credit / pay later?'
              : 'Record ${NumberFormat.currency(locale: 'en_IN', symbol: '₹').format(paymentAmount)} as received?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await context.read<ApiClient>().dio.post(
        'service-jobs/${widget.serviceJobId}/payments',
        data: {
          'amount': method == 'credit' ? 0 : paymentAmount,
          'method': method,
          'transaction_reference': reference.text.trim().isEmpty
              ? null
              : reference.text.trim(),
          'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
        },
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
    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');

    return Scaffold(
      appBar: AppBar(title: const Text('Payment')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 30),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF1E3A8A), AppColors.primary],
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'AMOUNT DUE',
                  style: TextStyle(
                    color: Colors.white70,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  money.format(pending),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 31,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Service total ${money.format(total)}  •  Paid ${money.format(paid)}',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          const Text(
            'Payment method',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: widget.paymentMethods.map((raw) {
              final option = Map<String, dynamic>.from(raw);
              final code = option['code']?.toString() ?? '';
              return ChoiceChip(
                selected: method == code,
                onSelected: submitting
                    ? null
                    : (_) => setState(() {
                        method = code;
                        if (code == 'credit') {
                          amount.text = '0.00';
                        } else if (value(amount.text) <= 0) {
                          amount.text = pending.toStringAsFixed(2);
                        }
                      }),
                label: Text(option['label']?.toString() ?? code),
              );
            }).toList(),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: amount,
            enabled: !submitting && method != 'credit',
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: method == 'credit'
                  ? 'Credit amount'
                  : 'Amount received',
              prefixText: '₹ ',
            ),
          ),
          if (['upi', 'card', 'online'].contains(method)) ...[
            const SizedBox(height: 12),
            TextField(
              controller: reference,
              enabled: !submitting,
              decoration: const InputDecoration(
                labelText: 'Transaction reference',
                prefixIcon: Icon(LucideIcons.receiptText),
              ),
            ),
          ],
          const SizedBox(height: 12),
          TextField(
            controller: notes,
            enabled: !submitting,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Notes (optional)'),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            Text(error!, style: const TextStyle(color: AppColors.danger)),
          ],
          const SizedBox(height: 18),
          FilledButton.icon(
            onPressed: submitting ? null : submit,
            icon: submitting
                ? const SizedBox.square(
                    dimension: 17,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(LucideIcons.circleCheck),
            label: Text(
              submitting
                  ? 'Saving…'
                  : method == 'credit'
                  ? 'Mark as pay later'
                  : 'Record payment',
            ),
          ),
        ],
      ),
    );
  }
}
