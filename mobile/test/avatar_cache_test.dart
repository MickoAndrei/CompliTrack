import 'dart:async';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import '../lib/core/services/avatar_cache.dart';

void main() {
  test('shares concurrent requests and reuses bytes until expiry', () async {
    var now = DateTime(2026);
    var requests = 0;
    final completer = Completer<Uint8List?>();
    final cache = AvatarCache(
        now: () => now,
        load: () {
          requests++;
          return completer.future;
        });
    final first = cache.load();
    expect(identical(first, cache.load()), isTrue);
    completer.complete(Uint8List.fromList([1, 2, 3]));
    expect(await first, [1, 2, 3]);
    expect(await cache.load(), [1, 2, 3]);
    expect(requests, 1);
    now = now.add(const Duration(hours: 1));
    await cache.load();
    expect(requests, 2);
  });

  test('clearing for an upload or account switch discards old in-flight data',
      () async {
    final pending = <Completer<Uint8List?>>[];
    final cache = AvatarCache(load: () {
      final request = Completer<Uint8List?>();
      pending.add(request);
      return request.future;
    });
    final firstAccount = cache.load();
    cache.clear();
    final secondAccount = cache.load();
    pending[1].complete(Uint8List.fromList([2]));
    pending[0].complete(Uint8List.fromList([1]));
    expect(await firstAccount, [1]);
    expect(await secondAccount, [2]);
    expect(await cache.load(), [2]);
    expect(pending.length, 2);
  });

  test('caches missing photos and retries network failures after 30 seconds',
      () async {
    var now = DateTime(2026);
    var requests = 0;
    final cache = AvatarCache(
        now: () => now,
        load: () async {
          requests++;
          if (requests == 1) throw Exception('offline');
          return null;
        });
    expect(await cache.load(), isNull);
    expect(await cache.load(), isNull);
    expect(requests, 1);
    now = now.add(const Duration(seconds: 30));
    expect(await cache.load(), isNull);
    expect(await cache.load(), isNull);
    expect(requests, 2);
  });
}
