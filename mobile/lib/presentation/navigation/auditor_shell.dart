import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_icons.dart';

class AuditorShell extends StatelessWidget {
  const AuditorShell({
    super.key,
    required this.navigationShell,
    this.hideNavigationBar = false,
  });

  final StatefulNavigationShell navigationShell;
  final bool hideNavigationBar;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: hideNavigationBar
          ? null
          : BottomNavigationBar(
        currentIndex: navigationShell.currentIndex,
        onTap: (index) => navigationShell.goBranch(
          index,
          initialLocation: index == navigationShell.currentIndex,
        ),
        type: BottomNavigationBarType.fixed,
        backgroundColor: AppColors.ustpNavy,
        selectedItemColor: AppColors.ustpGold,
        unselectedItemColor: AppColors.ustpMuted,
        selectedFontSize: 9,
        unselectedFontSize: 8,
        iconSize: 18,
        elevation: 0,
        items: const [
          BottomNavigationBarItem(icon: Icon(AppIcons.dashboard), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(AppIcons.compliance), label: 'Checks'),
          BottomNavigationBarItem(icon: Icon(AppIcons.archive), label: 'Archive'),
          BottomNavigationBarItem(icon: Icon(AppIcons.inProgress), label: 'Actions'),
          BottomNavigationBarItem(icon: Icon(AppIcons.user), label: 'Profile'),
        ],
      ),
    );
  }
}