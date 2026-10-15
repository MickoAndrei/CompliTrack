import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:socket_io_client/socket_io_client.dart' as IO;
import '../constants/api_constants.dart';
import 'secure_storage_service.dart';

class SocketService {
  SocketService._();

  static final instance = SocketService._();
  final _storage = SecureStorageService();
  final _notifications = StreamController<Map<String, dynamic>>.broadcast();
  IO.Socket? _socket;

  Stream<Map<String, dynamic>> get notifications => _notifications.stream;

  Future<void> connect() async {
    final token = await _storage.getToken();
    if (token == null || token.isEmpty) return;
    if (_socket?.connected == true) return;

    _socket?.dispose();
    final socket = IO.io(
      ApiConstants.realtimeUrl,
      IO.OptionBuilder()
          .setTransports(['websocket'])
          .setAuth({'token': token})
          .disableAutoConnect()
          .enableReconnection()
          .build(),
    );
    socket.onConnectError((error) {
      debugPrint('Realtime connection failed: $error');
    });
    socket.on('notification', (dynamic payload) {
      if (payload is Map) {
        _notifications.add(
          payload.map((key, value) => MapEntry(key.toString(), value)),
        );
      }
    });
    _socket = socket;
    socket.connect();
  }

  void disconnect() {
    _socket?.disconnect();
    _socket?.dispose();
    _socket = null;
  }
}