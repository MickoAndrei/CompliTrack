import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/constants/app_colors.dart';

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
              icon: Icon(Icons.grid_view_outlined, size: 22),
              activeIcon: _AuditeeActiveIcon(icon: Icons.grid_view_rounded),
              label: 'Home',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.fact_check_outlined, size: 22),
              activeIcon: _AuditeeActiveIcon(icon: Icons.fact_check_rounded),
              label: 'Status',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.inventory_2_outlined, size: 22),
              activeIcon: _AuditeeActiveIcon(icon: Icons.inventory_2_rounded),
              label: 'Archive',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.help_outline_rounded, size: 22),
              activeIcon: _AuditeeActiveIcon(icon: Icons.help_rounded),
              label: 'Standards',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.account_circle_outlined, size: 22),
              activeIcon: _AuditeeActiveIcon(icon: Icons.account_circle),
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
