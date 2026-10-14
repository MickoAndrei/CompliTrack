import 'package:go_router/go_router.dart';
import '../../core/utils/role_helper.dart';
import '../screens/admin/admin_dashboard_screen.dart';
import '../screens/admin/admin_notifications_screen.dart';
import '../screens/admin/admin_user_management_screen.dart';
import '../screens/admin/archive_repository_screen.dart';
import '../screens/admin/department_management_screen.dart';
import '../screens/admin/reports_view_screen.dart';
import '../screens/auditee/notifications_screen.dart';
import '../screens/auditee/upload_evidence_screen.dart';
import '../screens/auditee/auditee_dashboard_screen.dart';
import '../screens/auditee/help_reference_screen.dart';
import '../screens/auditee/my_archives_screen.dart';
import '../screens/auditee/my_findings_screen.dart';
import '../screens/auditor/archive_review_screen.dart';
import '../screens/auditor/auditor_dashboard_screen.dart';
import '../screens/auditor/auditor_notifications_screen.dart';
import '../screens/auditor/check_detail_screen.dart';
import '../screens/auditor/checks_list_screen.dart';
import '../screens/auditor/corrective_board_screen.dart';
import '../screens/auditor/create_check_screen.dart';
import '../screens/auth/forgot_password_screen.dart';
import '../screens/auth/landing_screen.dart';
import '../screens/auth/login_screen.dart';
import '../screens/common/forbidden_403_screen.dart';
import '../screens/common/profile_screen.dart';
import '../screens/super_admin/settings_readonly_screen.dart';
import '../screens/super_admin/super_admin_dashboard_screen.dart';
import '../screens/super_admin/system_logs_screen.dart';
import '../screens/super_admin/user_management_screen.dart';
import 'admin_shell.dart';
import 'auditor_shell.dart';
import 'auditee_shell.dart';
import 'super_admin_shell.dart';

class AppRouter {
  static GoRouter buildRouter({required int currentRoleId, required bool isLoggedIn}) {
    return GoRouter(
      initialLocation: isLoggedIn ? RoleHelper.getDashboardRoute(currentRoleId) : '/landing',
      redirect: (context, state) {
        final loc = state.matchedLocation;
        final isAuthRoute = loc == '/landing' || loc == '/login' || loc == '/forgot-password';
        if (!isLoggedIn && !isAuthRoute) {
          return '/login';
        }
        if (isLoggedIn && isAuthRoute) {
          return RoleHelper.getDashboardRoute(currentRoleId);
        }
        if (isLoggedIn && loc != '/403' && loc != '/common/profile') {
          final prefix = switch (currentRoleId) {
            RoleHelper.roleSuperAdmin => '/super-admin',
            RoleHelper.roleAdmin => '/admin',
            RoleHelper.roleAuditor => '/auditor',
            RoleHelper.roleAuditee => '/auditee',
            _ => null,
          };
          if (prefix == null || (loc != prefix && !loc.startsWith('$prefix/'))) {
            return '/403';
          }
        }
        return null;
      },
      routes: [
        GoRoute(
          path: '/landing',
          builder: (context, state) => const LandingScreen(),
        ),
        GoRoute(
          path: '/login',
          builder: (context, state) => const LoginScreen(),
        ),
        GoRoute(
          path: '/forgot-password',
          builder: (context, state) => const ForgotPasswordScreen(),
        ),
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
        GoRoute(
          path: '/admin/notifications',
          builder: (context, state) => const AdminNotificationsScreen(),
        ),

        // Super Admin Shell Route
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

        // Admin Shell Route
        StatefulShellRoute.indexedStack(
          builder: (context, state, navigationShell) {
            return AdminShell(navigationShell: navigationShell);
          },
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/admin',
                  builder: (context, state) => const AdminDashboardScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/admin/users',
                  builder: (context, state) => const AdminUserManagementScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/admin/departments',
                  builder: (context, state) => const DepartmentManagementScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/admin/reports',
                  builder: (context, state) => const ReportsViewScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/admin/archives',
                  builder: (context, state) => const ArchiveRepositoryScreen(),
                ),
              ],
            ),
          ],
        ),

        // Auditor Shell Route
        StatefulShellRoute.indexedStack(
          builder: (context, state, navigationShell) {
            return AuditorShell(navigationShell: navigationShell);
          },
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditor',
                  builder: (context, state) => const AuditorDashboardScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditor/checks',
                  builder: (context, state) => const ChecksListScreen(),
                  routes: [
                    GoRoute(
                      path: 'create',
                      builder: (context, state) => const CreateCheckScreen(),
                    ),
                    GoRoute(
                      path: ':id',
                      builder: (context, state) => CheckDetailScreen(
                        checkId: state.pathParameters['id']!,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditor/corrective',
                  builder: (context, state) => const CorrectiveBoardScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditor/archives',
                  builder: (context, state) => const ArchiveReviewScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditor/notifications',
                  builder: (context, state) => const AuditorNotificationsScreen(),
                ),
              ],
            ),
          ],
        ),

        // Auditee Shell Route
        StatefulShellRoute.indexedStack(
          builder: (context, state, navigationShell) {
            return AuditeeShell(navigationShell: navigationShell);
          },
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditee',
                  builder: (context, state) => const AuditeeDashboardScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditee/status',
                  builder: (context, state) => const MyFindingsScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditee/upload',
                  builder: (context, state) => const UploadEvidenceScreen(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/auditee/notifications',
                  builder: (context, state) => const NotificationsScreen(),
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/auditee/archives',
          builder: (context, state) => const MyArchivesScreen(),
        ),
        GoRoute(
          path: '/auditee/standards',
          builder: (context, state) => const HelpReferenceScreen(),
        ),
        GoRoute(
          path: '/auditee/profile',
          builder: (context, state) => const ProfileScreen(),
        ),
      ],
    );
  }
}
