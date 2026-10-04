import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'service_job_detail_screen.dart';

class EstimateScreen extends StatefulWidget {
  const EstimateScreen({
    super.key,
    required this.serviceJobId,
    required this.services,
  });

  final int serviceJobId;
  final List<dynamic> services;

  @override
  State<EstimateScreen> createState() => _EstimateScreenState();
}

class _EstimateScreenState extends State<EstimateScreen> {
  final selected = <int, double>{};
  final notes = TextEditingController();
  final discount = TextEditingController(text: '0');
  bool submitting = false;
  String? error;

  @override
  void dispose() {
    notes.dispose();
    discount.dispose();
    super.dispose();
  }

  double number(dynamic value) => double.tryParse(value?.toString() ?? '') ?? 0;

  double get subtotal => widget.services.fold(0, (total, raw) {
    final service = Map<String, dynamic>.from(raw);
    final quantity = selected[service['id']] ?? 0;
    return total + (number(service['standard_price']) * quantity);
  });

  double get tax => widget.services.fold(0, (total, raw) {
    final service = Map<String, dynamic>.from(raw);
    final quantity = selected[service['id']] ?? 0;
    return total +
        number(service['standard_price']) *
            quantity *
            number(service['tax_percent']) /
            100;
  });

  double get discountAmount => number(discount.text);

  Future<void> submit() async {
    if (selected.isEmpty) {
      setState(() => error = 'Select at least one service.');
      return;
    }
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await context.read<ApiClient>().dio.post(
        'service-jobs/${widget.serviceJobId}/estimates',
        data: {
          'discount_amount': discountAmount,
          'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
          'items': widget.services
              .map((value) => Map<String, dynamic>.from(value))
              .where((service) => selected.containsKey(service['id']))
              .map(
                (service) => {
                  'service_catalog_item_id': service['id'],
                  'type': 'service',
                  'quantity': selected[service['id']],
                  'unit': 'service',
                },
              )
              .toList(),
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
    final format = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    final finalAmount = (subtotal + tax - discountAmount).clamp(
      0,
      double.infinity,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Create estimate')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 124),
        children: [
          const Text(
            'Select work required',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          const Text(
            'Approved master prices and taxes are applied automatically.',
            style: TextStyle(color: AppColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 14),
          ...widget.services.map((raw) {
            final service = Map<String, dynamic>.from(raw);
            final id = service['id'] as int;
            final checked = selected.containsKey(id);
            final quantity = selected[id] ?? 1;
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(8, 8, 12, 8),
                  child: Column(
                    children: [
                      CheckboxListTile(
                        value: checked,
                        onChanged: submitting
                            ? null
                            : (value) => setState(() {
                                if (value == true) {
                                  selected[id] = 1;
                                } else {
                                  selected.remove(id);
                                }
                              }),
                        controlAffinity: ListTileControlAffinity.leading,
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          service['name']?.toString() ?? 'Service',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          '${service['category'] ?? 'Service'}  •  ${format.format(number(service['standard_price']))} + ${number(service['tax_percent']).toStringAsFixed(0)}% tax',
                        ),
                      ),
                      if (checked)
                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            const Text(
                              'Quantity',
                              style: TextStyle(color: AppColors.muted),
                            ),
                            const SizedBox(width: 12),
                            IconButton.filledTonal(
                              onPressed: quantity <= 1
                                  ? null
                                  : () => setState(
                                      () => selected[id] = quantity - 1,
                                    ),
                              icon: const Icon(LucideIcons.minus, size: 17),
                            ),
                            SizedBox(
                              width: 38,
                              child: Text(
                                quantity.toStringAsFixed(0),
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            IconButton.filledTonal(
                              onPressed: () =>
                                  setState(() => selected[id] = quantity + 1),
                              icon: const Icon(LucideIcons.plus, size: 17),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
              ),
            );
          }),
          const SizedBox(height: 8),
          TextField(
            controller: discount,
            enabled: !submitting,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              labelText: 'Discount amount (optional)',
              prefixText: '₹ ',
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: notes,
            enabled: !submitting,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Estimate notes (optional)',
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            Text(error!, style: const TextStyle(color: AppColors.danger)),
          ],
        ],
      ),
      bottomSheet: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 14),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'ESTIMATED TOTAL',
                      style: TextStyle(
                        color: AppColors.muted,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      format.format(finalAmount),
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              FilledButton.icon(
                onPressed: submitting ? null : submit,
                style: FilledButton.styleFrom(minimumSize: const Size(165, 52)),
                icon: submitting
                    ? const SizedBox.square(
                        dimension: 17,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(LucideIcons.send, size: 18),
                label: Text(submitting ? 'Sending…' : 'Send for approval'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
