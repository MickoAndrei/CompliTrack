import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../widgets/v2/portal_navigation_bar.dart';

class AuditorShell extends StatelessWidget {
  const AuditorShell({super.key, required this.navigationShell, this.hideNavigationBar = false});
  final StatefulNavigationShell navigationShell;
  final bool hideNavigationBar;
  @override
  Widget build(BuildContext context) => Scaffold(
    body: navigationShell,
    bottomNavigationBar: hideNavigationBar ? null : PortalNavigationBar(
      selectedIndex: navigationShell.currentIndex,
      onDestinationSelected: (index) => navigationShell.goBranch(index,
        initialLocation: index == navigationShell.currentIndex),
      sectionLabel: 'Audits',
      sectionIcon: Icons.fact_check_outlined,
    ),
  );
}
