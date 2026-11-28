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

  String _initials(BuildContext context) {
    final name = context.watch<AuthProvider>().currentUser?.fullName.trim() ?? '';
    final words = name.split(RegExp(r'\s+')).where((s) => s.isNotEmpty).take(2);
    return words.isEmpty ? 'U' : words.map((s) => s.characters.first.toUpperCase()).join();
  }

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(2),
        decoration: const BoxDecoration(
          color: Color(0xFFEAF0F8),
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
                          child: Center(child: Text(
                            _initials(context),
                            style: TextStyle(color: const Color(0xFF0A2B59), fontSize: widget.radius * .8, fontWeight: FontWeight.w600),
                          )),
                        )
                      : Image.memory(snapshot.data!, fit: BoxFit.cover),
            ),
          ),
        ),
      );
}
