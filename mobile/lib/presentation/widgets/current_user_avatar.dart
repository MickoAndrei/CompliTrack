import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';

class CurrentUserAvatar extends StatefulWidget {
  const CurrentUserAvatar({
    super.key,
    this.radius = 18,
    this.avatarVersion = 0,
  });

  final double radius;
  final int avatarVersion;

  @override
  State<CurrentUserAvatar> createState() => _CurrentUserAvatarState();
}

class _CurrentUserAvatarState extends State<CurrentUserAvatar> {
  late Future<Uint8List?> _avatar;

  @override
  void initState() {
    super.initState();
    _avatar = _loadAvatar();
  }

  @override
  void didUpdateWidget(covariant CurrentUserAvatar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.avatarVersion != widget.avatarVersion) {
      _avatar = _loadAvatar();
    }
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
                      child: Icon(
                        Icons.person,
                        size: widget.radius * 1.15,
                        color: const Color(0xFFCBD5E1),
                      ),
                    )
                  : Image.memory(snapshot.data!, fit: BoxFit.cover),
            ),
          ),
        ),
      );
}
