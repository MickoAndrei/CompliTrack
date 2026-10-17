import 'package:file_picker/file_picker.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../core/utils/file_validator.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../../data/repositories/compliance_repository.dart';

class UploadEvidenceScreen extends StatefulWidget {
  const UploadEvidenceScreen({
    super.key,
    this.checkId,
    this.checkCode,
    this.referenceStandard,
  });

  final String? checkId;
  final String? checkCode;
  final String? referenceStandard;

  static Future<void> show(
    BuildContext context, {
    required String checkId,
    required String checkCode,
    required String referenceStandard,
  }) {
    return showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => Dialog(
        insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
        backgroundColor: Colors.transparent,
        child: UploadEvidenceScreen(
          checkId: checkId,
          checkCode: checkCode,
          referenceStandard: referenceStandard,
        ),
      ),
    );
  }

  @override
  State<UploadEvidenceScreen> createState() => _UploadEvidenceScreenState();
}

class _UploadEvidenceScreenState extends State<UploadEvidenceScreen> {
  PlatformFile? _selectedFile;
  String? _errorMessage;
  bool _isPickingFile = false;
  bool _isUploading = false;
  double? _uploadProgress;
  String? _selectedCheckId;
  late Future<List<ComplianceCheckModel>> _eligibleChecks;
  final _archiveRepository = ArchiveRepository();

  @override
  void initState() {
    super.initState();
    _selectedCheckId = widget.checkId;
    _eligibleChecks = ComplianceRepository()
        .getChecks()
        .then((checks) => checks
            .where((check) => check.result == 'non_compliant')
            .toList());
  }
  Future<void> _pickFile() async {
    setState(() {
      _isPickingFile = true;
      _errorMessage = null;
    });
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: FileValidator.allowedExtensions.toList(),
      );
      if (!mounted) return;
      if (result.isEmpty) {
        setState(() => _isPickingFile = false);
        return;
      }

