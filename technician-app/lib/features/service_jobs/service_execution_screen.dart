import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'inspection_screen.dart';
import 'service_job_detail_screen.dart';
import 'signature_capture.dart';

class ServiceExecutionScreen extends StatefulWidget {
  const ServiceExecutionScreen({
    super.key,
    required this.serviceJobId,
    required this.job,
    required this.masters,
  });

  final int serviceJobId;
  final Map<String, dynamic> job;
  final Map<String, dynamic> masters;

  @override
  State<ServiceExecutionScreen> createState() => _ServiceExecutionScreenState();
}

class _ServiceExecutionScreenState extends State<ServiceExecutionScreen> {
  final picker = ImagePicker();
  late Map<String, dynamic> job = Map<String, dynamic>.from(widget.job);
  late Map<String, dynamic> masters = Map<String, dynamic>.from(widget.masters);
  bool loading = false;
  bool submitting = false;
  String? error;

  Dio get api => context.read<ApiClient>().dio;

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await api.get('service-jobs/${widget.serviceJobId}');
      job = Map<String, dynamic>.from(response.data['job']);
      masters = Map<String, dynamic>.from(response.data['masters'] ?? {});
    } on DioException catch (exception) {
      error = apiErrorMessage(exception);
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> addService() async {
    final services = List<dynamic>.from(masters['services'] ?? []);
    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * .68,
          child: Column(
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(18, 0, 18, 10),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Add work performed',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800),
                  ),
                ),
              ),
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(10, 0, 10, 18),
                  itemCount: services.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final service = Map<String, dynamic>.from(services[index]);
                    return ListTile(
                      leading: const CircleAvatar(
                        child: Icon(LucideIcons.wrench, size: 18),
                      ),
                      title: Text(service['name']?.toString() ?? 'Service'),
                      subtitle: Text(service['category']?.toString() ?? ''),
                      trailing: Text(
                        '₹${service['standard_price']}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      onTap: () => Navigator.pop(context, service),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (selected == null || !mounted) return;
    await runAction(() async {
      await api.post(
        'service-jobs/${widget.serviceJobId}/services',
        data: {'service_catalog_item_id': selected['id'], 'quantity': 1},
      );
    }, 'Work performed added.');
  }

  Future<void> addMaterial() async {
    final name = TextEditingController();
    final quantity = TextEditingController(text: '1');
    final rate = TextEditingController(text: '0');
    final remark = TextEditingController();
    final units = List<dynamic>.from(masters['units'] ?? []);
    final products = List<dynamic>.from(masters['products'] ?? []);
    String unit = units.isEmpty ? 'piece' : units.first['code'].toString();
    int? selectedProductId;
    final data = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Add material used'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int?>(
                  initialValue: selectedProductId,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Inventory product',
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Other / non-inventory material'),
                    ),
                    ...products.map(
                      (raw) => DropdownMenuItem<int?>(
                        value: (raw['id'] as num).toInt(),
                        child: Text(
                          [
                            raw['name'],
                            raw['brand'],
                            raw['model'],
                          ].where((value) => value != null).join(' · '),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                  ],
                  onChanged: (value) => setDialogState(() {
                    selectedProductId = value;
                    if (value != null) name.clear();
                  }),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: name,
                  enabled: selectedProductId == null,
                  textCapitalization: TextCapitalization.words,
                  decoration: InputDecoration(
                    labelText: selectedProductId == null
                        ? 'Other material name'
                        : 'Product selected above',
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: quantity,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Quantity',
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: unit,
                        decoration: const InputDecoration(labelText: 'Unit'),
                        items: units
                            .map(
                              (raw) => DropdownMenuItem<String>(
                                value: raw['code']?.toString(),
                                child: Text(raw['label']?.toString() ?? ''),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            setDialogState(() => unit = value ?? unit),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: rate,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Unit rate',
                    prefixText: '₹ ',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: remark,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Remark (optional)',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () {
                if (selectedProductId == null && name.text.trim().isEmpty) {
                  return;
                }
                Navigator.pop(context, {
                  'product_id': selectedProductId,
                  'name': selectedProductId == null ? name.text.trim() : null,
                  'quantity': double.tryParse(quantity.text) ?? 0,
                  'unit': unit,
                  'unit_rate': double.tryParse(rate.text) ?? 0,
                  'remark': remark.text.trim().isEmpty
                      ? null
                      : remark.text.trim(),
                });
              },
              child: const Text('Add'),
            ),
          ],
        ),
      ),
    );
    name.dispose();
    quantity.dispose();
    rate.dispose();
    remark.dispose();
    if (data == null || !mounted) return;
    await runAction(
      () =>
          api.post('service-jobs/${widget.serviceJobId}/materials', data: data),
      'Material added.',
    );
  }

  Future<void> capturePhoto(String category) async {
    final photo = await picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 78,
      maxWidth: 1800,
    );
    if (photo == null || !mounted) return;
    await runAction(
      () async => api.post(
        'service-jobs/${widget.serviceJobId}/photos',
        data: FormData.fromMap({
          'photo': await MultipartFile.fromFile(
            photo.path,
            filename: photo.name,
          ),
          'category': category,
        }),
      ),
      '${category[0].toUpperCase()}${category.substring(1)} photo added.',
    );
  }

  Future<void> openPostInspection() async {
    final inspections = List<dynamic>.from(job['inspections'] ?? []);
    var post = inspections.cast<Map>().where(
      (value) => value['phase'] == 'post',
    );
    if (post.isEmpty) {
      await runAction(
        () => api.post('service-jobs/${widget.serviceJobId}/post-inspection'),
        'Post-service checklist started.',
      );
      post = List<dynamic>.from(
        job['inspections'] ?? [],
      ).cast<Map>().where((value) => value['phase'] == 'post');
    }
    if (!mounted) return;
    await Navigator.push<void>(
      context,
      MaterialPageRoute(
        builder: (_) => InspectionScreen(
          serviceJobId: widget.serviceJobId,
          phase: 'post',
          job: job,
          conditions: List<dynamic>.from(masters['conditions'] ?? []),
        ),
      ),
    );
    await load();
  }

  Future<void> captureCompletionSignature() async {
    final name = TextEditingController();
    final signer = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Customer name'),
        content: TextField(
          controller: name,
          autofocus: true,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(labelText: 'Signer name'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, name.text.trim()),
            child: const Text('Continue'),
          ),
        ],
      ),
    );
    name.dispose();
    if (signer == null || signer.isEmpty || !mounted) return;
    final Uint8List? signature = await showSignatureCapture(
      context,
      title: 'Service completion signature',
    );
    if (signature == null || !mounted) return;
    await runAction(
      () => api.post(
        'service-jobs/${widget.serviceJobId}/signatures',
        data: FormData.fromMap({
          'type': 'completion',
          'signer_name': signer,
          'signature': MultipartFile.fromBytes(
            signature,
            filename: 'completion-signature.png',
          ),
        }),
      ),
      'Customer signature saved.',
    );
  }

  Future<void> completeWork() async {
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await api.post('service-jobs/${widget.serviceJobId}/service/complete');
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

  Future<void> runAction(
    Future<dynamic> Function() action,
    String success,
  ) async {
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await action();
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(success), backgroundColor: AppColors.success),
        );
      }
    } on DioException catch (exception) {
      if (mounted) setState(() => error = apiErrorMessage(exception));
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final services = List<dynamic>.from(job['performed_services'] ?? []);
    final materials = List<dynamic>.from(job['materials'] ?? []);
    final photos = List<dynamic>.from(job['photos'] ?? []);
    final signatures = List<dynamic>.from(job['signatures'] ?? []);
    final inspections = List<dynamic>.from(job['inspections'] ?? []);
    final post = inspections.cast<Map>().where(
      (value) => value['phase'] == 'post',
    );
    final postDone = post.isNotEmpty && post.first['status'] == 'completed';
    final afterPhoto = photos.any((value) => value['category'] == 'after');
    final signed = signatures.any((value) => value['type'] == 'completion');
    final ready = services.isNotEmpty && postDone && afterPhoto && signed;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Perform service'),
        actions: [
          IconButton(
            onPressed: submitting ? null : load,
            icon: const Icon(LucideIcons.refreshCw),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 8, 18, 120),
          children: [
            if (loading) const LinearProgressIndicator(),
            if (error != null) ...[
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
              const SizedBox(height: 12),
            ],
            _WorkSection(
              title: 'Work performed',
              icon: LucideIcons.wrench,
              complete: services.isNotEmpty,
              actionLabel: 'Add service',
              onAction: submitting ? null : addService,
              children: services
                  .map(
                    (value) => _LineItem(
                      title: value['name']?.toString() ?? 'Service',
                      subtitle: 'Qty ${value['quantity']}',
                      amount: value['line_total'],
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 12),
            _WorkSection(
              title: 'Materials used',
              icon: LucideIcons.packagePlus,
              complete: true,
              actionLabel: 'Add material',
              onAction: submitting ? null : addMaterial,
              emptyText: 'No material used',
              children: materials
                  .map(
                    (value) => _LineItem(
                      title: value['name']?.toString() ?? 'Material',
                      subtitle: '${value['quantity']} ${value['unit']}',
                      amount: value['line_total'],
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 12),
            _StepCard(
              icon: LucideIcons.camera,
              title: 'Service photos',
              subtitle:
                  '${photos.where((value) => value['category'] == 'during').length} during · ${photos.where((value) => value['category'] == 'after').length} after',
              complete: afterPhoto,
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: submitting ? null : () => capturePhoto('during'),
                    icon: const Icon(LucideIcons.camera, size: 17),
                    label: const Text('During'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: submitting ? null : () => capturePhoto('after'),
                    icon: const Icon(LucideIcons.camera, size: 17),
                    label: const Text('After'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _ActionCard(
              icon: LucideIcons.clipboardCheck,
              title: 'Post-service checklist',
              subtitle: postDone
                  ? 'All quality and safety checks completed'
                  : 'Verify cooling, airflow, leakage and housekeeping',
              complete: postDone,
              button: postDone ? 'Review checks' : 'Start checks',
              onPressed: submitting ? null : openPostInspection,
            ),
            const SizedBox(height: 12),
            _ActionCard(
              icon: LucideIcons.signature,
              title: 'Customer completion signature',
              subtitle: signed
                  ? 'Customer sign-off captured'
                  : 'Required before completing service work',
              complete: signed,
              button: signed ? 'Capture again' : 'Capture signature',
              onPressed: submitting ? null : captureCompletionSignature,
            ),
          ],
        ),
      ),
      bottomSheet: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
          ),
          child: FilledButton.icon(
            onPressed: submitting || !ready ? null : completeWork,
            icon: submitting
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(LucideIcons.badgeCheck),
            label: Text(
              ready ? 'Complete service work' : 'Finish all required steps',
            ),
          ),
        ),
      ),
    );
  }
}

class _WorkSection extends StatelessWidget {
  const _WorkSection({
    required this.title,
    required this.icon,
    required this.complete,
    required this.actionLabel,
    required this.onAction,
    required this.children,
    this.emptyText = 'Nothing added yet',
  });

  final String title;
  final IconData icon;
  final bool complete;
  final String actionLabel;
  final VoidCallback? onAction;
  final List<Widget> children;
  final String emptyText;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(17),
      child: Column(
        children: [
          Row(
            children: [
              Icon(icon, size: 20, color: AppColors.primary),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              if (complete)
                const Icon(
                  LucideIcons.circleCheck,
                  color: AppColors.success,
                  size: 19,
                ),
            ],
          ),
          const SizedBox(height: 12),
          if (children.isEmpty)
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                emptyText,
                style: const TextStyle(color: AppColors.muted, fontSize: 12),
              ),
            )
          else
            ...children,
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: onAction,
              icon: const Icon(LucideIcons.plus, size: 17),
              label: Text(actionLabel),
            ),
          ),
        ],
      ),
    ),
  );
}

