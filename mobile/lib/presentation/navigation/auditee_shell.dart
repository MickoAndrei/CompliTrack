import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../widgets/v2/portal_navigation_bar.dart';

class AuditeeShell extends StatelessWidget {
  const AuditeeShell({super.key, required this.navigationShell});
  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: navigationShell,
    bottomNavigationBar: PortalNavigationBar(
      selectedIndex: navigationShell.currentIndex,
      onDestinationSelected: (index) => navigationShell.goBranch(index,
        initialLocation: index == navigationShell.currentIndex),
      sectionLabel: 'Findings',
      sectionIcon: Icons.fact_check_outlined,
    ),
  );
}
