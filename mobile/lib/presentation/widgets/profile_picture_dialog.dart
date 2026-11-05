import 'dart:ui';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../data/repositories/auth_repository.dart';
import '../providers/auth_provider.dart';

class ProfilePictureDialog extends StatefulWidget {
  const ProfilePictureDialog({super.key});

  static Future<bool?> show(BuildContext context) {
    return showDialog<bool>(
      context: context,
      barrierColor: const Color(0x660F172A),
      builder: (_) => const ProfilePictureDialog(),
    );
  }

  @override
  State<ProfilePictureDialog> createState() => _ProfilePictureDialogState();
}

class _ProfilePictureDialogState extends State<ProfilePictureDialog> {
  static const _navy = Color(0xFF0F172A);
  static const _gold = Color(0xFFFFC72C);
  static const _maxFileSize = 2 * 1024 * 1024;
  final _authRepository = AuthRepository();
  Uint8List? _photoBytes;
  String? _fileName;
  String? _errorMessage;
  bool _isSaving = false;

  Future<void> _choosePhoto() async {
    if (_isSaving) return;
    setState(() => _errorMessage = null);

    try {
      final file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp'],
      );
      if (file == null || !mounted) return;

      final extension = file.extension?.toLowerCase();
      if (!const {'jpg', 'jpeg', 'png', 'webp'}.contains(extension)) {
        setState(() => _errorMessage = 'Choose a JPG, PNG, or WebP image.');
        return;
      }

      final bytes = await file.readAsBytes();
      if (!mounted) return;
      if (bytes.isEmpty) {
        setState(() => _errorMessage = 'The selected photo is empty.');
        return;
      }
      if (bytes.length > _maxFileSize) {
        setState(() => _errorMessage = 'Choose an image up to 2 MB.');
        return;
      }
      setState(() {
        _photoBytes = bytes;
        _fileName = file.name;
      });
    } on PlatformException catch (error) {
      if (mounted) {
        setState(
            () => _errorMessage = error.message ?? 'Unable to choose a photo.');
      }
    } on Exception catch (error) {
      if (mounted) {
        setState(
            () => _errorMessage = 'Unable to read the selected photo: $error');
      }
    }
  }

  Future<void> _savePhoto() async {
    final photoBytes = _photoBytes;
    final fileName = _fileName;
    if (photoBytes == null || fileName == null) {
      setState(() => _errorMessage = 'Choose a photo before saving.');
      return;
    }

    setState(() {
      _isSaving = true;
      _errorMessage = null;
    });
    try {
      await _authRepository.uploadAvatar(bytes: photoBytes, filename: fileName);
      if (!mounted) return;
      context.read<AuthProvider>().refreshAvatar();
      Navigator.of(context).pop(true);
    } on DioException catch (error) {
      if (mounted) {
        final response = error.response?.data;
        final message = response is Map && response['message'] is String
            ? response['message'] as String
            : 'Could not save your photo. Please try again.';
        setState(() => _errorMessage = message);
      }
    } on Exception catch (error) {
      if (mounted) {
        setState(() => _errorMessage = 'Could not save your photo: $error');
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 5, sigmaY: 5),
      child: Dialog(
        insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
        backgroundColor: Colors.transparent,
        elevation: 0,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 360),
          child: Container(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xFFD8E0E9), width: 2),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x260F172A),
                  blurRadius: 28,
                  offset: Offset(0, 12),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFCBD5E1),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Profile Picture',
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: _navy,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Choose a JPG, PNG or WebP, up to 2 MB.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 13,
                    color: Color(0xFF0F172A),
                  ),
                ),
                const SizedBox(height: 28),
                Container(
                  width: 36,
                  height: 36,
                  padding: const EdgeInsets.all(2),
                  decoration: const BoxDecoration(
                    color: _gold,
                    shape: BoxShape.circle,
                  ),
                  child: ClipOval(
                    child: _photoBytes == null
                        ? const ColoredBox(
                            color: Color(0xFFF1F5F9),
                            child: Icon(
                              Icons.person,
                              size: 25,
                              color: Color(0xFFCBD5E1),
                            ),
                          )
                        : Image.memory(_photoBytes!, fit: BoxFit.cover),
                  ),
                ),
                const SizedBox(height: 32),
                Material(
                  color: const Color(0xFFF3F6FA),
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    onTap: _isSaving ? null : _choosePhoto,
                    borderRadius: BorderRadius.circular(14),
                    child: SizedBox(
                      width: double.infinity,
                      height: 104,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(
                            Icons.file_upload_outlined,
                            size: 34,
                            color: Color(0xFF9CA3AF),
                          ),
                          const SizedBox(height: 12),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            child: Text(
                              _fileName ?? 'Choose a photo or drop it here',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 13,
                                color: _navy,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                if (_errorMessage != null) ...[
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      _errorMessage!,
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 12,
                        color: Color(0xFFB91C1C),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 44,
                        child: OutlinedButton(
                          onPressed: _isSaving
                              ? null
                              : () => Navigator.of(context).pop(false),
                          style: OutlinedButton.styleFrom(
                            backgroundColor: const Color(0xFFF1F5F9),
                            foregroundColor: _navy,
                            side: const BorderSide(color: Color(0xFFDCE4ED)),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          child: const Text(
                            'Cancel',
                            style: TextStyle(
                              fontFamily: 'Inter',
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: SizedBox(
                        height: 44,
                        child: ElevatedButton(
                          onPressed: _isSaving ? null : _savePhoto,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: _gold,
                            foregroundColor: _navy,
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          child: _isSaving
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: _navy,
                                  ),
                                )
                              : const Text(
                                  'Save Photo',
                                  style: TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
