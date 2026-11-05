import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/models/department_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../../data/repositories/department_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';
import '../admin/admin_notifications_screen.dart';

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
  String _selectedStandard = 'All Standards';
  String _selectedDate = 'Any Date';
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
          department: _selectedDept == 'All Departments' ? null : _selectedDept,
          referenceStandard:
              _selectedStandard == 'All Standards' ? null : _selectedStandard,
          dateFilter: _selectedDate == 'Any Date' ? 'All Dates' : _selectedDate,
        ),
        _departmentsRepository.getDepartments(),
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
      final savedPath = await FilePicker.saveFile(
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
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(doc.originalName),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Document Code: ${doc.fileCode}'),
            const SizedBox(height: 4),
            Text('Department: ${doc.departmentCode}'),
            const SizedBox(height: 4),
            Text('Standard: ${doc.referenceStandard}'),
            const SizedBox(height: 4),
            Text('Uploaded by: ${doc.uploadedBy}'),
            const SizedBox(height: 4),
            Text('File size: ${_formatSize(doc.fileSize)}'),
            const SizedBox(height: 12),
            const Text(
              'USTP QMS verified official document record.',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
            ),
          ],
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.ustpNavy,
              foregroundColor: Colors.white,
            ),
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
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: UstpAppBar(
        onNotificationTap: () => AdminNotificationsScreen.showOverlay(context),
      ),
      body: Column(
        children: [
          // Filter Header Row matching mockup: All Departments, All Standards, Any Date (fitted 1-to-1)
          Container(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
            color: const Color(0xFFF8F9FA),
            child: Row(
              children: [
                // All Departments Dropdown
                Expanded(
                  child: PopupMenuButton<String>(
                    initialValue: _selectedDept,
                    onSelected: (val) {
                      setState(() => _selectedDept = val);
                      _loadArchives();
                    },
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    color: Colors.white,
                    elevation: 4,
                    itemBuilder: (context) => [
                      const PopupMenuItem(
                        value: 'All Departments',
                        child: Text('All Departments',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                      for (final dept in _departments)
                        PopupMenuItem(
                          value: dept.departmentCode,
                          child: Text(dept.departmentName,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 13,
                                  color: Color(0xFF0F172A))),
                        ),
                    ],
                    child: Container(
                      height: 38,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 8),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              _selectedDept,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 11.5,
                                fontWeight: FontWeight.w500,
                                color: Color(0xFF1E293B),
                              ),
                            ),
                          ),
                          const Icon(Icons.keyboard_arrow_down_rounded,
                              size: 18, color: Color(0xFF64748B)),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 6),

                // All Standards Dropdown
                Expanded(
                  child: PopupMenuButton<String>(
                    initialValue: _selectedStandard,
                    onSelected: (val) {
                      setState(() => _selectedStandard = val);
                      _loadArchives();
                    },
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    color: Colors.white,
                    elevation: 4,
                    itemBuilder: (context) => const [
                      PopupMenuItem(
                        value: 'All Standards',
                        child: Text('All Standards',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                      PopupMenuItem(
                        value: 'ISO 9001',
                        child: Text('ISO 9001',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                      PopupMenuItem(
                        value: 'ARTA 7',
                        child: Text('ARTA 7',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                      PopupMenuItem(
                        value: 'ARTA 3',
                        child: Text('ARTA 3',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                      PopupMenuItem(
                        value: 'ISO 14001',
                        child: Text('ISO 14001',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                    ],
                    child: Container(
                      height: 38,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 8),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              _selectedStandard,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 11.5,
                                fontWeight: FontWeight.w500,
                                color: Color(0xFF1E293B),
                              ),
                            ),
                          ),
                          const Icon(Icons.keyboard_arrow_down_rounded,
                              size: 18, color: Color(0xFF64748B)),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 6),

                // Any Date Dropdown
                Expanded(
                  child: PopupMenuButton<String>(
                    initialValue: _selectedDate,
                    onSelected: (val) {
                      setState(() => _selectedDate = val);
                      _loadArchives();
                    },
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    color: Colors.white,
                    elevation: 4,
                    itemBuilder: (context) => const [
                      PopupMenuItem(
                        value: 'Any Date',
                        child: Text('Any Date',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                      PopupMenuItem(
                        value: 'This Quarter',
                        child: Text('This Quarter',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                      PopupMenuItem(
                        value: 'Last 30 Days',
                        child: Text('Last 30 Days',
                            style: TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: Color(0xFF0F172A))),
                      ),
                    ],
                    child: Container(
                      height: 38,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 8),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              _selectedDate,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 11.5,
                                fontWeight: FontWeight.w500,
                                color: Color(0xFF1E293B),
                              ),
                            ),
                          ),
                          const Icon(Icons.keyboard_arrow_down_rounded,
                              size: 18, color: Color(0xFF64748B)),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Archive Cards List
          Expanded(
            child: _isLoading
                ? const Center(
                    child:
                        CircularProgressIndicator(color: AppColors.ustpNavy),
                  )
                : _error != null
                    ? _ArchiveLoadError(
                        error: _error!,
                        onRetry: _loadArchives,
                      )
                    : _documents.isEmpty
                        ? RefreshIndicator(
                            color: AppColors.ustpNavy,
                            onRefresh: _loadArchives,
                            child: ListView(
                              physics:
                                  const AlwaysScrollableScrollPhysics(),
                              children: const [
                                SizedBox(height: 80),
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
                              padding:
                                  const EdgeInsets.fromLTRB(16, 0, 16, 16),
                              itemCount: _documents.length,
                              itemBuilder: (context, index) {
                                final doc = _documents[index];
                                return _ArchiveCard(
                                  document: doc,
                                  isBusy: _busyDocuments.contains(doc.id),
                                  onPreview: () => _previewDetails(doc),
                                  onDownload: () => _download(doc),
                                  onVerify: () => _verifyChecksum(doc),
                                );
                              },
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
    final isImage = document.originalName.toLowerCase().endsWith('.jpg') ||
        document.originalName.toLowerCase().endsWith('.png');
    final fileColor = isPdf ? const Color(0xFF2563EB) : (isImage ? const Color(0xFF10B981) : const Color(0xFFF59E0B));

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: File Icon + Filename + Verified/Unverified Badge
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: fileColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  isImage ? Icons.image_rounded : Icons.description_rounded,
                  color: fileColor,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      document.originalName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xFF0F172A),
                        fontFamily: 'Inter',
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${document.departmentCode} · ${document.referenceStandard}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xFF64748B),
                        fontFamily: 'Inter',
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              // Verified / Unverified Badge
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: document.isVerified
                      ? const Color(0xFFECFDF5)
                      : const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      document.isVerified ? Icons.check : Icons.warning_rounded,
                      size: 12,
                      color: document.isVerified
                          ? const Color(0xFF10B981)
                          : const Color(0xFFEF4444),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      document.isVerified ? 'Verified' : 'Unverified',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: document.isVerified
                            ? const Color(0xFF10B981)
                            : const Color(0xFFEF4444),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Uploaded by metadata
          Text(
            'Uploaded by ${document.uploadedBy} · ${DateFormat('yyyy-MM-dd').format(document.createdAt)}',
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 12,
              color: Color(0xFF94A3B8),
            ),
          ),

          // Divider Line
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Divider(
              height: 1,
              thickness: 1,
              color: Color(0xFFF1F5F9),
            ),
          ),

          // Bottom Actions Row: Preview, Download, Verify (Left) + Delete (Right)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Preview Button
                  _ActionButton(
                    icon: Icons.visibility_outlined,
                    label: 'Preview',
                    onPressed: onPreview,
                  ),
                  const SizedBox(width: 8),
                  // Download Button
                  _ActionButton(
                    icon: Icons.download_rounded,
                    label: isBusy ? 'Wait…' : 'Download',
                    onPressed: isBusy ? null : onDownload,
                  ),
                  if (!document.isVerified) ...[
                    const SizedBox(width: 8),
                    // Verify Checksum Button
                    _ActionButton(
                      icon: Icons.security_rounded,
                      label: 'Verify',
                      onPressed: isBusy ? null : onVerify,
                    ),
                  ],
                ],
              ),
              // Delete / Trash Icon Button
              IconButton(
                constraints: const BoxConstraints(),
                padding: EdgeInsets.zero,
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                        content: Text('Admin/Super Admin archive items are read-only.')),
                  );
                },
                icon: const Icon(
                  Icons.delete_outline_rounded,
                  color: Color(0xFFEF4444),
                  size: 20,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFF1F5F9),
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 14, color: const Color(0xFF0F172A)),
              const SizedBox(width: 4),
              Text(
                label,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0F172A),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
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
                style: const TextStyle(color: Color(0xFF64748B)),
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
