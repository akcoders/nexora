import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'service_job_detail_screen.dart';

class InspectionScreen extends StatefulWidget {
  const InspectionScreen({
    super.key,
    required this.serviceJobId,
    required this.phase,
    required this.job,
    required this.conditions,
  });

  final int serviceJobId;
  final String phase;
  final Map<String, dynamic> job;
  final List<dynamic> conditions;

  @override
  State<InspectionScreen> createState() => _InspectionScreenState();
}

class _InspectionScreenState extends State<InspectionScreen> {
  final picker = ImagePicker();
  Map<String, dynamic>? inspection;
  List<dynamic> conditions = [];
  bool loading = true;
  bool submitting = false;
  int? savingItemId;
  final pendingItemIds = <int>{};
  String? error;

  Dio get api => context.read<ApiClient>().dio;

  @override
  void initState() {
    super.initState();
    conditions = widget.conditions;
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await api.get('service-jobs/${widget.serviceJobId}');
      final data = Map<String, dynamic>.from(response.data['job']);
      final inspections = List<dynamic>.from(data['inspections'] ?? []);
      final found = inspections.cast<Map>().where(
        (item) => item['phase'] == widget.phase,
      );
      inspection = found.isEmpty
          ? null
          : Map<String, dynamic>.from(found.first);
      final masters = Map<String, dynamic>.from(response.data['masters'] ?? {});
      conditions = List<dynamic>.from(masters['conditions'] ?? conditions);
    } on DioException catch (exception) {
      error = apiErrorMessage(exception);
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> saveItem(
    Map<String, dynamic> item,
    Map<String, dynamic> condition, {
    String? remark,
  }) async {
    final id = item['id'] as int;
    final items = List<dynamic>.from(inspection?['items'] ?? []);
    final index = items.indexWhere((value) => value['id'] == id);
    if (index >= 0) {
      items[index] = {
        ...Map<String, dynamic>.from(items[index]),
        'inspection_condition_id': condition['id'],
        'condition': condition,
        'remark': remark ?? item['remark'],
        'completed_at': DateTime.now().toIso8601String(),
      };
      inspection?['items'] = items;
    }
    setState(() {
      savingItemId = id;
      pendingItemIds.add(id);
      error = null;
    });
    try {
      final response = await api.put(
        'service-jobs/${widget.serviceJobId}/inspection-items/$id',
        data: {
          'inspection_condition_id': condition['id'],
          'remark': remark ?? item['remark'],
        },
      );
      final saved = Map<String, dynamic>.from(response.data['item']);
      final currentItems = List<dynamic>.from(inspection?['items'] ?? []);
      final currentIndex = currentItems.indexWhere(
        (value) => value['id'] == id,
      );
      if (currentIndex >= 0) {
        currentItems[currentIndex] = {
          ...Map<String, dynamic>.from(currentItems[currentIndex]),
          ...saved,
        };
      }
      inspection?['items'] = currentItems;
      pendingItemIds.remove(id);
    } on DioException catch (exception) {
      error =
          '${apiErrorMessage(exception)} This item is not synced; tap its condition to retry.';
    }
    if (mounted) setState(() => savingItemId = null);
  }

  Future<void> addRemark(Map<String, dynamic> item) async {
    final selected = item['condition'];
    if (selected == null) {
      setState(() => error = 'Select a condition before adding a remark.');
      return;
    }
    final controller = TextEditingController(text: item['remark']?.toString());
    final remark = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Inspection remark'),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLines: 4,
          decoration: const InputDecoration(
            hintText: 'Add observation or action required',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (remark != null && mounted) {
      await saveItem(item, Map<String, dynamic>.from(selected), remark: remark);
    }
  }

  Future<void> capturePhoto({Map<String, dynamic>? item}) async {
    final photo = await picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 78,
      maxWidth: 1800,
    );
    if (photo == null || !mounted) return;
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      await api.post(
        'service-jobs/${widget.serviceJobId}/photos',
        data: FormData.fromMap({
          'photo': await MultipartFile.fromFile(
            photo.path,
            filename: photo.name,
          ),
          'category': widget.phase == 'pre' ? 'before' : 'after',
          'service_inspection_id': inspection?['id'],
          if (item != null) 'service_inspection_item_id': item['id'],
        }),
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Photo added.'),
            backgroundColor: AppColors.success,
          ),
        );
      }
      await load();
    } on DioException catch (exception) {
      if (mounted) setState(() => error = apiErrorMessage(exception));
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  Future<void> complete() async {
    setState(() {
      submitting = true;
      error = null;
    });
    try {
      final path = widget.phase == 'pre'
          ? 'inspection/complete'
          : 'post-inspection/complete';
      await api.post('service-jobs/${widget.serviceJobId}/$path');
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
    final title = widget.phase == 'pre'
        ? 'Pre-service inspection'
        : 'Post-service checks';
    if (loading) {
      return Scaffold(
        appBar: AppBar(title: Text(title)),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    final items = List<dynamic>.from(inspection?['items'] ?? []);
    final completed = items
        .where((item) => item['completed_at'] != null)
        .length;

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: inspection == null
          ? Center(child: Text(error ?? 'Checklist is not available.'))
          : ListView(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 110),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                '$completed of ${items.length} completed',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            Text(
                              items.isEmpty
                                  ? '0%'
                                  : '${(completed / items.length * 100).round()}%',
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        LinearProgressIndicator(
                          value: items.isEmpty ? 0 : completed / items.length,
                          minHeight: 8,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        const SizedBox(height: 14),
                        OutlinedButton.icon(
                          onPressed: submitting ? null : () => capturePhoto(),
                          icon: const Icon(LucideIcons.camera),
                          label: Text(
                            widget.phase == 'pre'
                                ? 'Add before photo'
                                : 'Add after photo',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (error != null) ...[
                  const SizedBox(height: 12),
                  _ErrorCard(message: error!),
                ],
                const SizedBox(height: 14),
                ...items.map((raw) {
                  final item = Map<String, dynamic>.from(raw);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _InspectionItemCard(
                      item: item,
                      conditions: conditions,
                      saving: savingItemId == item['id'],
                      pending: pendingItemIds.contains(item['id']),
                      onCondition: (condition) => saveItem(item, condition),
                      onRemark: () => addRemark(item),
                      onPhoto: () => capturePhoto(item: item),
                    ),
                  );
                }),
              ],
            ),
      bottomSheet: inspection == null
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
                  onPressed:
                      submitting ||
                          pendingItemIds.isNotEmpty ||
                          completed != items.length
                      ? null
                      : complete,
                  icon: submitting
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(LucideIcons.clipboardCheck),
                  label: Text(
                    completed == items.length
                        ? 'Complete checklist'
                        : 'Complete all required checks',
                  ),
                ),
              ),
            ),
    );
  }
}

