import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:provider/provider.dart';

import '../../core/i18n/t.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/ui.dart';
import '../service_jobs/signature_capture.dart';

typedef Option = ({String value, String label});

/// Technician visit report: time on site, checks, readings, diagnosis, photos,
/// customer sign-off and the visit result (resolved, pending or observation).
class ComplaintVisitReportScreen extends StatefulWidget {
  const ComplaintVisitReportScreen({
    super.key,
    required this.visit,
    required this.complaint,
    required this.form,
  });

  final Map<String, dynamic> visit;
  final Map<String, dynamic> complaint;
  final Map<String, dynamic> form;

  @override
  State<ComplaintVisitReportScreen> createState() =>
      _ComplaintVisitReportScreenState();
}

class _ComplaintVisitReportScreenState
    extends State<ComplaintVisitReportScreen> {
  static const timeFields = [
    ('accepted_at', 'Accepted'),
    ('en_route_at', 'Left for site'),
    ('arrived_at', 'Reached site'),
    ('started_at', 'Work started'),
    ('ended_at', 'Work finished'),
  ];
  static const textFields = [
    'observation',
    'initial_diagnosis',
    'abnormality_found',
    'fault_code',
    'problem_diagnosis',
    'work_performed',
    'technician_remarks',
    'customer_signed_name',
    'customer_remarks',
  ];

  final times = <String, DateTime?>{};
  final text = <String, TextEditingController>{};
  final readings = <String, TextEditingController>{};
  final checks = <String, String?>{};
  final choices = <String, String?>{};
  final pending = <String, String?>{};
  final pendingText = {
    'next_action': TextEditingController(),
    'remarks': TextEditingController(),
  };
  final observationNextAction = TextEditingController();
  final photos = <XFile>[];
  int? rating;
  String? outcome;
  DateTime? observationFollowUp;
  Uint8List? signature;
  bool saving = false;
  String? error;

  Map<String, dynamic> get visit => widget.visit;
  Map<String, dynamic> get form => widget.form;

  @override
  void initState() {
    super.initState();
    for (final (field, _) in timeFields) {
      times[field] = DateTime.tryParse(
        visit[field]?.toString() ?? '',
      )?.toLocal();
    }
    for (final field in textFields) {
      text[field] = TextEditingController(text: visit[field]?.toString() ?? '');
    }
    final savedChecks = visit['checks'] is Map
        ? Map<String, dynamic>.from(visit['checks'])
        : <String, dynamic>{};
    for (final check in _list('checks')) {
      checks[check['key'].toString()] = savedChecks[check['key']]?.toString();
    }
    final savedReadings = visit['diagnosis'] is Map
        ? Map<String, dynamic>.from(visit['diagnosis'])
        : <String, dynamic>{};
    for (final field in _list('diagnosis_fields')) {
      final key = field['key'].toString();
      final value = savedReadings[key]?.toString();
      if (field['type'] == 'condition' || field['type'] == 'yes_no') {
        choices['diagnosis.$key'] = value;
      } else {
        readings[key] = TextEditingController(text: value ?? '');
      }
    }
    choices['fault_category'] = visit['fault_category']?.toString();
    choices['root_cause'] = visit['root_cause']?.toString();
    choices['final_result'] = visit['final_result']?.toString();
    rating = (visit['customer_rating'] as num?)?.toInt();
  }

  @override
  void dispose() {
    for (final controller in [
      ...text.values,
      ...readings.values,
      ...pendingText.values,
      observationNextAction,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  List<Map<String, dynamic>> _list(String key) {
    final raw = form[key];
    if (raw is List) {
      return raw
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
    }
    return const [];
  }

  /// Option lists arrive as [{value, label}]; a plain {value: label} map is accepted too.
  List<Option> options(String key) {
    final raw = form[key];
    if (raw is Map) {
      return raw.entries
          .map(
            (entry) =>
                (value: entry.key.toString(), label: entry.value.toString()),
          )
          .toList();
    }
    return _list(key)
        .map(
          (item) => (
            value: (item['value'] ?? item['code'] ?? item['label']).toString(),
            label: (item['label'] ?? item['value']).toString(),
          ),
        )
        .toList();
  }

  List<Map<String, dynamic>> get pendingReasons {
    final tree = form['pending_reasons'];
    final group = pending['pending_group'];
    if (tree is! Map || group == null || tree[group] is! List) return const [];
    return List<dynamic>.from(
      tree[group],
    ).whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
  }

  List<String> get pendingSubReasons {
    final reason = pendingReasons
        .where((item) => item['label'] == pending['pending_reason'])
        .firstOrNull;
    return List<dynamic>.from(
      reason?['subs'] ?? [],
    ).map((item) => item.toString()).toList();
  }

  String _stamp(DateTime value) =>
      DateFormat("yyyy-MM-dd'T'HH:mm:ss").format(value);
  String _day(DateTime value) => DateFormat('yyyy-MM-dd').format(value);

  Future<void> pickTime(String field) async {
    final initial = times[field] ?? DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now().subtract(const Duration(days: 60)),
      lastDate: DateTime.now(),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null) return;
    setState(
      () => times[field] = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  Future<DateTime?> pickDay(DateTime? current) => showDatePicker(
    context: context,
    initialDate: current ?? DateTime.now(),
    firstDate: DateUtils.dateOnly(DateTime.now()),
    lastDate: DateTime.now().add(const Duration(days: 365)),
  );

  Future<void> addPhotos(ImageSource source) async {
    final picker = ImagePicker();
    final picked = source == ImageSource.camera
        ? [
            await picker.pickImage(source: source, imageQuality: 80),
          ].whereType<XFile>().toList()
        : await picker.pickMultiImage(imageQuality: 80);
    if (!mounted || picked.isEmpty) return;
    if (photos.length + picked.length > 10) {
      showAppSnack(
        context,
        'You can attach up to 10 photos at a time.',
        tone: Tone.danger,
      );
      return;
    }
    setState(() => photos.addAll(picked));
  }

  Future<void> captureSignature() async {
    final bytes = await showSignatureCapture(
      context,
      title: context.tr('Customer signature'),
    );
    if (bytes != null && mounted) setState(() => signature = bytes);
  }

  String? validate(bool submitting) {
    if (signature != null &&
        text['customer_signed_name']!.text.trim().isEmpty) {
      return 'Enter the name of the person who signed.';
    }
    if (!submitting) return null;
    if (outcome == null) return 'Choose the visit result.';
    if (text['problem_diagnosis']!.text.trim().isEmpty) {
      return 'Describe exactly what was wrong (problem diagnosis).';
    }
    if (text['work_performed']!.text.trim().isEmpty) {
      return 'Describe the work performed.';
    }
    if (choices['final_result'] == null) {
      return 'Choose whether the unit is working after the visit.';
    }
    if (outcome == 'resolved' && choices['final_result'] == 'not_working') {
      return 'A visit cannot be resolved while the unit is not working. Choose "Pending" or "Under observation".';
    }
    if (outcome == 'pending') {
      for (final (key, label) in const [
        ('pending_group', 'pending group'),
        ('pending_reason', 'pending reason'),
        ('pending_owner', 'pending owner'),
        ('follow_up_date', 'next follow-up date'),
        ('target_date', 'targeted closing date'),
        ('delay_reason', 'reason for delay'),
      ]) {
        if (pending[key] == null) return 'Select the $label.';
      }
      if (pendingText['next_action']!.text.trim().isEmpty) {
        return 'Enter the next action.';
      }
      if (pending['target_date']!.compareTo(pending['follow_up_date']!) < 0) {
        return 'The targeted closing date cannot be before the next follow-up.';
      }
    }
    if (outcome == 'observation' && observationFollowUp == null) {
      return 'Select the observation follow-up date.';
    }
    return null;
  }

  Future<void> save({required bool submit}) async {
    final problem = validate(submit);
    if (problem != null) {
      setState(() => error = problem);
      showAppSnack(context, problem, tone: Tone.danger);
      return;
    }
    if (submit) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          icon: const Icon(LucideIcons.send, color: AppColors.primary),
          title: const T('Submit visit result?'),
          content: T(
            outcome == 'resolved'
                ? 'The visit will be completed and the complaint marked ready for closure checks.'
                : outcome == 'pending'
                ? 'The visit will be completed and the complaint stays pending with the reason you selected.'
                : 'The visit will be completed and the complaint kept under observation.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const T('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const T('Submit'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
      times['ended_at'] ??= DateTime.now();
    }

    setState(() {
      saving = true;
      error = null;
    });
    final dio = context.read<ApiClient>().dio;
    try {
      final data = <String, dynamic>{
        for (final entry in times.entries)
          if (entry.value != null) entry.key: _stamp(entry.value!),
        for (final entry in text.entries) entry.key: entry.value.text.trim(),
        for (final key in ['fault_category', 'root_cause', 'final_result'])
          if (choices[key] != null) key: choices[key],
        if (rating != null) 'customer_rating': rating,
        'checks': {
          for (final entry in checks.entries)
            if (entry.value != null) entry.key: entry.value,
        },
        'diagnosis': {
          for (final entry in readings.entries)
            if (entry.value.text.trim().isNotEmpty)
              entry.key: entry.value.text.trim(),
          for (final entry in choices.entries)
            if (entry.key.startsWith('diagnosis.') && entry.value != null)
              entry.key.substring(10): entry.value,
        },
        if (submit) 'outcome': outcome,
        if (submit && outcome == 'pending')
          'pending': {
            for (final entry in pending.entries)
              if (entry.value != null) entry.key: entry.value,
            'next_action': pendingText['next_action']!.text.trim(),
            'remarks': pendingText['remarks']!.text.trim(),
          },
        if (submit && outcome == 'observation') ...{
          'observation_follow_up_date': _day(observationFollowUp!),
          'observation_next_action': observationNextAction.text.trim(),
        },
        if (signature != null)
          'signature_data': 'data:image/png;base64,${base64Encode(signature!)}',
      };
      // Empty maps would clear saved values; leave them out so the server keeps them.
      if ((data['checks'] as Map).isEmpty) data.remove('checks');
      if ((data['diagnosis'] as Map).isEmpty) data.remove('diagnosis');
      if (photos.isNotEmpty) {
        data['attachments[]'] = [
          for (final photo in photos)
            await MultipartFile.fromFile(photo.path, filename: photo.name),
        ];
      }

      final response = await dio.post(
        'complaints/${widget.complaint['id']}/visits/${visit['id']}/report',
        data: FormData.fromMap(data),
      );
      if (!mounted) return;
      showAppSnack(
        context,
        response.data['message']?.toString() ?? 'Visit report saved.',
        tone: Tone.success,
      );
      Navigator.pop(context, true);
    } catch (exception) {
      if (mounted) {
        final message = apiErrorMessage(
          exception,
          'Unable to save the visit report. Your entries are still here.',
        );
        setState(() => error = message);
        showAppSnack(context, message, tone: Tone.danger);
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final diagnosisFields = _list('diagnosis_fields');
    final checkList = _list('checks');
    final existingAttachments = List<dynamic>.from(
      visit['attachments'] ?? [],
    ).length;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const T('Visit report'),
            Text(
              '${widget.complaint['complaint_no'] ?? ''} · ${visit['label'] ?? ''}',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
      body: GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: ListView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
          children: [
            if (error != null) ...[
              NoticeBox(error!),
              const SizedBox(height: 12),
            ],
            _Step(
              number: 1,
              title: 'Time on site',
              icon: LucideIcons.clock,
              child: Column(
                children: [
                  for (final (index, (field, label)) in timeFields.indexed)
                    _TimeRow(
                      label: label,
                      value: times[field],
                      last: index == timeFields.length - 1,
                      onPick: () => pickTime(field),
                      onNow: () =>
                          setState(() => times[field] = DateTime.now()),
                    ),
                ],
              ),
            ),
            if (checkList.isNotEmpty)
              _Step(
                number: 2,
                title: 'Site checks',
                icon: LucideIcons.listChecks,
                child: Column(
                  children: [
                    for (final (index, check) in checkList.indexed)
                      _YesNoRow(
                        label: check['label'].toString(),
                        value: checks[check['key'].toString()],
                        last: index == checkList.length - 1,
                        options: options('yes_no'),
                        onChanged: (value) => setState(
                          () => checks[check['key'].toString()] = value,
                        ),
                      ),
                  ],
                ),
              ),
            if (diagnosisFields.isNotEmpty)
              _Step(
                number: 3,
                title: 'Readings & condition',
                subtitle: widget.complaint['equipment_type_label']?.toString(),
                icon: LucideIcons.gauge,
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 4,
                ),
                child: Column(
                  children: [
                    for (final (index, field) in diagnosisFields.indexed)
                      _ReadingRow(
                        label: field['label'].toString(),
                        unit: field['unit']?.toString(),
                        last: index == diagnosisFields.length - 1,
                        child: switch (field['type']?.toString()) {
                          'condition' || 'yes_no' => _Select(
                            label: '',
                            value: choices['diagnosis.${field['key']}'],
                            options: options(
                              field['type'] == 'condition'
                                  ? 'conditions'
                                  : 'yes_no',
                            ),
                            onChanged: (value) => setState(
                              () =>
                                  choices['diagnosis.${field['key']}'] = value,
                            ),
                          ),
                          final type => TextField(
                            controller: readings[field['key'].toString()],
                            textAlign: TextAlign.end,
                            keyboardType: type == 'number'
                                ? const TextInputType.numberWithOptions(
                                    decimal: true,
                                    signed: true,
                                  )
                                : TextInputType.text,
                            inputFormatters: type == 'number'
                                ? [
                                    FilteringTextInputFormatter.allow(
                                      RegExp(r'^-?\d*\.?\d*'),
                                    ),
                                  ]
                                : null,
                            decoration: InputDecoration(hintText: '—'),
                          ),
                        },
                      ),
                  ],
                ),
              ),
            _Step(
              number: 4,
              title: 'Diagnosis & work done',
              icon: LucideIcons.stethoscope,
              child: Column(
                children: [
                  _field('observation', 'Observation at site', lines: 2),
                  _field('initial_diagnosis', 'Initial diagnosis', lines: 2),
                  Row(
                    children: [
                      Expanded(
                        child: _Select(
                          label: context.tr('Fault category'),
                          value: choices['fault_category'],
                          options: options('fault_categories'),
                          onChanged: (value) =>
                              setState(() => choices['fault_category'] = value),
                        ),
                      ),
                      const SizedBox(width: 10),
                      SizedBox(
                        width: 120,
                        child: _field('fault_code', 'Fault code', gap: false),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _Select(
                    label: context.tr('Root cause'),
                    value: choices['root_cause'],
                    options: options('root_causes'),
                    onChanged: (value) =>
                        setState(() => choices['root_cause'] = value),
                  ),
                  const SizedBox(height: 12),
                  _field('abnormality_found', 'Abnormality found', lines: 2),
                  _field(
                    'problem_diagnosis',
                    'Problem diagnosis *',
                    lines: 3,
                    hint: 'What exactly was wrong',
                  ),
                  _field(
                    'work_performed',
                    'Work performed *',
                    lines: 3,
                    hint: 'What you did to fix it',
                    gap: false,
                  ),
                ],
              ),
            ),
            _Step(
              number: 5,
              title: 'Photos & documents',
              subtitle: existingAttachments == 0
                  ? null
                  : '$existingAttachments already uploaded',
              icon: LucideIcons.camera,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (photos.isNotEmpty) ...[
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final photo in photos)
                          Stack(
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                                child: Image.file(
                                  File(photo.path),
                                  width: 76,
                                  height: 76,
                                  fit: BoxFit.cover,
                                ),
                              ),
                              Positioned(
                                top: 2,
                                right: 2,
                                child: InkWell(
                                  onTap: saving
                                      ? null
                                      : () => setState(
                                          () => photos.remove(photo),
                                        ),
                                  child: Container(
                                    padding: const EdgeInsets.all(3),
                                    decoration: const BoxDecoration(
                                      color: Colors.black54,
                                      shape: BoxShape.circle,
                                    ),
                                    child: const Icon(
                                      LucideIcons.x,
                                      size: 13,
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                      ],
                    ),
                    const SizedBox(height: 12),
                  ],
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: saving
                              ? null
                              : () => addPhotos(ImageSource.camera),
                          icon: const Icon(LucideIcons.camera, size: 17),
                          label: const T('Take photo'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: saving
                              ? null
                              : () => addPhotos(ImageSource.gallery),
                          icon: const Icon(LucideIcons.images, size: 17),
                          label: const T('Gallery'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            _Step(
              number: 6,
              title: 'Customer sign-off',
              icon: LucideIcons.signature,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _field('customer_signed_name', 'Signed by (name)'),
                  _field('customer_remarks', 'Customer remarks', lines: 2),
                  const T(
                    'Customer rating',
                    style: TextStyle(color: AppColors.muted, fontSize: 12.5),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      for (var star = 1; star <= 5; star++)
                        IconButton(
                          visualDensity: VisualDensity.compact,
                          onPressed: () => setState(
                            () => rating = rating == star ? null : star,
                          ),
                          icon: Icon(
                            LucideIcons.star,
                            size: 26,
                            color: (rating ?? 0) >= star
                                ? const Color(0xFFF59E0B)
                                : AppColors.borderStrong,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (signature != null)
                    Container(
                      height: 110,
                      margin: const EdgeInsets.only(bottom: 10),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        border: Border.all(color: AppColors.border),
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                      child: Image.memory(signature!, fit: BoxFit.contain),
                    )
                  else if (visit['has_signature'] == true)
                    const Padding(
                      padding: EdgeInsets.only(bottom: 10),
                      child: NoticeBox(
                        'A customer signature is already on file for this visit.',
                        tone: Tone.success,
                      ),
                    ),
                  OutlinedButton.icon(
                    onPressed: saving ? null : captureSignature,
                    icon: const Icon(LucideIcons.penLine, size: 17),
                    label: T(
                      signature == null
                          ? 'Capture customer signature'
                          : 'Sign again',
                    ),
                  ),
                ],
              ),
            ),
            _Step(
              number: 7,
              title: 'Visit result',
              icon: LucideIcons.flagTriangleRight,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Select(
                    label: context.tr('Unit after visit *'),
                    value: choices['final_result'],
                    options: options('final_results'),
                    onChanged: (value) =>
                        setState(() => choices['final_result'] = value),
                  ),
                  const SizedBox(height: 14),
                  const T(
                    'Outcome *',
                    style: TextStyle(color: AppColors.muted, fontSize: 12.5),
                  ),
                  const SizedBox(height: 8),
                  for (final option in options('outcomes'))
                    _OutcomeTile(
                      option: option,
                      selected: outcome == option.value,
                      onTap: () => setState(
                        () => outcome = outcome == option.value
                            ? null
                            : option.value,
                      ),
                    ),
                  if (outcome == 'pending') ...[
                    const SizedBox(height: 6),
                    _pendingFields(),
                  ],
                  if (outcome == 'observation') ...[
                    const SizedBox(height: 6),
                    _DateField(
                      label: 'Observation follow-up date *',
                      value: observationFollowUp,
                      onTap: () async {
                        final value = await pickDay(observationFollowUp);
                        if (value != null) {
                          setState(() => observationFollowUp = value);
                        }
                      },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: observationNextAction,
                      decoration: InputDecoration(
                        labelText: context.tr('Next action'),
                        hintText: context.tr('Check unit performance'),
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  _field(
                    'technician_remarks',
                    'Technician remarks',
                    lines: 2,
                    gap: false,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: BottomActionBar(
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: saving ? null : () => save(submit: false),
                icon: const Icon(LucideIcons.save, size: 17),
                label: const T('Save progress'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: BusyButton(
                label: 'Submit result',
                busyLabel: 'Saving…',
                icon: LucideIcons.send,
                busy: saving,
                onPressed: () => save(submit: true),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _field(
    String key,
    String label, {
    int lines = 1,
    String? hint,
    bool gap = true,
  }) => Padding(
    padding: EdgeInsets.only(bottom: gap ? 12 : 0),
    child: TextField(
      controller: text[key],
      minLines: lines,
      maxLines: lines == 1 ? 1 : lines + 3,
      textCapitalization: TextCapitalization.sentences,
      decoration: InputDecoration(
        labelText: context.tr(label),
        hintText: hint == null ? null : context.tr(hint),
        alignLabelWithHint: lines > 1,
      ),
    ),
  );

  Widget _pendingFields() {
    final reasons = pendingReasons;
    final subs = pendingSubReasons;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.dangerSoft.withValues(alpha: .45),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.danger.withValues(alpha: .2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const T(
            'A pending complaint must carry its reason, owner and dates.',
            style: TextStyle(
              color: AppColors.danger,
              fontSize: 12.5,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 12),
          _Select(
            label: context.tr('Pending group *'),
            value: pending['pending_group'],
            options: options('pending_groups'),
            onChanged: (value) => setState(() {
              pending['pending_group'] = value;
              pending['pending_reason'] = null;
              pending['pending_sub_reason'] = null;
            }),
          ),
          const SizedBox(height: 12),
          _Select(
            label: context.tr('Pending reason *'),
            value: pending['pending_reason'],
            options: [
              for (final reason in reasons)
                (
                  value: reason['label'].toString(),
                  label: reason['label'].toString(),
                ),
            ],
            onChanged: (value) => setState(() {
              pending['pending_reason'] = value;
              pending['pending_sub_reason'] = null;
            }),
          ),
          if (subs.isNotEmpty) ...[
            const SizedBox(height: 12),
            _Select(
              label: context.tr('Sub category'),
              value: pending['pending_sub_reason'],
              options: [for (final sub in subs) (value: sub, label: sub)],
              onChanged: (value) =>
                  setState(() => pending['pending_sub_reason'] = value),
            ),
          ],
          const SizedBox(height: 12),
          _Select(
            label: context.tr('Pending with *'),
            value: pending['pending_owner'],
            options: options('pending_owners'),
            onChanged: (value) =>
                setState(() => pending['pending_owner'] = value),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: pendingText['next_action'],
            decoration: InputDecoration(labelText: context.tr('Next action *')),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _DateField(
                  label: 'Next follow-up *',
                  value: DateTime.tryParse(pending['follow_up_date'] ?? ''),
                  onTap: () async {
                    final value = await pickDay(
                      DateTime.tryParse(pending['follow_up_date'] ?? ''),
                    );
                    if (value != null) {
                      setState(() => pending['follow_up_date'] = _day(value));
                    }
                  },
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _DateField(
                  label: 'Target closing *',
                  value: DateTime.tryParse(pending['target_date'] ?? ''),
                  onTap: () async {
                    final value = await pickDay(
                      DateTime.tryParse(
                        pending['target_date'] ??
                            pending['follow_up_date'] ??
                            '',
                      ),
                    );
                    if (value != null) {
                      setState(() => pending['target_date'] = _day(value));
                    }
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _DateField(
            label: 'Expected material date',
            value: DateTime.tryParse(pending['expected_material_date'] ?? ''),
            onTap: () async {
              final value = await pickDay(
                DateTime.tryParse(pending['expected_material_date'] ?? ''),
              );
              if (value != null) {
                setState(() => pending['expected_material_date'] = _day(value));
              }
            },
          ),
          const SizedBox(height: 12),
          _Select(
            label: context.tr('Reason for delay *'),
            value: pending['delay_reason'],
            options: options('delay_reasons'),
            onChanged: (value) =>
                setState(() => pending['delay_reason'] = value),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: pendingText['remarks'],
            minLines: 2,
            maxLines: 4,
            decoration: InputDecoration(
              labelText: context.tr('Pending remarks'),
              alignLabelWithHint: true,
            ),
          ),
        ],
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({
    required this.number,
    required this.title,
    required this.icon,
    required this.child,
    this.subtitle,
    this.padding = const EdgeInsets.all(16),
  });

  final EdgeInsetsGeometry padding;

  final int number;
  final String title;
  final String? subtitle;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: AppColors.surface2,
            padding: const EdgeInsets.fromLTRB(14, 11, 14, 11),
            child: Row(
              children: [
                Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    color: AppColors.navy,
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '$number',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      T(
                        title,
                        style: const TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.heading,
                        ),
                      ),
                      if (subtitle != null)
                        Text(
                          subtitle!,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.muted,
                          ),
                        ),
                    ],
                  ),
                ),
                Icon(icon, size: 18, color: AppColors.subtle),
              ],
            ),
          ),
          const Divider(),
          Padding(padding: padding, child: child),
        ],
      ),
    ),
  );
}

class _ReadingRow extends StatelessWidget {
  const _ReadingRow({
    required this.label,
    required this.child,
    this.unit,
    this.last = false,
  });

  final String label;
  final String? unit;
  final Widget child;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 8),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: AppColors.border)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Text.rich(
            TextSpan(
              text: label,
              children: [
                if (unit != null)
                  TextSpan(
                    text: '  ($unit)',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12,
                    ),
                  ),
              ],
            ),
            style: const TextStyle(fontSize: 13.5),
          ),
        ),
        const SizedBox(width: 12),
        SizedBox(width: 148, child: child),
      ],
    ),
  );
}

class _TimeRow extends StatelessWidget {
  const _TimeRow({
    required this.label,
    required this.value,
    required this.onPick,
    required this.onNow,
    this.last = false,
  });

  final String label;
  final DateTime? value;
  final VoidCallback onPick;
  final VoidCallback onNow;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 6),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: AppColors.border)),
    ),
    child: Row(
      children: [
        Expanded(
          child: T(
            label,
            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500),
          ),
        ),
        InkWell(
          onTap: onPick,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
            child: Row(
              children: [
                Text(
                  value == null
                      ? 'Set time'
                      : DateFormat('d MMM, hh:mm a').format(value!),
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: value == null ? AppColors.subtle : AppColors.heading,
                  ),
                ),
                const SizedBox(width: 6),
                const Icon(
                  LucideIcons.pencil,
                  size: 13,
                  color: AppColors.muted,
                ),
              ],
            ),
          ),
        ),
        if (value == null) TextButton(onPressed: onNow, child: const T('Now')),
      ],
    ),
  );
}

class _YesNoRow extends StatelessWidget {
  const _YesNoRow({
    required this.label,
    required this.value,
    required this.options,
    required this.onChanged,
    this.last = false,
  });

  final String label;
  final String? value;
  final List<Option> options;
  final ValueChanged<String?> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final choices = options.isEmpty
        ? const [(value: 'yes', label: 'Yes'), (value: 'no', label: 'No')]
        : options;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        border: last
            ? null
            : const Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(fontSize: 13.5))),
          const SizedBox(width: 8),
          for (final option in choices) ...[
            ChoiceChip(
              label: Text(option.label),
              selected: value == option.value,
              selectedColor: option.value == 'no'
                  ? AppColors.dangerSoft
                  : AppColors.successSoft,
              labelStyle: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: value != option.value
                    ? AppColors.ink
                    : option.value == 'no'
                    ? AppColors.danger
                    : AppColors.success,
              ),
              onSelected: (selected) =>
                  onChanged(selected ? option.value : null),
            ),
            const SizedBox(width: 6),
          ],
        ],
      ),
    );
  }
}

