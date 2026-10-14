import 'package:go_router/go_router.dart';
import '../../core/utils/role_helper.dart';
import '../screens/admin/admin_notifications_screen.dart';
import '../screens/admin/archive_repository_screen.dart';
import '../screens/admin/department_management_screen.dart';
import '../screens/common/forbidden_403_screen.dart';
import '../screens/common/profile_screen.dart';
import '../screens/super_admin/settings_readonly_screen.dart';
import '../screens/super_admin/super_admin_dashboard_screen.dart';
import '../screens/super_admin/system_logs_screen.dart';
import '../screens/super_admin/user_management_screen.dart';
import 'super_admin_shell.dart';

class AppRouter {
  static GoRouter buildRouter({required int currentRoleId, required bool isLoggedIn}) {
    return GoRouter(
      initialLocation: RoleHelper.getDashboardRoute(currentRoleId),
      redirect: (context, state) {
        final loc = state.matchedLocation;
        if (!isLoggedIn) {
          return loc == '/login' || loc == '/forgot-password' ? null : '/login';
        }
        if (loc == '/login') {
          return RoleHelper.getDashboardRoute(currentRoleId);
        }
        final home = RoleHelper.getDashboardRoute(currentRoleId);
        final allowed = loc.startsWith(home) || loc.startsWith('/common');
        return allowed ? null : '/403';
      },
      routes: [
        GoRoute(
          path: '/403',
          builder: (context, state) => const Forbidden403Screen(),
        ),
        GoRoute(
          path: '/common/profile',
          builder: (context, state) => const ProfileScreen(),
        ),
        GoRoute(
          path: '/super-admin/settings',
          builder: (context, state) => const SettingsReadonlyScreen(),
        ),
        GoRoute(
          path: '/super-admin/notifications',
          builder: (context, state) => const AdminNotificationsScreen(),
        ),
        StatefulShellRoute.indexedStack(
          builder: (context, state, navigationShell) {
            return SuperAdminShell(navigationShell: navigationShell);
          },
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/super-admin',
                  builder: (context, state) => const SuperAdminDashboardScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/super-admin/users',
                  builder: (context, state) => const UserManagementScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/super-admin/departments',
                  builder: (context, state) => const DepartmentManagementScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/super-admin/logs',
                  builder: (context, state) => const SystemLogsScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/super-admin/archives',
                  builder: (context, state) => const ArchiveRepositoryScreen(),
                ),
              ],
            ),
          ],
        ),
      ],
    );
  }
}
