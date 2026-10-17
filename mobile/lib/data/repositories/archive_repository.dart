import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'dart:typed_data';
import '../../core/network/api_client.dart';
import '../../core/network/api_response.dart';
import '../models/archive_document_model.dart';

class ArchiveRepository {
  final Dio _dio;

  ArchiveRepository({Dio? dio}) : _dio = dio ?? ApiClient().dio;

  Future<List<ArchiveDocumentModel>> getArchiveDocuments({
    String? department,
    String? referenceStandard,
    String? dateFilter,
  }) async {
    final response = await _dio.get(
      '/api/v1/archives',
      queryParameters: {
        if (department != null &&
            department.isNotEmpty &&
            department != 'All Departments')
          'department': department,
        if (referenceStandard != null &&
            referenceStandard.isNotEmpty &&
            referenceStandard != 'All Standards' &&
            referenceStandard != 'ISO & ARTA')
          'referenceStandard': referenceStandard,
        if (dateFilter != null &&
            dateFilter.isNotEmpty &&
            dateFilter != 'Any Date')
          'dateFilter': dateFilter,
      },
    );
    return ApiResponse.list(response.data)
        .map(ArchiveDocumentModel.fromJson)
        .toList();
  }

  Future<bool> verifyChecksum(String fileId) async {
    final response = await _dio.get('/api/v1/archives/$fileId/verify-checksum');
    final body = ApiResponse.object(response.data);
    final verified = body['isVerified'];
    if (verified is! bool) {
      throw const FormatException(
          'The server returned an invalid checksum result.');
    }
    return verified;
  }

  Future<Uint8List> downloadArchive(String fileId) async {
    final response = await _dio.get<List<int>>(
      '/api/v1/archives/$fileId/download',
      options: Options(responseType: ResponseType.bytes),
    );
    final data = response.data;
    if (data == null) {
      throw const FormatException('The server returned an empty archive.');
    }
    return Uint8List.fromList(data);
  }

  Future<ArchiveDocumentModel> uploadEvidence({
    required String checkId,
    required String referenceStandard,
    required PlatformFile file,
    void Function(int sent, int total)? onSendProgress,
  }) async {
    final bytes = await file.readAsBytes();
    final upload = MultipartFile.fromBytes(bytes, filename: file.name);

    final response = await _dio.post(
      '/api/v1/archives/upload',
      data: FormData.fromMap({
        'file': upload,
        'checkId': checkId,
        'referenceStandard': referenceStandard,
      }),
      onSendProgress: onSendProgress,
    );
    return ArchiveDocumentModel.fromJson(ApiResponse.object(response.data));
  }
}
