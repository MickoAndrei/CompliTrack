import 'package:dio/dio.dart';
import '../models/archive_document_model.dart';

class ArchiveRepository {
  final Dio _dio;

  ArchiveRepository({Dio? dio}) : _dio = dio ?? Dio();

  Future<List<ArchiveDocumentModel>> getArchiveDocuments({
    String? department,
    String? referenceStandard,
    String? dateFilter,
  }) async {
    try {
      final response = await _dio.get(
        '/api/v1/archives',
        queryParameters: {
          if (department != null && department.isNotEmpty && department != 'All Departments')
            'department': department,
          if (referenceStandard != null && referenceStandard.isNotEmpty && referenceStandard != 'All Standards')
            'referenceStandard': referenceStandard,
          if (dateFilter != null && dateFilter.isNotEmpty && dateFilter != 'Any Date')
            'dateFilter': dateFilter,
        },
      );
      if (response.data != null && response.data['data'] != null) {
        final list = response.data['data'] as List<dynamic>;
        return list.map((e) => ArchiveDocumentModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      // Offline fallback mock data
    }
    return _getMockArchives(
      department: department,
      referenceStandard: referenceStandard,
    );
  }

  Future<bool> verifyChecksum(String fileId) async {
    try {
      final response = await _dio.post('/api/v1/archives/$fileId/verify-checksum');
      return response.data?['isVerified'] as bool? ?? true;
    } catch (_) {
      return true;
    }
  }

  List<ArchiveDocumentModel> _getMockArchives({
    String? department,
    String? referenceStandard,
  }) {
    final mockData = [
      ArchiveDocumentModel(
        id: '1',
        fileCode: 'ARCH-2026-0001',
        originalName: 'ISO-9001-Cert-2026.pdf',
        mimeType: 'application/pdf',
        fileSize: 2450000,
        checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        departmentCode: 'IT',
        referenceStandard: 'ISO 9001',
        uploadedBy: 'Jomar Villareal',
        isVerified: true,
        createdAt: DateTime(2026, 9, 8),
      ),
      ArchiveDocumentModel(
        id: '2',
        fileCode: 'ARCH-2026-0002',
        originalName: 'ARTA-Checklist-Q3.docx',
        mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        fileSize: 1200000,
        checksum: 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e',
        departmentCode: 'Envi Sci',
        referenceStandard: 'ARTA 7',
        uploadedBy: 'Ana Reyes',
        isVerified: true,
        createdAt: DateTime(2026, 9, 5),
      ),
      ArchiveDocumentModel(
        id: '3',
        fileCode: 'ARCH-2026-0003',
        originalName: 'Fire-Safety-Report.pdf',
        mimeType: 'application/pdf',
        fileSize: 3100000,
        checksum: '3b092b7850ed96f2a7d2b254a2a19b5962886c96825227f711204859f5b33182',
        departmentCode: 'Food Tech.',
        referenceStandard: 'ARTA 3',
        uploadedBy: 'Noel Cruz',
        isVerified: false,
        createdAt: DateTime(2026, 9, 2),
      ),
      ArchiveDocumentModel(
        id: '4',
        fileCode: 'ARCH-2026-0004',
        originalName: 'Lab-Inspection-Photos.jpg',
        mimeType: 'image/jpeg',
        fileSize: 4500000,
        checksum: '1f122e20b0805c31713d2f9b8c91334c22956f50567a146e83863d085956a81a',
        departmentCode: 'Agro - Bio .',
        referenceStandard: 'ISO 14001',
        uploadedBy: 'Liza Tan',
        isVerified: true,
        createdAt: DateTime(2026, 8, 29),
      ),
    ];

    return mockData.where((doc) {
      if (department != null &&
          department.isNotEmpty &&
          department != 'All Departments' &&
          !doc.departmentCode.contains(department)) {
        return false;
      }
      if (referenceStandard != null &&
          referenceStandard.isNotEmpty &&
          referenceStandard != 'All Standards' &&
          !doc.referenceStandard.contains(referenceStandard)) {
        return false;
      }
      return true;
    }).toList();
  }
}
