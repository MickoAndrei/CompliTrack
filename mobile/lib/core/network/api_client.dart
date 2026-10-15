import 'package:dio/dio.dart';
import '../constants/api_constants.dart';
import '../services/secure_storage_service.dart';

class ApiClient {
  late final Dio dio;
  final SecureStorageService _storageService;

  ApiClient({
    String? baseUrl,
    SecureStorageService? storageService,
  }) : _storageService = storageService ?? SecureStorageService() {
    dio = Dio(
      BaseOptions(
        baseUrl: baseUrl ?? ApiConstants.baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storageService.getToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException error, handler) async {
          if (error.response?.statusCode == 401) {
            await _storageService.clearAll();
          }
          return handler.next(error);
        },
      ),
    );
  }
}
