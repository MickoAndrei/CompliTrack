class FileValidator {
  FileValidator._();

  static const maxFileSizeBytes = 20 * 1024 * 1024;
  static const allowedExtensions = {
    'pdf',
    'docx',
    'jpg',
    'jpeg',
    'png',
  };

  static String? validate({
    required String? extension,
    required int sizeInBytes,
  }) {
    final normalizedExtension = extension?.toLowerCase();
    if (normalizedExtension == null ||
        !allowedExtensions.contains(normalizedExtension)) {
      return 'Choose a PDF, DOCX, JPG, JPEG, or PNG file.';
    }
    if (sizeInBytes <= 0) {
      return 'The selected file is empty.';
    }
    if (sizeInBytes > maxFileSizeBytes) {
      return 'Choose a file no larger than 20 MB.';
    }
    return null;
  }
}