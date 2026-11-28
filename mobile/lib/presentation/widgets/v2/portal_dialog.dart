import 'dart:ui' show ImageFilter;
import 'package:flutter/material.dart';

/// Blur belongs behind, not inside, the modal. Preserve native route/back behavior.
Future<T?> showPortalDialog<T>({required BuildContext context,
  required WidgetBuilder builder, bool barrierDismissible = true}) {
  return showGeneralDialog<T>(
    context: context,
    barrierDismissible: barrierDismissible,
    barrierLabel: MaterialLocalizations.of(context).modalBarrierDismissLabel,
    barrierColor: Colors.transparent,
    transitionDuration: MediaQuery.disableAnimationsOf(context)
      ? Duration.zero : const Duration(milliseconds: 180),
    pageBuilder: (context, _, _) => Stack(fit: StackFit.expand, children: [
      Positioned.fill(child: IgnorePointer(child: ClipRect(child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 6, sigmaY: 6),
        child: const ColoredBox(color: Color(0x3D0F172A)),
      )))),
      SafeArea(child: Builder(builder: builder)),
    ]),
    transitionBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: child),
  );
}
