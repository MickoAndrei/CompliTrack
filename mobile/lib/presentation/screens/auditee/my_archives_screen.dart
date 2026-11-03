import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../widgets/ustp_app_bar.dart';

class MyArchivesScreen extends StatefulWidget {
  const MyArchivesScreen({super.key});

  @override
  State<MyArchivesScreen> createState() => _MyArchivesScreenState();
}

class _MyArchivesScreenState extends State<MyArchivesScreen> {
  final _repository = ArchiveRepository();
  late Future<List<ArchiveDocumentModel>> _archives;
  final Set<String> _downloading = {};

  @override
  void initState() {
    super.initState();
    _archives = _repository.getArchiveDocuments();
  }

  Future<void> _refresh() async {
    final next = _repository.getArchiveDocuments();
    setState(() => _archives = next);
    await next;
  }

  Future<void> _download(ArchiveDocumentModel archive) async {
    setState(() => _downloading.add(archive.id));
    try {
      final bytes = await _repository.downloadArchive(archive.id);
      final savedPath = await FilePicker.saveFile(
        fileName: archive.originalName,
        bytes: bytes,
      );
      if (mounted && savedPath != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Downloaded ${archive.originalName}.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not download archive: $error')),
        );
      }
    } finally {
      if (mounted) setState(() => _downloading.remove(archive.id));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: AppColors.ustpLight,
        appBar: UstpAppBar(
          onProfileTap: () => context.go('/auditee/profile'),
        ),
        body: FutureBuilder<List<ArchiveDocumentModel>>(
          future: _archives,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting &&
                !snapshot.hasData) {
              return const Center(
                child: CircularProgressIndicator(color: AppColors.ustpNavy),
              );
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'Could not load your archives.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: AppColors.ustpDark),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: _refresh,
                        child: const Text('Try again'),
                      ),
                    ],
                  ),
                ),
              );
            }

            final archives = snapshot.data ?? const <ArchiveDocumentModel>[];
            return RefreshIndicator(
              color: AppColors.ustpNavy,
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: _ArchiveSummaryCard(
                          label: 'TOTAL ARCHIVED',
                          value: '${archives.length} Files',
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: _ArchiveSummaryCard(
                          label: 'SECURITY STATUS',
                          value: 'Securely Encrypted',
                          compactValue: true,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 36),
                    decoration: BoxDecoration(
                      color: AppColors.ustpWhite,
                      borderRadius: BorderRadius.circular(13),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Secure File Vault',
                          style: TextStyle(
                            fontFamily: 'Poppins',
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ustpDark,
                          ),
                        ),
                        const SizedBox(height: 3),
                        const Text(
                          'Philippine Gov Audit Standard compliant storage',
                          style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 10,
                            color: AppColors.ustpMuted,
                          ),
                        ),
                        const SizedBox(height: 14),
                        if (archives.isEmpty)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 16),
                            child: Center(
                              child: Text(
                                'No archived evidence yet.',
                                style: TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 12,
                                  color: AppColors.ustpMuted,
                                ),
                              ),
                            ),
                          )
                        else
                          for (var index = 0;
                              index < archives.length;
                              index++) ...[
                            if (index > 0) const SizedBox(height: 46),
                            _ArchiveFileCard(
                              archive: archives[index],
                              downloading:
                                  _downloading.contains(archives[index].id),
                              onDownload: () => _download(archives[index]),
                            ),
                          ],
                        const SizedBox(height: 38),
                        _ArchiveUploadTarget(
                          onTap: () => context.go('/auditee/upload'),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      );
}

class _ArchiveSummaryCard extends StatelessWidget {
  const _ArchiveSummaryCard({
    required this.label,
    required this.value,
    this.compactValue = false,
  });

  final String label;
  final String value;
  final bool compactValue;

  @override
  Widget build(BuildContext context) => Container(
        height: 87,
        padding: const EdgeInsets.fromLTRB(15, 14, 12, 12),
        decoration: BoxDecoration(
          color: AppColors.ustpWhite,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 9,
                color: AppColors.ustpMuted,
              ),
            ),
            const SizedBox(height: 7),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                maxLines: 1,
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: compactValue ? 14 : 20,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ustpNavy,
                ),
              ),
            ),
          ],
        ),
      );
}

class _ArchiveFileCard extends StatelessWidget {
  const _ArchiveFileCard({
    required this.archive,
    required this.downloading,
    required this.onDownload,
  });

  final ArchiveDocumentModel archive;
  final bool downloading;
  final VoidCallback onDownload;

  @override
  Widget build(BuildContext context) {
    final extension = archive.originalName.split('.').last.toLowerCase();
    final spreadsheet = {'xls', 'xlsx', 'csv'}.contains(extension);
    final iconColor =
        spreadsheet ? const Color(0xFF059669) : AppColors.ustpNavy;
    final iconTint =
        spreadsheet ? const Color(0xFF10B981) : const Color(0xFFFEE2E2);
    final icon = spreadsheet
        ? Icons.inventory_2_outlined
        : Icons.insert_drive_file_outlined;
    final type = switch (extension) {
      'pdf' => 'PDF Document',
      'xls' || 'xlsx' => 'Excel Spreadsheet',
      'csv' => 'CSV File',
      _ => extension.toUpperCase(),
    };

    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F9FC),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: const Color(0xFFE0E7F0)),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: iconTint,
                  borderRadius: BorderRadius.circular(7),
                ),
                child: Icon(icon, size: 19, color: iconColor),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      archive.originalName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: AppColors.ustpDark,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${DateFormat('MMM d, yyyy').format(archive.createdAt.toLocal())} · $type',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 10,
                        color: AppColors.ustpMuted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            height: 34,
            child: OutlinedButton.icon(
              onPressed: downloading ? null : onDownload,
              icon: downloading
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.cloud_download_outlined, size: 17),
              label: Text(
                  downloading ? 'Preparing file...' : 'Download / Decrypt'),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.ustpNavy,
                side: const BorderSide(color: AppColors.ustpNavy, width: 1.2),
                padding: EdgeInsets.zero,
                textStyle: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(6),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ArchiveUploadTarget extends StatelessWidget {
  const _ArchiveUploadTarget({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: CustomPaint(
          foregroundPainter: const _DashedBorderPainter(),
          child: Container(
            height: 84,
            decoration: BoxDecoration(
              color: const Color(0xFFFAFBFD),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.cloud_upload_outlined,
                    size: 22, color: AppColors.ustpMuted),
                SizedBox(height: 4),
                Text(
                  'Securely archive more evidence',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 10,
                    color: AppColors.ustpMuted,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
}

class _DashedBorderPainter extends CustomPainter {
  const _DashedBorderPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF8195B2)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    final path = Path()
      ..addRRect(RRect.fromRectAndRadius(
        Offset.zero & size,
        const Radius.circular(8),
      ));
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final end = (distance + 5).clamp(0, metric.length).toDouble();
        canvas.drawPath(metric.extractPath(distance, end), paint);
        distance += 8;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