class _Select extends StatelessWidget {
  const _Select({
    required this.label,
    required this.value,
    required this.options,
    required this.onChanged,
  });

  final String label;
  final String? value;
  final List<Option> options;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    // Keep a saved value visible even if the master was renamed or switched off.
    final items = [
      ...options,
      if (value != null && !options.any((option) => option.value == value))
        (value: value!, label: value!),
    ];
    return DropdownButtonFormField<String>(
      key: ValueKey('$label$value${items.length}'),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: label.isEmpty ? null : label,
        hintText: label.isEmpty ? context.tr('Select') : null,
      ),
      items: [
        for (final option in items)
          DropdownMenuItem(
            value: option.value,
            child: Text(option.label, overflow: TextOverflow.ellipsis),
          ),
      ],
      onChanged: onChanged,
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({
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
        suffixIcon: const Icon(LucideIcons.calendar, size: 18),
      ),
      child: Text(
        value == null ? '' : DateFormat('d MMM yyyy').format(value!),
        style: const TextStyle(fontSize: 14),
      ),
    ),
  );
}

class _OutcomeTile extends StatelessWidget {
  const _OutcomeTile({
    required this.option,
    required this.selected,
    required this.onTap,
  });

  final Option option;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final tone = switch (option.value) {
      'resolved' => Tone.success,
      'pending' => Tone.danger,
      _ => Tone.purple,
    };
    final icon = switch (option.value) {
      'resolved' => LucideIcons.circleCheckBig,
      'pending' => LucideIcons.hourglass,
      _ => LucideIcons.eye,
    };
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: selected ? tone.soft : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(
            color: selected ? tone.color : AppColors.border,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.md),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            child: Row(
              children: [
                Icon(icon, size: 19, color: tone.color),
                const SizedBox(width: 12),
                Expanded(
                  child: T(
                    option.label,
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 13.5,
                      color: selected ? tone.color : AppColors.ink,
                    ),
                  ),
                ),
                Icon(
                  selected ? LucideIcons.circleCheck : LucideIcons.circle,
                  size: 19,
                  color: selected ? tone.color : AppColors.borderStrong,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
