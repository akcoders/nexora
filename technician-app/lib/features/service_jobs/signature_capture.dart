import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

import '../../core/theme/app_theme.dart';

Future<Uint8List?> showSignatureCapture(
  BuildContext context, {
  required String title,
}) => showDialog<Uint8List>(
  context: context,
  barrierDismissible: false,
  builder: (_) => _SignatureDialog(title: title),
);

class _SignatureDialog extends StatefulWidget {
  const _SignatureDialog({required this.title});

  final String title;

  @override
  State<_SignatureDialog> createState() => _SignatureDialogState();
}

class _SignatureDialogState extends State<_SignatureDialog> {
  final boundaryKey = GlobalKey();
  final points = <Offset?>[];
  bool saving = false;

  Future<void> save() async {
    if (points.whereType<Offset>().length < 3) return;
    setState(() => saving = true);
    final boundary =
        boundaryKey.currentContext!.findRenderObject() as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 3);
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    if (mounted) Navigator.pop(context, data?.buffer.asUint8List());
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: SizedBox(
      width: 420,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Sign inside the box',
            style: TextStyle(color: AppColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: RepaintBoundary(
              key: boundaryKey,
              child: ColoredBox(
                color: Colors.white,
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onPanStart: (details) =>
                      setState(() => points.add(details.localPosition)),
                  onPanUpdate: (details) =>
                      setState(() => points.add(details.localPosition)),
                  onPanEnd: (_) => setState(() => points.add(null)),
                  child: CustomPaint(
                    painter: _SignaturePainter(points),
                    size: const Size(double.infinity, 190),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 4),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton(
              onPressed: saving ? null : () => setState(() => points.clear()),
              child: const Text('Clear'),
            ),
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: saving ? null : () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: saving || points.whereType<Offset>().length < 3
            ? null
            : save,
        child: Text(saving ? 'Saving…' : 'Use signature'),
      ),
    ],
  );
}

class _SignaturePainter extends CustomPainter {
  const _SignaturePainter(this.points);

  final List<Offset?> points;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(
      Offset.zero & size,
      Paint()
        ..color = const Color(0xFFE2E8F0)
        ..style = PaintingStyle.stroke,
    );
    final pen = Paint()
      ..color = AppColors.ink
      ..strokeWidth = 2.6
      ..strokeCap = StrokeCap.round;
    for (var index = 0; index < points.length - 1; index++) {
      final start = points[index];
      final end = points[index + 1];
      if (start != null && end != null) canvas.drawLine(start, end, pen);
    }
  }

  @override
  bool shouldRepaint(covariant _SignaturePainter oldDelegate) => true;
}
