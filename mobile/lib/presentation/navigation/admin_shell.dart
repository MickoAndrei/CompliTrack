import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_icons.dart';

class AdminShell extends StatelessWidget {
  const AdminShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: navigationShell.currentIndex,
        onTap: (index) => navigationShell.goBranch(
          index,
          initialLocation: index == navigationShell.currentIndex,
        ),
        type: BottomNavigationBarType.fixed,
        backgroundColor: AppColors.ustpNavy,
        selectedItemColor: AppColors.ustpGold,
        unselectedItemColor: AppColors.ustpMuted,
        items: const [
          BottomNavigationBarItem(icon: Icon(AppIcons.dashboard), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(AppIcons.users), label: 'Users'),
          BottomNavigationBarItem(icon: Icon(AppIcons.department), label: 'Depts'),
          BottomNavigationBarItem(icon: Icon(AppIcons.compliance), label: 'Reports'),
          BottomNavigationBarItem(icon: Icon(AppIcons.archive), label: 'Archives'),
        ],
      ),
    );
  }
}