import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';

class CurrentUserAvatar extends StatefulWidget {
  const CurrentUserAvatar({
    super.key,
    required this.name,
    this.radius = 18,
  });

  final String name;
  final double radius;

  @override
  State<CurrentUserAvatar> createState() => _CurrentUserAvatarState();
}

class _CurrentUserAvatarState extends State<CurrentUserAvatar> {
  late final Future<Uint8List?> _avatar;

  @override
  void initState() {
    super.initState();
    _avatar = _loadAvatar();
  }

  Future<Uint8List?> _loadAvatar() async {
    try {
      final response = await ApiClient().dio.get<List<int>>(
            '/api/v1/auth/avatar',
            options: Options(responseType: ResponseType.bytes),
          );
      final bytes = response.data;
      return bytes == null ? null : Uint8List.fromList(bytes);
    } catch (_) {
      return null;
    }
  }

  String _initials() {
    final parts = widget.name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return '?';
    return parts.length == 1
        ? parts.first.substring(0, 1).toUpperCase()
        : '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(2),
        decoration: const BoxDecoration(
          color: Color(0xFFFFC72C),
          shape: BoxShape.circle,
        ),
        child: ClipOval(
          child: SizedBox.square(
            dimension: widget.radius * 2,
            child: FutureBuilder<Uint8List?>(
              future: _avatar,
              builder: (context, snapshot) => snapshot.data == null
                  ? ColoredBox(
                      color: const Color(0xFFF1F5F9),
                      child: Center(
                        child: Text(
                          _initials(),
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF00205B),
                          ),
                        ),
                      ),
                    )
                  : Image.memory(snapshot.data!, fit: BoxFit.cover),
            ),
          ),
        ),
      );
}
