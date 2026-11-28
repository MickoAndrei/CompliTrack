import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../../core/constants/app_colors.dart';
import '../current_user_avatar.dart';

/// Shared four-destination shell. The route determines selected state.
class PortalNavigationBar extends StatelessWidget {
  const PortalNavigationBar({super.key, required this.selectedIndex,
    required this.onDestinationSelected, required this.sectionLabel,
    required this.sectionIcon});
  final int selectedIndex;
  final ValueChanged<int> onDestinationSelected;
  final String sectionLabel;
  final IconData sectionIcon;

  @override
  Widget build(BuildContext context) {
    final labels = ['Home', sectionLabel, 'Files', 'Account'];
    final icons = [Icons.grid_view_outlined, sectionIcon,
      Icons.folder_outlined, Icons.person_outline];
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        systemNavigationBarColor: AppColors.ustpNavy,
        systemNavigationBarIconBrightness: Brightness.light,
      ),
      child: Material(
        color: AppColors.ustpNavy,
        child: SafeArea(
          top: false,
          child: SizedBox(
            height: 72,
            child: Row(children: List.generate(labels.length, (index) {
              final selected = index == selectedIndex;
              return Expanded(child: Semantics(
                selected: selected,
                button: true,
                label: labels[index],
                excludeSemantics: true,
                child: InkWell(
                  onTap: () => onDestinationSelected(index),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        key: ValueKey('portal-icon-$index'),
                        width: 56, height: 36,
                        decoration: BoxDecoration(
                          color: selected ? AppColors.navigationActive : Colors.transparent,
                          borderRadius: BorderRadius.circular(18),
                        ),
                        alignment: Alignment.center,
                        child: index == 3
                          ? CurrentUserAvatar(key: ValueKey(context.watch<AuthProvider>().currentUser?.id), radius: 14, avatarVersion: context.watch<AuthProvider>().avatarVersion)
                          : Icon(icons[index], size: 22,
                              color: selected ? AppColors.navigationGold : AppColors.navigationMuted),
                      ),
                      const SizedBox(height: 4),
                      Text(labels[index], maxLines: 1, overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 12, fontFamily: 'Inter',
                          fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                          color: selected ? AppColors.navigationLight : AppColors.navigationMuted)),
                    ]),
                ),
              ));
            })),
          ),
        ),
      ),
    );
  }
}
