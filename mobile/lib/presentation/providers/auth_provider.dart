import 'package:flutter/material.dart';
import '../../core/utils/role_helper.dart';
import '../../data/models/user_model.dart';
import '../../data/repositories/auth_repository.dart';

class AuthProvider extends ChangeNotifier {
  final AuthRepository _authRepository;

  AuthProvider({AuthRepository? authRepository})
      : _authRepository = authRepository ?? AuthRepository() {
    _initSession();
  }

  bool _isLoggedIn = false;
  int _roleId = RoleHelper.roleSuperAdmin;
  UserModel? _currentUser;
  bool _isInitializing = true;

  bool get isLoggedIn => _isLoggedIn;
  int get roleId => _roleId;
  UserModel? get currentUser => _currentUser;
  bool get isInitializing => _isInitializing;

  Future<void> _initSession() async {
    final session = await _authRepository.getSavedSession();
    if (session.token != null && session.roleId != null) {
      _isLoggedIn = true;
      _roleId = session.roleId!;
    } else {
      _isLoggedIn = true;
      _roleId = RoleHelper.roleSuperAdmin;
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
      notifyListeners();
      return true;
    }
    return false;
  }

  Future<void> logout() async {
    await _authRepository.logout();
    _isLoggedIn = false;
    _currentUser = null;
    notifyListeners();
  }
}
