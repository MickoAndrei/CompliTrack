import 'dart:async';

import 'package:flutter/material.dart';
import '../../core/services/socket_service.dart';
import '../../data/models/user_model.dart';
import '../../data/repositories/auth_repository.dart';

class AuthProvider extends ChangeNotifier {
  final AuthRepository _authRepository;

  AuthProvider({AuthRepository? authRepository})
      : _authRepository = authRepository ?? AuthRepository() {
    _initSession();
  }

  bool _isLoggedIn = false;
  int _roleId = -1;
  UserModel? _currentUser;
  bool _isInitializing = true;

  bool get isLoggedIn => _isLoggedIn;
  int get roleId => _roleId;
  UserModel? get currentUser => _currentUser;
  bool get isInitializing => _isInitializing;

  Future<void> _initSession() async {
    try {
      final session = await _authRepository.getSavedSession();
      _isLoggedIn = session.token != null && session.roleId != null;
      if (_isLoggedIn) {
        _roleId = session.roleId!;
        _currentUser = session.user;
        unawaited(SocketService.instance.connect());
      }
    } catch (error, stackTrace) {
      FlutterError.reportError(FlutterErrorDetails(
        exception: error,
        stack: stackTrace,
        library: 'CompliTrack authentication',
      ));
      _isLoggedIn = false;
      _roleId = -1;
      _currentUser = null;
    }
    _isInitializing = false;
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    final result = await _authRepository.login(email: email, password: password);
    if (result != null) {
      _isLoggedIn = true;
      _roleId = result.user.roleId;
      _currentUser = result.user;
      unawaited(SocketService.instance.connect());
      notifyListeners();
      return true;
    }
    return false;
  }

  Future<void> logout() async {
    try {
      await _authRepository.logout();
    } finally {
      _isLoggedIn = false;
      _roleId = -1;
      _currentUser = null;
      SocketService.instance.disconnect();
      notifyListeners();
    }
  }
}
