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
            fontSize: 10,
            fontWeight: FontWeight.bold,
          ),
          unselectedLabelStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 10,
          ),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(AppIcons.dashboard, size: 20),
              activeIcon: _AuditeeActiveIcon(icon: AppIcons.dashboard),
              label: 'Home',
            ),
            BottomNavigationBarItem(
              icon: Icon(AppIcons.compliance, size: 20),
              activeIcon: _AuditeeActiveIcon(icon: AppIcons.compliance),
              label: 'Status',
            ),
            BottomNavigationBarItem(
              icon: Icon(AppIcons.archive, size: 20),
              activeIcon: _AuditeeActiveIcon(icon: AppIcons.archive),
              label: 'Archive',
            ),
            BottomNavigationBarItem(
              icon: Icon(AppIcons.help, size: 20),
              activeIcon: _AuditeeActiveIcon(icon: AppIcons.help),
              label: 'Standards',
            ),
            BottomNavigationBarItem(
              icon: Icon(AppIcons.user, size: 20),
              activeIcon: _AuditeeActiveIcon(icon: AppIcons.user),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }
}

class _AuditeeActiveIcon extends StatelessWidget {
  const _AuditeeActiveIcon({required this.icon});
  final IconData icon;

  @override
  Widget build(BuildContext context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 20, color: AppColors.ustpGold),
          const SizedBox(height: 2),
          Container(
            width: 12,
            height: 2,
            decoration: BoxDecoration(
              color: AppColors.ustpGold,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ],
      );
}