class _LineItem extends StatelessWidget {
  const _LineItem({
    required this.title,
    required this.subtitle,
    required this.amount,
  });

  final String title;
  final String subtitle;
  final dynamic amount;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
              Text(
                subtitle,
                style: const TextStyle(color: AppColors.muted, fontSize: 11),
              ),
            ],
          ),
        ),
        Text(
          NumberFormat.currency(
            locale: 'en_IN',
            symbol: '₹',
          ).format(double.tryParse(amount?.toString() ?? '') ?? 0),
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ],
    ),
  );
}

class _StepCard extends StatelessWidget {
  const _StepCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.complete,
    required this.children,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool complete;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(17),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: AppColors.primary, size: 20),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              if (complete)
                const Icon(
                  LucideIcons.circleCheck,
                  color: AppColors.success,
                  size: 19,
                ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            subtitle,
            style: const TextStyle(color: AppColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 12),
          Row(children: children),
        ],
      ),
    ),
  );
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.complete,
    required this.button,
    required this.onPressed,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool complete;
  final String button;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(17),
      child: Row(
        children: [
          Container(
            width: 43,
            height: 43,
            decoration: BoxDecoration(
              color: complete
                  ? const Color(0xFFEAF8EF)
                  : const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              complete ? LucideIcons.circleCheck : icon,
              color: complete ? AppColors.success : AppColors.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: const TextStyle(color: AppColors.muted, fontSize: 11),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: onPressed,
                  style: TextButton.styleFrom(padding: EdgeInsets.zero),
                  child: Text(button),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