      final file = result.single;
      final fileSize = file.lengthSync() ?? 0;
      final validationError = FileValidator.validate(
        extension: file.extension,
        sizeInBytes: fileSize,
      );
      setState(() {
        _isPickingFile = false;
        _selectedFile = validationError == null ? file : null;
        _errorMessage = validationError;
      });
    } on PlatformException catch (error) {
      if (!mounted) return;
      setState(() {
        _isPickingFile = false;
        _errorMessage = error.message ?? 'Unable to open the file picker.';
      });
    }
  }

  Future<void> _startUpload() async {
    if (_selectedFile == null) {
      setState(() => _errorMessage = 'Choose a valid evidence file first.');
      return;
    }
    List<ComplianceCheckModel> checks = const [];
    if (widget.checkId == null) {
      try {
        checks = await _eligibleChecks;
      } on DioException catch (error) {
        final body = error.response?.data;
        final message = body is Map && body['message'] is String
            ? body['message'] as String
            : 'Unable to load checks for evidence upload.';
        if (mounted) setState(() => _errorMessage = message);
        return;
      } on FormatException catch (error) {
        if (mounted) setState(() => _errorMessage = error.message);
        return;
      }
    }
    final matches = checks.where((check) => check.id == _selectedCheckId);
    final selected = matches.isEmpty ? null : matches.first;
    final checkCode = selected?.checkCode ?? widget.checkCode;
    final referenceStandard = selected?.referenceStandard ?? widget.referenceStandard;
    if (_selectedCheckId == null || checkCode == null || referenceStandard == null) {
      setState(() => _errorMessage = 'Select a non-compliant check before uploading evidence.');
      return;
    }

    setState(() {
      _isUploading = true;
      _errorMessage = null;
      _uploadProgress = 0;
    });
    try {
      await _archiveRepository.uploadEvidence(
        checkId: _selectedCheckId!,
        referenceStandard: referenceStandard,
        file: _selectedFile!,
        onSendProgress: (sent, total) {
          if (mounted && total > 0) {
            setState(() => _uploadProgress = sent / total);
          }
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Evidence uploaded and encrypted successfully.')),
      );
      Navigator.pop(context);
    } on DioException catch (error) {
      final body = error.response?.data;
      final message = body is Map && body['message'] is String
          ? body['message'] as String
          : 'The evidence upload failed. Please try again.';
      if (mounted) setState(() => _errorMessage = message);
    } on FormatException catch (error) {
      if (mounted) setState(() => _errorMessage = error.message);
    } finally {
      if (mounted) setState(() => _isUploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: AppColors.ustpMuted.withValues(alpha: 0.2),
        ),
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'New Evidence Submission',
              style: TextStyle(
                fontFamily: 'Poppins',
                fontSize: 13,
                fontWeight: FontWeight.bold,
                color: AppColors.ustpNavy,
              ),
            ),
            const SizedBox(height: 2),
            const Text(
              'Documentary proof to resolve open actions',
              style: TextStyle(fontSize: 9, color: AppColors.ustpMuted),
            ),
            const SizedBox(height: 12),
            const _FieldTitle('Related Check (non-compliant only)'),
            const SizedBox(height: 4),
            if (widget.checkId == null)
              FutureBuilder<List<ComplianceCheckModel>>(
                future: _eligibleChecks,
                builder: (context, snapshot) {
                  if (snapshot.hasError) {
                    return Text('Could not load checks: ${snapshot.error}');
                  }
                  if (!snapshot.hasData) {
                    return const LinearProgressIndicator();
                  }
                  final checks = snapshot.data!;
                  return DropdownButtonFormField<String>(
                    initialValue: _selectedCheckId,
                    decoration: const InputDecoration(
                      hintText: 'Select a non-compliant check',
                      border: OutlineInputBorder(),
                    ),
                    items: checks.map((check) => DropdownMenuItem(
                      value: check.id,
                      child: Text('${check.checkCode} — ${check.referenceStandard}'),
                    )).toList(),
                    onChanged: _isUploading
                        ? null
                        : (value) => setState(() => _selectedCheckId = value),
                  );
                },
              )
            else
              _ReadOnlyValue(
                value: '${widget.checkCode} — ${widget.referenceStandard}',
              ),
            const SizedBox(height: 10),
            const _FieldTitle('Reference Standard (auto-filled)'),
            const SizedBox(height: 4),
            FutureBuilder<List<ComplianceCheckModel>>(
              future: _eligibleChecks,
              builder: (context, snapshot) {
                final checks = snapshot.data;
                final matches = checks?.where((check) => check.id == _selectedCheckId);
                final selected = matches == null || matches.isEmpty ? null : matches.first;
                return _ReadOnlyValue(
                  value: selected?.referenceStandard ?? widget.referenceStandard ?? '',
                  muted: true,
                );
              },
            ),
            const SizedBox(height: 10),
            InkWell(
              onTap: _isPickingFile || _isUploading ? null : _pickFile,
              borderRadius: BorderRadius.circular(8),
              child: Container(
                height: 76,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: AppColors.ustpLight,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: AppColors.ustpMuted,
                    style: BorderStyle.solid,
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (_isPickingFile)
                      const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.ustpNavy,
                        ),
                      )
                    else
                      const Icon(
                        AppIcons.upload,
                        color: AppColors.ustpNavy,
                        size: 20,
                      ),
                    const SizedBox(height: 5),
                    Text(
                      _selectedFile?.name ?? 'Drag & drop files or click browse',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpDark,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _selectedFile == null
                          ? 'PDF, DOCX, JPG, PNG · Max 20MB'
                          : '${(((_selectedFile?.lengthSync() ?? 0) / (1024 * 1024))).toStringAsFixed(2)} MB · Tap to replace',
                      style: const TextStyle(
                        fontSize: 8,
                        color: AppColors.ustpMuted,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (_errorMessage != null) ...[
              const SizedBox(height: 7),
              Text(
                _errorMessage!,
                style: const TextStyle(
                  fontSize: 9,
                  color: AppColors.error,
                ),
              ),
            ],
            if (_isUploading && _uploadProgress != null) ...[
              const SizedBox(height: 8),
              LinearProgressIndicator(value: _uploadProgress),
            ],
            const SizedBox(height: 9),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              decoration: BoxDecoration(
                color: AppColors.info.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(5),
                border: Border.all(
                  color: AppColors.info.withValues(alpha: 0.25),
                ),
              ),
              child: const Row(
                children: [
                  Icon(AppIcons.lock, size: 13, color: AppColors.ustpNavy),
                  SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'File will be encrypted with AES-256-GCM',
                      style: TextStyle(
                        fontSize: 8,
                        color: AppColors.ustpNavy,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.ustpMuted,
                    minimumSize: const Size(60, 34),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(7),
                      side: BorderSide(
                        color: AppColors.ustpMuted.withValues(alpha: 0.2),
                      ),
                    ),
                  ),
                  child: const Text('Cancel', style: TextStyle(fontSize: 10)),
                ),
                const SizedBox(width: 7),
                ElevatedButton(
                  onPressed: _isUploading ? null : _startUpload,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.ustpGold,
                    foregroundColor: AppColors.ustpDark,
                    elevation: 0,
                    minimumSize: const Size(90, 34),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(7),
                    ),
                  ),
                  child: Text(
                    _isUploading ? 'Uploading…' : 'Start Upload',
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FieldTitle extends StatelessWidget {
  const _FieldTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 9,
        fontWeight: FontWeight.w600,
        color: AppColors.ustpDark,
      ),
    );
  }
}

class _ReadOnlyValue extends StatelessWidget {
  const _ReadOnlyValue({required this.value, this.muted = false});

  final String value;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
      decoration: BoxDecoration(
        color: muted ? AppColors.ustpLight : AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(
          color: AppColors.ustpMuted.withValues(alpha: 0.2),
        ),
      ),
      child: Text(
        value,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          fontSize: 9,
          color: muted ? AppColors.ustpMuted : AppColors.ustpDark,
        ),
      ),
    );
  }
}