class _InspectionItemCard extends StatelessWidget {
  const _InspectionItemCard({
    required this.item,
    required this.conditions,
    required this.saving,
    required this.pending,
    required this.onCondition,
    required this.onRemark,
    required this.onPhoto,
  });

  final Map<String, dynamic> item;
  final List<dynamic> conditions;
  final bool saving;
  final bool pending;
  final ValueChanged<Map<String, dynamic>> onCondition;
  final VoidCallback onRemark;
  final VoidCallback onPhoto;

  @override
  Widget build(BuildContext context) {
    final definition = item['checklist_item'] is Map
        ? Map<String, dynamic>.from(item['checklist_item'])
        : <String, dynamic>{};
    final allowed = List<dynamic>.from(definition['allowed_conditions'] ?? []);
    final visibleConditions = conditions
        .map((value) => Map<String, dynamic>.from(value))
        .where(
          (condition) => allowed.isEmpty || allowed.contains(condition['code']),
        )
        .toList();
    final selectedId = item['inspection_condition_id'];
    final photoRequired = definition['photo_required'] == true;
    final remarkAllowed = definition['remark_allowed'] != false;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(17),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    item['label']?.toString() ?? 'Inspection item',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                if (definition['required'] == true)
                  const Text(
                    'REQUIRED',
                    style: TextStyle(
                      color: AppColors.danger,
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                if (pending)
                  const Padding(
                    padding: EdgeInsets.only(left: 7),
                    child: Text(
                      'NOT SYNCED',
                      style: TextStyle(
                        color: AppColors.warning,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: visibleConditions.map((condition) {
                final selected = selectedId == condition['id'];
                final color = _conditionColor(condition['color']?.toString());
                return ChoiceChip(
                  selected: selected,
                  onSelected: saving ? null : (_) => onCondition(condition),
                  label: Text(condition['name']?.toString() ?? ''),
                  selectedColor: color.withValues(alpha: .16),
                  side: BorderSide(
                    color: selected ? color : const Color(0xFFE2E8F0),
                  ),
                  labelStyle: TextStyle(
                    color: selected ? color : AppColors.ink,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                  ),
                );
              }).toList(),
            ),
            if (saving) ...[
              const SizedBox(height: 10),
              const LinearProgressIndicator(),
            ],
            if ((item['remark']?.toString().isNotEmpty ?? false)) ...[
              const SizedBox(height: 10),
              Text(
                item['remark'].toString(),
                style: const TextStyle(color: AppColors.muted, fontSize: 12),
              ),
            ],
            if (remarkAllowed || photoRequired) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: [
                  if (remarkAllowed)
                    TextButton.icon(
                      onPressed: saving ? null : onRemark,
                      icon: const Icon(LucideIcons.messageSquareText, size: 16),
                      label: Text(
                        item['remark'] == null ? 'Remark' : 'Edit remark',
                      ),
                    ),
                  if (photoRequired)
                    TextButton.icon(
                      onPressed: saving ? null : onPhoto,
                      icon: const Icon(LucideIcons.camera, size: 16),
                      label: const Text('Required photo'),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFEF2F2),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(message, style: const TextStyle(color: AppColors.danger)),
  );
}

Color _conditionColor(String? color) => switch (color) {
  'success' => AppColors.success,
  'warning' => AppColors.warning,
  'danger' => AppColors.danger,
  _ => AppColors.muted,
};
