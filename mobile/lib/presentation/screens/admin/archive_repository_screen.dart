import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/models/department_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../../data/repositories/department_repository.dart';
import '../../widgets/admin_page_header.dart';
import '../../widgets/empty_state.dart';

class ArchiveRepositoryScreen extends StatefulWidget {
  const ArchiveRepositoryScreen({super.key});

  @override
  State<ArchiveRepositoryScreen> createState() =>
      _ArchiveRepositoryScreenState();
}

class _ArchiveRepositoryScreenState extends State<ArchiveRepositoryScreen> {
  final _repository = ArchiveRepository();
  final _departmentsRepository = DepartmentRepository();
  String _selectedDept = 'All Departments';
  String _selectedStandard = 'ISO & ARTA';
  String _selectedDate = 'This Quarter';
  bool _isLoading = true;
  Object? _error;
  List<ArchiveDocumentModel> _documents = [];
  List<DepartmentModel> _departments = [];
  final Set<String> _busyDocuments = {};

  @override
  void initState() {
    super.initState();
    _loadArchives();
  }

  Future<void> _loadArchives() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        _repository.getArchiveDocuments(
          department: _selectedDept,
          referenceStandard: _selectedStandard,
          dateFilter: _selectedDate,
        ),
        _departmentsRepository.getDepartments(campus: 'CET Claveria'),
      ]);
      if (!mounted) return;
      setState(() {
        _documents = results[0] as List<ArchiveDocumentModel>;
        _departments = results[1] as List<DepartmentModel>;
        _isLoading = false;
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error;
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _verifyChecksum(ArchiveDocumentModel doc) async {
    setState(() => _busyDocuments.add(doc.id));
    try {
      final verified = await _repository.verifyChecksum(doc.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: verified ? AppColors.success : AppColors.error,
            content: Text(
              verified
                  ? 'Checksum verified for ${doc.originalName}.'
                  : 'Checksum verification failed for ${doc.originalName}.',
            ),
          ),
        );
        await _loadArchives();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not verify archive: $error')),
        );
      }
    } finally {
      if (mounted) setState(() => _busyDocuments.remove(doc.id));
    }
  }

  Future<void> _download(ArchiveDocumentModel doc) async {
    setState(() => _busyDocuments.add(doc.id));
    try {
      final bytes = await _repository.downloadArchive(doc.id);
      final savedPath = await FilePicker.platform.saveFile(
        fileName: doc.originalName,
        bytes: bytes,
      );
      if (mounted && savedPath != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Downloaded ${doc.originalName}.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not download archive: $error')),
        );
      }
    } finally {
      if (mounted) setState(() => _busyDocuments.remove(doc.id));
    }
  }

  void _previewDetails(ArchiveDocumentModel doc) {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(doc.originalName),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Department: ${doc.departmentCode}'),
            Text('Standard: ${doc.referenceStandard}'),
            Text('Uploaded by: ${doc.uploadedBy}'),
            Text('File size: ${_formatSize(doc.fileSize)}'),
            const SizedBox(height: 8),
            const Text(
              'Use Download to open the verified document on this device.',
              style: TextStyle(color: AppColors.ustpMuted, fontSize: 12),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      body: Column(
        children: [
          const AdminPageHeader(
            title: 'Document Archive',
            subtitle: 'System-wide repository for verified compliance records',
          ),
          Expanded(
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                  child: Column(
                    children: [
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.ustpWhite,
                          border: const Border(
                            left: BorderSide(
                              color: AppColors.ustpMuted,
                              width: 3,
                            ),
                          ),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(
                              AppIcons.lock,
                              size: 16,
                              color: AppColors.ustpMuted,
                            ),
                            SizedBox(width: 7),
                            Expanded(
                              child: Text(
                                'Read-only — Admin cannot delete archive documents once verified.',
                                style: TextStyle(
                                  color: AppColors.ustpMuted,
                                  fontSize: 10,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 8),
                      _FilterDropdown<String>(
                        label: 'Dept:',
                        value: _selectedDept,
                        items: [
                          const DropdownMenuItem(
                            value: 'All Departments',
                            child: Text('All Departments'),
                          ),
                          for (final department in _departments)
                            DropdownMenuItem(
                              value: department.departmentCode,
                              child: Text(department.departmentName),
                            ),
                        ],
                        onChanged: (value) {
                          if (value == null) return;
                          setState(() => _selectedDept = value);
                          _loadArchives();
                        },
                      ),
                      const SizedBox(height: 6),
                      _FilterDropdown<String>(
                        label: 'Standard:',
                        value: _selectedStandard,
                        items: const [
                          DropdownMenuItem(
                            value: 'ISO & ARTA',
                            child: Text('ISO & ARTA'),
                          ),
                          DropdownMenuItem(
                            value: 'ISO9001-9.2',
                            child: Text('ISO 9001'),
                          ),
                          DropdownMenuItem(
                            value: 'ARTA-3days',
                            child: Text('ARTA 3'),
                          ),
                          DropdownMenuItem(
                            value: 'ARTA-7days',
                            child: Text('ARTA 7'),
                          ),
                          DropdownMenuItem(
                            value: 'ARTA-20days',
                            child: Text('ARTA 20'),
                          ),
                        ],
                        onChanged: (value) {
                          if (value == null) return;
                          setState(() => _selectedStandard = value);
                          _loadArchives();
                        },
                      ),
                      const SizedBox(height: 6),
                      _FilterDropdown<String>(
                        label: 'Upload Date:',
                        value: _selectedDate,
                        items: const [
                          DropdownMenuItem(
                            value: 'This Quarter',
                            child: Text('This Quarter'),
                          ),
                          DropdownMenuItem(
                            value: 'Last 30 Days',
                            child: Text('Last 30 Days'),
                          ),
                          DropdownMenuItem(
                            value: 'All Dates',
                            child: Text('All Dates'),
                          ),
                        ],
                        onChanged: (value) {
                          if (value == null) return;
                          setState(() => _selectedDate = value);
                          _loadArchives();
                        },
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: _isLoading
                      ? const Center(
                          child: CircularProgressIndicator(
                            color: AppColors.ustpNavy,
                          ),
                        )
                      : _error != null
                          ? _ArchiveLoadError(
                              error: _error!,
                              onRetry: _loadArchives,
                            )
                          : _documents.isEmpty
                              ? RefreshIndicator(
                                  onRefresh: _loadArchives,
                                  child: ListView(
                                    physics:
                                        const AlwaysScrollableScrollPhysics(),
                                    children: const [
                                      SizedBox(height: 50),
                                      EmptyState(
                                        title: 'No archives found',
                                        subtitle:
                                            'No documents match the selected filters.',
                                        icon: AppIcons.archive,
                                      ),
                                    ],
                                  ),
                                )
                              : RefreshIndicator(
                                  color: AppColors.ustpNavy,
                                  onRefresh: _loadArchives,
                                  child: ListView.builder(
                                    physics:
                                        const AlwaysScrollableScrollPhysics(),
                                    padding: const EdgeInsets.fromLTRB(
                                      12,
                                      0,
                                      12,
                                      16,
                                    ),
                                    itemCount: _documents.length,
                                    itemBuilder: (context, index) =>
                                        _ArchiveCard(
                                      document: _documents[index],
                                      isBusy: _busyDocuments
                                          .contains(_documents[index].id),
                                      onPreview: () =>
                                          _previewDetails(_documents[index]),
                                      onDownload: () =>
                                          _download(_documents[index]),
                                      onVerify: () =>
                                          _verifyChecksum(_documents[index]),
                                    ),
                                  ),
                                ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterDropdown<T> extends StatelessWidget {
  const _FilterDropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final String label;
  final T value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 38,
      padding: const EdgeInsets.symmetric(horizontal: 9),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        border: Border.all(color: const Color(0xFFE5EAF1)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Text(
            label,
            style: const TextStyle(color: AppColors.ustpMuted, fontSize: 10),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<T>(
                value: value,
                isExpanded: true,
                icon: const Icon(AppIcons.chevronDown, size: 15),
                style: const TextStyle(
                  color: AppColors.ustpDark,
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                ),
                items: items,
                onChanged: onChanged,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ArchiveCard extends StatelessWidget {
  const _ArchiveCard({
    required this.document,
    required this.isBusy,
    required this.onPreview,
    required this.onDownload,
    required this.onVerify,
  });

  final ArchiveDocumentModel document;
  final bool isBusy;
  final VoidCallback onPreview;
  final VoidCallback onDownload;
  final VoidCallback onVerify;

  @override
  Widget build(BuildContext context) {
    final isPdf = document.originalName.toLowerCase().endsWith('.pdf');
    final fileColor = isPdf ? AppColors.error : AppColors.info;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(11, 10, 11, 8),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        border: Border.all(color: const Color(0xFFE5EAF1)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 27,
                height: 29,
                decoration: BoxDecoration(
                  color: fileColor.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Icon(
                  isPdf ? AppIcons.document : AppIcons.spreadsheet,
                  color: fileColor,
                  size: 16,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      document.originalName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.ustpDark,
                        fontFamily: 'Poppins',
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      '${document.referenceStandard} · ${document.departmentCode}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.ustpMuted,
                        fontSize: 9,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            'Uploaded by ${document.uploadedBy}',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: AppColors.ustpDark, fontSize: 9),
          ),
          const SizedBox(height: 5),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'UPLOAD DATE',
                style: TextStyle(color: AppColors.ustpMuted, fontSize: 8),
              ),
              Text(
                DateFormat('MMM d, yyyy').format(document.createdAt),
                style: const TextStyle(
                  color: AppColors.ustpDark,
                  fontSize: 9,
                ),
              ),
            ],
          ),
          const Divider(height: 13, color: Color(0xFFE8EDF4)),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              _ArchiveAction(label: 'Preview', onPressed: onPreview),
              const _ArchiveActionDivider(),
              _ArchiveAction(
                label: isBusy ? 'Please wait…' : 'Download',
                onPressed: isBusy ? null : onDownload,
              ),
              if (!document.isVerified) ...[
                const _ArchiveActionDivider(),
                _ArchiveAction(
                  label: 'Verify',
                  onPressed: isBusy ? null : onVerify,
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _ArchiveAction extends StatelessWidget {
  const _ArchiveAction({required this.label, required this.onPressed});

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => TextButton(
        onPressed: onPressed,
        style: TextButton.styleFrom(
          minimumSize: Size.zero,
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        ),
        child: Text(
          label,
          style: const TextStyle(
            color: AppColors.ustpNavy,
            fontSize: 9,
            fontWeight: FontWeight.w600,
          ),
        ),
      );
}

class _ArchiveActionDivider extends StatelessWidget {
  const _ArchiveActionDivider();

  @override
  Widget build(BuildContext context) =>
      Container(height: 12, width: 1, color: const Color(0xFFCBD5E1));
}

class _ArchiveLoadError extends StatelessWidget {
  const _ArchiveLoadError({required this.error, required this.onRetry});

  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Could not load archives.\n$error',
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.ustpMuted),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(AppIcons.refresh, size: 16),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
}

String _formatSize(int bytes) {
  if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(0)} KB';
  return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
}
