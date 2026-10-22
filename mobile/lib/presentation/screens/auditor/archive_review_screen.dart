import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../widgets/auditor_page_header.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class ArchiveReviewScreen extends StatefulWidget {
  const ArchiveReviewScreen({super.key});

  @override
  State<ArchiveReviewScreen> createState() => _ArchiveReviewScreenState();
}

class _ArchiveReviewScreenState extends State<ArchiveReviewScreen> {
  final _repository = ArchiveRepository();
  final Set<String> _busyDocuments = {};
  final Set<String> _verifiedDocuments = {};
  late Future<List<ArchiveDocumentModel>> _archives;

  @override
  void initState() {
    super.initState();
    _archives = _repository.getArchiveDocuments();
  }

  Future<void> _refresh() async {
    setState(() => _archives = _repository.getArchiveDocuments());
    await _archives;
  }

  Future<void> _verify(ArchiveDocumentModel archive) async {
    setState(() => _busyDocuments.add(archive.id));
    try {
      final verified = await _repository.verifyChecksum(archive.id);
      if (!mounted) return;
      setState(() {
        if (verified) _verifiedDocuments.add(archive.id);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            verified
                ? 'Checksum verified for ${archive.originalName}.'
                : 'Checksum verification failed for ${archive.originalName}.',
          ),
        ),
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not verify archive: $error')),
        );
      }
    } finally {
      if (mounted) setState(() => _busyDocuments.remove(archive.id));
    }
  }

  Future<void> _download(ArchiveDocumentModel archive) async {
    setState(() => _busyDocuments.add(archive.id));
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
      if (mounted) setState(() => _busyDocuments.remove(archive.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: UstpAppBar(
        onNotificationTap: () async =>
            context.go('/auditor/profile/notifications'),
      ),
      body: FutureBuilder<List<ArchiveDocumentModel>>(
        future: _archives,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _ArchiveError(onRetry: _refresh);
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final archives = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 18),
              children: [
                const AuditorPageHeader(
                  title: 'Archive Review',
                  subtitle: 'Evidence uploaded by your auditees',
                ),
                if (archives.isEmpty)
                  const SizedBox(
                    height: 330,
                    child: EmptyState(
                      title: 'No evidence documents',
                      subtitle:
                          'Evidence uploaded in your assigned departments will appear here.',
                      icon: AppIcons.archive,
                    ),
                  )
                else
                  for (final archive in archives)
                    _ArchiveCard(
                      archive: archive,
                      isBusy: _busyDocuments.contains(archive.id),
                      isVerified: archive.isVerified ||
                          _verifiedDocuments.contains(archive.id),
                      onVerify: () => _verify(archive),
                      onDownload: () => _download(archive),
                    ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ArchiveCard extends StatelessWidget {
  const _ArchiveCard({
    required this.archive,
    required this.isBusy,
    required this.isVerified,
    required this.onVerify,
    required this.onDownload,
  });

  final ArchiveDocumentModel archive;
  final bool isBusy;
  final bool isVerified;
  final VoidCallback onVerify;
  final VoidCallback onDownload;

  @override
  Widget build(BuildContext context) {
    final isSpreadsheet = archive.originalName.toLowerCase().endsWith('.xlsx');
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: [
          Container(
            height: 70,
            width: double.infinity,
            color: AppColors.ustpLight,
            child: Icon(
              isSpreadsheet ? AppIcons.spreadsheet : AppIcons.document,
              color: AppColors.ustpNavy,
              size: 34,
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(9, 8, 7, 6),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  archive.originalName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  'Uploaded by ${archive.uploadedBy} · ${DateFormat('yyyy-MM-dd').format(archive.createdAt.toLocal())}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 8,
                    color: AppColors.ustpMuted,
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.only(top: 5),
                  child: Divider(height: 1, color: Color(0xFFE2E8F0)),
                ),
                Row(
                  children: [
                    TextButton(
                      onPressed: isBusy ? null : onVerify,
                      style: TextButton.styleFrom(
                        backgroundColor: isVerified
                            ? const Color(0xFFDCFCE7)
                            : AppColors.ustpLight,
                        foregroundColor:
                            isVerified ? AppColors.success : AppColors.ustpDark,
                        minimumSize: const Size(0, 25),
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        textStyle: const TextStyle(fontSize: 8),
                      ),
                      child: Text(
                        isBusy
                            ? 'Checking…'
                            : isVerified
                                ? 'Checksum Verified'
                                : 'Verify Checksum',
                      ),
                    ),
                    const Spacer(),
                    IconButton(
                      onPressed: isBusy ? null : onDownload,
                      tooltip: 'Download ${archive.originalName}',
                      visualDensity: VisualDensity.compact,
                      icon: const Icon(
                        AppIcons.download,
                        color: AppColors.ustpNavy,
                        size: 18,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ArchiveError extends StatelessWidget {
  const _ArchiveError({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('Could not load archive records.'),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(AppIcons.refresh),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}
