import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class ArchiveRepositoryScreen extends StatefulWidget {
  const ArchiveRepositoryScreen({super.key});

  @override
  State<ArchiveRepositoryScreen> createState() => _ArchiveRepositoryScreenState();
}

class _ArchiveRepositoryScreenState extends State<ArchiveRepositoryScreen> {
  final ArchiveRepository _repository = ArchiveRepository();

  String? _selectedDept;
  String? _selectedStandard;
  String? _selectedDate;
  bool _isLoading = true;
  List<ArchiveDocumentModel> _documents = [];

  @override
  void initState() {
    super.initState();
    _loadArchives();
  }

  Future<void> _loadArchives() async {
    setState(() => _isLoading = true);
    final results = await _repository.getArchiveDocuments(
      department: _selectedDept,
      referenceStandard: _selectedStandard,
      dateFilter: _selectedDate,
    );
    if (mounted) {
      setState(() {
        _documents = results;
        _isLoading = false;
      });
    }
  }

  Future<void> _verifyChecksum(ArchiveDocumentModel doc) async {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Verifying SHA-256 checksum for ${doc.originalName}...')),
    );
    final success = await _repository.verifyChecksum(doc.id);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: success ? AppColors.success : AppColors.error,
          content: Text(
            success
                ? 'SHA-256 Checksum Verified! Plaintext matches hash.'
                : 'Checksum mismatch detected!',
          ),
        ),
      );
      _loadArchives();
    }
  }

  void _showDeleteNotice(ArchiveDocumentModel doc) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Delete Archive'),
        content: Text(
          'Deleting encrypted archive "${doc.originalName}" is restricted on mobile devices for roles 0 & 1. Manage on Web to perform permanent deletions.',
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.ustpNavy,
              foregroundColor: AppColors.ustpWhite,
            ),
            onPressed: () => Navigator.pop(context),
            child: const Text('Got it'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        onNotificationTap: () {},
        onProfileTap: () {},
      ),
      body: Column(
        children: [
          // Header Filters Row Container
          Container(
            padding: const EdgeInsets.all(16),
            color: AppColors.ustpWhite,
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  // Department Filter Dropdown
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.ustpLight,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String?>(
                        value: _selectedDept,
                        hint: const Text(
                          'All Departments',
                          style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ustpDark,
                          ),
                        ),
                        icon: const Icon(AppIcons.chevronDown, size: 16, color: AppColors.ustpDark),
                        items: const [
                          DropdownMenuItem(value: null, child: Text('All Departments')),
                          DropdownMenuItem(value: 'IT', child: Text('IT')),
                          DropdownMenuItem(value: 'Envi Sci', child: Text('Envi Sci')),
                          DropdownMenuItem(value: 'Food Tech.', child: Text('Food Tech.')),
                          DropdownMenuItem(value: 'Agro - Bio .', child: Text('Agro - Bio .')),
                        ],
                        onChanged: (val) {
                          setState(() => _selectedDept = val);
                          _loadArchives();
                        },
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),

                  // Standards Filter Dropdown
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.ustpLight,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String?>(
                        value: _selectedStandard,
                        hint: const Text(
                          'All Standards',
                          style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ustpDark,
                          ),
                        ),
                        icon: const Icon(AppIcons.chevronDown, size: 16, color: AppColors.ustpDark),
                        items: const [
                          DropdownMenuItem(value: null, child: Text('All Standards')),
                          DropdownMenuItem(value: 'ISO 9001', child: Text('ISO 9001')),
                          DropdownMenuItem(value: 'ARTA 7', child: Text('ARTA 7-day rule')),
                          DropdownMenuItem(value: 'ARTA 3', child: Text('ARTA 3-day rule')),
                          DropdownMenuItem(value: 'ISO 14001', child: Text('ISO 14001')),
                        ],
                        onChanged: (val) {
                          setState(() => _selectedStandard = val);
                          _loadArchives();
                        },
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),

                  // Date Filter Dropdown
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.ustpLight,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.ustpMuted.withValues(alpha: 0.2)),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String?>(
                        value: _selectedDate,
                        hint: const Text(
                          'Any Date',
                          style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ustpDark,
                          ),
                        ),
                        icon: const Icon(AppIcons.chevronDown, size: 16, color: AppColors.ustpDark),
                        items: const [
                          DropdownMenuItem(value: null, child: Text('Any Date')),
                          DropdownMenuItem(value: 'Today', child: Text('Today')),
                          DropdownMenuItem(value: 'This Week', child: Text('This Week')),
                          DropdownMenuItem(value: 'This Month', child: Text('This Month')),
                        ],
                        onChanged: (val) {
                          setState(() => _selectedDate = val);
                          _loadArchives();
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Archive Cards List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.ustpNavy))
                : _documents.isEmpty
                    ? const EmptyState(
                        title: 'No archives found',
                        subtitle: 'No encrypted documents match your selected filters.',
                        icon: AppIcons.archive,
                      )
                    : RefreshIndicator(
                        color: AppColors.ustpNavy,
                        onRefresh: _loadArchives,
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _documents.length,
                          itemBuilder: (context, index) {
                            final doc = _documents[index];
                            return _buildArchiveCard(doc);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildArchiveCard(ArchiveDocumentModel doc) {
    final formattedDate = DateFormat('yyyy-MM-dd').format(doc.createdAt);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: File Icon + File Name & Reference (Left) | Verification Pill Badge (Right)
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFE0F2FE),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.insert_drive_file_outlined,
                  color: AppColors.info,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      doc.originalName,
                      style: const TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpDark,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${doc.departmentCode} · ${doc.referenceStandard}',
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 12,
                        color: AppColors.ustpMuted,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              // Verification Badge Pill
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: doc.isVerified ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      doc.isVerified ? Icons.check_circle_outline : Icons.warning_amber_rounded,
                      size: 12,
                      color: doc.isVerified ? AppColors.success : AppColors.error,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      doc.isVerified ? 'Verified' : 'Unverified',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: doc.isVerified ? AppColors.success : AppColors.error,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Upload Metadata Text
          Text(
            'Uploaded by ${doc.uploadedBy} · $formattedDate',
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 12,
              color: AppColors.ustpMuted,
            ),
          ),
          const SizedBox(height: 14),

          // Action Buttons Row: Preview | Download | Verify Checksum | Delete Trash Icon
          Row(
            children: [
              // Preview Button
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  side: BorderSide(color: AppColors.ustpMuted.withValues(alpha: 0.3)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Decrypting & opening preview for ${doc.originalName}...')),
                  );
                },
                icon: const Icon(Icons.remove_red_eye_outlined, size: 14, color: AppColors.ustpDark),
                label: const Text(
                  'Preview',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
              ),
              const SizedBox(width: 8),

              // Download Button
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  side: BorderSide(color: AppColors.ustpMuted.withValues(alpha: 0.3)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Downloading encrypted archive ${doc.originalName}...')),
                  );
                },
                icon: const Icon(Icons.download_outlined, size: 14, color: AppColors.ustpDark),
                label: const Text(
                  'Download',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
              ),
              const SizedBox(width: 8),

              // Verify Checksum Button (if Unverified)
              if (!doc.isVerified) ...[
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                    side: const BorderSide(color: AppColors.ustpNavy),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  onPressed: () => _verifyChecksum(doc),
                  icon: const Icon(Icons.verified_user_outlined, size: 14, color: AppColors.ustpNavy),
                  label: const Text(
                    'Verify Checksum',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: AppColors.ustpNavy,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
              ],

              const Spacer(),

              // Delete Trash Icon
              IconButton(
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
                onPressed: () => _showDeleteNotice(doc),
                icon: const Icon(
                  Icons.delete_outline_rounded,
                  color: AppColors.error,
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
