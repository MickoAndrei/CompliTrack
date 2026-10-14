import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_icons.dart';

class AuditeeShell extends StatelessWidget {
  final StatefulNavigationShell navigationShell;

  const AuditeeShell({
    super.key,
    required this.navigationShell,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: AppColors.ustpNavy,
        ),
        child: BottomNavigationBar(
          currentIndex: navigationShell.currentIndex,
          onTap: (index) => navigationShell.goBranch(
            index,
            initialLocation: index == navigationShell.currentIndex,
          ),
          type: BottomNavigationBarType.fixed,
          backgroundColor: AppColors.ustpNavy,
          selectedItemColor: AppColors.ustpGold,
          unselectedItemColor: AppColors.ustpMuted,
          selectedLabelStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 11,
            fontWeight: FontWeight.bold,
          ),
          unselectedLabelStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 11,
          ),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(AppIcons.dashboard),
              label: 'Home',
            ),
            BottomNavigationBarItem(
              icon: Icon(AppIcons.compliance),
              label: 'Findings',
            ),
            BottomNavigationBarItem(
              icon: Icon(AppIcons.upload),
              label: 'Upload',
            ),
            BottomNavigationBarItem(
              icon: Icon(AppIcons.notifications),
              label: 'Alerts',
            ),
          ],
        ),
      ),
    );
  }
}
