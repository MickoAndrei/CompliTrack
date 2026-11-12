import 'dart:typed_data';

/// Shares both in-flight requests and image bytes within one account session.
class AvatarCache {
  AvatarCache({
    required Future<Uint8List?> Function() load,
    DateTime Function()? now,
  })  : _load = load,
        _now = now ?? DateTime.now;

  final Future<Uint8List?> Function() _load;
  final DateTime Function() _now;
  Future<Uint8List?>? _pending;
  DateTime? _expiresAt;

  Future<Uint8List?> load() {
    if (_pending != null && _now().isBefore(_expiresAt!)) {
      return _pending!;
    }
    _expiresAt = _now().add(const Duration(hours: 1));
    late final Future<Uint8List?> request;
    request =
        _load().then<Uint8List?>((bytes) => bytes, onError: (Object error) {
      // Briefly cache failures to avoid a request storm while offline.
      if (identical(_pending, request)) {
        _expiresAt = _now().add(const Duration(seconds: 30));
      }
      return null;
    });
    _pending = request;
    return request;
  }

  void clear() {
    _pending = null;
    _expiresAt = null;
  }
}
