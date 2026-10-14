import 'package:flutter_test/flutter_test.dart';
import 'package:complitrack/core/utils/file_validator.dart';

void main() {
  group('FileValidator', () {
    test('accepts supported extensions within the size limit', () {
      expect(
        FileValidator.validate(
          extension: 'PDF',
          sizeInBytes: 1024,
        ),
        isNull,
      );
    });

    test('rejects unsupported extensions', () {
      expect(
        FileValidator.validate(
          extension: 'exe',
          sizeInBytes: 1024,
        ),
        isNotNull,
      );
    });

    test('rejects empty files', () {
      expect(
        FileValidator.validate(
          extension: 'png',
          sizeInBytes: 0,
        ),
        isNotNull,
      );
    });

    test('rejects files larger than 20 MB', () {
      expect(
        FileValidator.validate(
          extension: 'docx',
          sizeInBytes: FileValidator.maxFileSizeBytes + 1,
        ),
        isNotNull,
      );
    });
  });
}
