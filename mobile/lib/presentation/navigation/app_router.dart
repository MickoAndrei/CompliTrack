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
import '../screens/auth/reset_password_screen.dart';
import '../screens/common/forbidden_403_screen.dart';
import '../screens/common/profile_screen.dart';
import '../screens/super_admin/settings_readonly_screen.dart';
import '../screens/super_admin/super_admin_dashboard_screen.dart';
import '../screens/super_admin/system_logs_screen.dart';
import '../screens/super_admin/user_management_screen.dart';
import '../screens/audits/audit_register_screen.dart';
import '../screens/audits/audit_workspace_screen.dart';
import '../screens/audits/audit_plans_screen.dart';
import '../screens/common/management_screen.dart';
import 'admin_shell.dart';
import 'auditor_shell.dart';
import 'auditee_shell.dart';
import 'super_admin_shell.dart';

class AppRouter {
  static GoRouter buildRouter(
      {required int currentRoleId, required bool isLoggedIn}) {
    return GoRouter(
      initialLocation:
          isLoggedIn ? RoleHelper.getDashboardRoute(currentRoleId) : '/landing',
      redirect: (context, state) {
        final loc = state.matchedLocation;
        final isAuthRoute = loc == '/landing' ||
            loc == '/login' ||
            loc == '/forgot-password' ||
            loc == '/reset-password';
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
          if (prefix == null ||
              (loc != prefix && !loc.startsWith('$prefix/'))) {
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
          path: '/reset-password',
          builder: (context, state) => ResetPasswordScreen(
            email: state.uri.queryParameters['email'] ?? '',
            token: state.uri.queryParameters['token'] ?? '',
          ),
        ),
        GoRoute(
          path: '/403',
          builder: (context, state) => const Forbidden403Screen(),
        ),
        GoRoute(
          path: '/common/profile',
          redirect: (context, state) => switch (currentRoleId) { 0 => '/super-admin/profile', 1 => '/admin/profile', 2 => '/auditor/profile', _ => '/auditee/profile' },
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


        // V2: retain the existing routes in four grouped destinations.
StatefulShellRoute.indexedStack(builder: (context, state, navigationShell) => SuperAdminShell(navigationShell: navigationShell), branches: [
StatefulShellBranch(routes: [
GoRoute(path: '/super-admin', builder: (context, state) => const SuperAdminDashboardScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/super-admin/manage', builder: (context, state) => const ManagementScreen()),
GoRoute(path: '/super-admin/users', builder: (context, state) => const UserManagementScreen()),
GoRoute(path: '/super-admin/departments', builder: (context, state) => const DepartmentManagementScreen()),
GoRoute(path: '/super-admin/logs', builder: (context, state) => const SystemLogsScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/super-admin/documents', builder: (context, state) => const ArchiveRepositoryScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/super-admin/profile', builder: (context, state) => const ProfileScreen()),
]),
]),
StatefulShellRoute.indexedStack(builder: (context, state, navigationShell) => AdminShell(navigationShell: navigationShell), branches: [
StatefulShellBranch(routes: [
GoRoute(path: '/admin', builder: (context, state) => const AdminDashboardScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/admin/audits', builder: (context, state) => const AuditRegisterScreen()),
GoRoute(path: '/admin/audit-workspaces/:id', builder: (context, state) => AuditWorkspaceScreen(auditId: state.pathParameters['id']!)),
GoRoute(path: '/admin/plans', builder: (context, state) => const AuditPlansScreen()),
GoRoute(path: '/admin/users', builder: (context, state) => const AdminUserManagementScreen()),
GoRoute(path: '/admin/departments', builder: (context, state) => const DepartmentManagementScreen()),
GoRoute(path: '/admin/reports', builder: (context, state) => const ReportsViewScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/admin/documents', builder: (context, state) => const ArchiveRepositoryScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/admin/profile', builder: (context, state) => const ProfileScreen()),
]),
]),
StatefulShellRoute.indexedStack(builder: (context, state, navigationShell) => AuditorShell(navigationShell: navigationShell, hideNavigationBar: state.matchedLocation == '/auditor/checks/create'), branches: [
StatefulShellBranch(routes: [
GoRoute(path: '/auditor', builder: (context, state) => const AuditorDashboardScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/auditor/audits', builder: (context, state) => const AuditRegisterScreen()),
GoRoute(path: '/auditor/audit-workspaces/:id', builder: (context, state) => AuditWorkspaceScreen(auditId: state.pathParameters['id']!)),
GoRoute(path: '/auditor/checks', builder: (context, state) => const ChecksListScreen(), routes: [GoRoute(path: 'create', builder: (context, state) => const CreateCheckScreen()),GoRoute(path: ':id', builder: (context, state) => CheckDetailScreen(checkId: state.pathParameters['id']!)),]),
GoRoute(path: '/auditor/corrective', builder: (context, state) => const CorrectiveBoardScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/auditor/documents', builder: (context, state) => const ArchiveReviewScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/auditor/profile', builder: (context, state) => const ProfileScreen(), routes: [GoRoute(path: 'notifications', builder: (context, state) => const AuditorNotificationsScreen()),]),
GoRoute(path: '/auditor/help', builder: (context, state) => const HelpReferenceScreen(forAuditor: true)),
]),
]),
StatefulShellRoute.indexedStack(builder: (context, state, navigationShell) => AuditeeShell(navigationShell: navigationShell), branches: [
StatefulShellBranch(routes: [
GoRoute(path: '/auditee', builder: (context, state) => const AuditeeDashboardScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/auditee/status', builder: (context, state) => const MyFindingsScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/auditee/documents', builder: (context, state) => const MyArchivesScreen()),
]),
StatefulShellBranch(routes: [
GoRoute(path: '/auditee/profile', builder: (context, state) => const ProfileScreen()),
GoRoute(path: '/auditee/standards', builder: (context, state) => const HelpReferenceScreen()),
]),
]),
        GoRoute(
          path: '/auditee/upload',
          builder: (context, state) => const UploadEvidenceScreen(),
        ),
        GoRoute(
          path: '/auditee/notifications',
          builder: (context, state) => const NotificationsScreen(),
        ),
      ],
    );
  }
}
