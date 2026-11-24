import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';

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

  Future<Uint8List?> _loadAvatar() => context.read<AuthProvider>().loadAvatar();

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
              builder: (context, snapshot) =>
                  snapshot.connectionState != ConnectionState.done ||
                          snapshot.data == null
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
