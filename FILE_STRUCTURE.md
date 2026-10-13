# CompliTrack — Full File Structure

`← NEW` = empty placeholder file added for you to fill in.

```
CompliTrack/
├── app/
│   ├── Helpers/
│   │   └── RoleHelper.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   └── V1/
│   │   │   │       ├── ArchiveController.php
│   │   │   │       ├── AuditLogController.php
│   │   │   │       ├── AuthController.php
│   │   │   │       ├── ComplianceCheckController.php
│   │   │   │       ├── DepartmentController.php
│   │   │   │       ├── NotificationController.php
│   │   │   │       ├── ReportController.php
│   │   │   │       └── UserController.php
│   │   │   ├── Web/
│   │   │   │   ├── DashboardController.php  ← NEW
│   │   │   │   └── ProfileController.php  ← NEW
│   │   │   └── Controller.php
│   │   └── Middleware/
│   │       ├── AuditLogMiddleware.php  ← NEW
│   │       ├── EnsureUserIsActive.php  ← NEW
│   │       └── RoleMiddleware.php
│   ├── Models/
│   │   ├── ArchiveDocument.php
│   │   ├── AuditLog.php
│   │   ├── ComplianceCheck.php
│   │   ├── Department.php
│   │   ├── Notification.php
│   │   ├── Role.php
│   │   └── User.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── RateLimitServiceProvider.php  ← NEW
│   └── Services/
│       ├── ArchiveEncryptionService.php
│       ├── AuditLogService.php  ← NEW
│       ├── ComplianceCheckService.php
│       ├── NotificationDispatchService.php
│       └── ReportGeneratorService.php
├── bootstrap/
│   ├── app.php
│   └── providers.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── cors.php  ← NEW
│   ├── database.php
│   ├── filesystems.php
│   ├── jwt.php  ← NEW
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── sanctum.php  ← NEW
│   ├── services.php
│   └── session.php
├── database/
│   ├── factories/
│   │   └── UserFactory.php
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_framework_support_tables.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   └── 2026_01_01_000001_create_mongo_indexes.php  ← NEW
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── DepartmentSeeder.php  ← NEW
│       ├── RoleSeeder.php
│       └── SuperAdminSeeder.php  ← NEW
├── mobile/
│   ├── assets/
│   │   ├── fonts/
│   │   │   └── .gitkeep  ← NEW
│   │   └── images/
│   │       └── .gitkeep  ← NEW
│   ├── lib/
│   │   ├── core/
│   │   │   ├── constants/
│   │   │   │   ├── api_constants.dart  ← NEW
│   │   │   │   ├── app_colors.dart  ← NEW
│   │   │   │   ├── app_icons.dart  ← NEW
│   │   │   │   └── app_strings.dart  ← NEW
│   │   │   ├── network/
│   │   │   │   ├── api_client.dart  ← NEW
│   │   │   │   └── auth_interceptor.dart  ← NEW
│   │   │   ├── services/
│   │   │   │   ├── fcm_service.dart  ← NEW
│   │   │   │   ├── secure_storage_service.dart  ← NEW
│   │   │   │   └── socket_service.dart  ← NEW
│   │   │   ├── theme/
│   │   │   │   └── ustp_theme.dart  ← NEW
│   │   │   └── utils/
│   │   │       ├── date_formatter.dart  ← NEW
│   │   │       ├── file_validator.dart  ← NEW
│   │   │       ├── role_helper.dart  ← NEW
│   │   │       └── validators.dart  ← NEW
│   │   ├── data/
│   │   │   ├── models/
│   │   │   │   ├── archive_document_model.dart  ← NEW
│   │   │   │   ├── audit_log_model.dart  ← NEW
│   │   │   │   ├── compliance_check_model.dart  ← NEW
│   │   │   │   ├── department_model.dart  ← NEW
│   │   │   │   ├── notification_model.dart  ← NEW
│   │   │   │   ├── role_model.dart  ← NEW
│   │   │   │   └── user_model.dart  ← NEW
│   │   │   └── repositories/
│   │   │       ├── archive_repository.dart  ← NEW
│   │   │       ├── audit_log_repository.dart  ← NEW
│   │   │       ├── auth_repository.dart  ← NEW
│   │   │       ├── compliance_repository.dart  ← NEW
│   │   │       ├── department_repository.dart  ← NEW
│   │   │       ├── notification_repository.dart  ← NEW
│   │   │       ├── report_repository.dart  ← NEW
│   │   │       └── user_repository.dart  ← NEW
│   │   ├── presentation/
│   │   │   ├── navigation/
│   │   │   │   ├── admin_shell.dart  ← NEW
│   │   │   │   ├── app_router.dart  ← NEW
│   │   │   │   ├── auditee_shell.dart  ← NEW
│   │   │   │   ├── auditor_shell.dart  ← NEW
│   │   │   │   └── super_admin_shell.dart  ← NEW
│   │   │   ├── screens/
│   │   │   │   ├── admin/
│   │   │   │   │   ├── admin_dashboard_screen.dart  ← NEW
│   │   │   │   │   ├── admin_notifications_screen.dart  ← NEW
│   │   │   │   │   ├── admin_user_management_screen.dart  ← NEW
│   │   │   │   │   ├── archive_repository_screen.dart  ← NEW
│   │   │   │   │   ├── department_management_screen.dart  ← NEW
│   │   │   │   │   └── reports_view_screen.dart  ← NEW
│   │   │   │   ├── auditee/
│   │   │   │   │   ├── auditee_dashboard_screen.dart  ← NEW
│   │   │   │   │   ├── finding_detail_screen.dart  ← NEW
│   │   │   │   │   ├── help_reference_screen.dart  ← NEW
│   │   │   │   │   ├── my_archives_screen.dart  ← NEW
│   │   │   │   │   ├── my_findings_screen.dart  ← NEW
│   │   │   │   │   ├── notifications_screen.dart  ← NEW
│   │   │   │   │   └── upload_evidence_screen.dart  ← NEW
│   │   │   │   ├── auditor/
│   │   │   │   │   ├── archive_review_screen.dart  ← NEW
│   │   │   │   │   ├── auditor_dashboard_screen.dart  ← NEW
│   │   │   │   │   ├── auditor_notifications_screen.dart  ← NEW
│   │   │   │   │   ├── check_detail_screen.dart  ← NEW
│   │   │   │   │   ├── checks_list_screen.dart  ← NEW
│   │   │   │   │   ├── corrective_board_screen.dart  ← NEW
│   │   │   │   │   └── create_check_screen.dart  ← NEW
│   │   │   │   ├── auth/
│   │   │   │   │   ├── forgot_password_screen.dart  ← NEW
│   │   │   │   │   └── login_screen.dart  ← NEW
│   │   │   │   ├── common/
│   │   │   │   │   ├── forbidden_403_screen.dart  ← NEW
│   │   │   │   │   └── profile_screen.dart  ← NEW
│   │   │   │   └── super_admin/
│   │   │   │       ├── role_management_screen.dart  ← NEW
│   │   │   │       ├── settings_readonly_screen.dart  ← NEW
│   │   │   │       ├── super_admin_dashboard_screen.dart  ← NEW
│   │   │   │       ├── system_logs_screen.dart  ← NEW
│   │   │   │       └── user_management_screen.dart  ← NEW
│   │   │   └── widgets/
│   │   │       ├── compliance_gauge.dart  ← NEW
│   │   │       ├── empty_state.dart  ← NEW
│   │   │       ├── encryption_indicator.dart  ← NEW
│   │   │       ├── kpi_card.dart  ← NEW
│   │   │       ├── manage_on_web_badge.dart  ← NEW
│   │   │       ├── role_badge.dart  ← NEW
│   │   │       ├── status_badge.dart  ← NEW
│   │   │       ├── timeline_stepper.dart  ← NEW
│   │   │       └── ustp_app_bar.dart  ← NEW
│   │   ├── app.dart  ← NEW
│   │   └── main.dart  ← NEW
│   ├── test/
│   │   ├── models_test.dart  ← NEW
│   │   └── role_helper_test.dart  ← NEW
│   ├── .env.example  ← NEW
│   ├── .gitignore  ← NEW
│   ├── README.md  ← NEW
│   ├── analysis_options.yaml  ← NEW
│   └── pubspec.yaml  ← NEW
├── public/
│   ├── fonts/
│   │   └── .gitkeep  ← NEW
│   ├── images/
│   │   └── .gitkeep  ← NEW
│   ├── .htaccess
│   ├── favicon.ico
│   ├── index.php
│   └── robots.txt
├── realtime/
│   ├── src/
│   │   ├── config/
│   │   │   ├── db.js  ← NEW
│   │   │   ├── env.js  ← NEW
│   │   │   └── firebase.js  ← NEW
│   │   ├── models/
│   │   │   └── notification.js  ← NEW
│   │   ├── services/
│   │   │   ├── fcmService.js  ← NEW
│   │   │   └── notificationService.js  ← NEW
│   │   ├── sockets/
│   │   │   ├── auth.js  ← NEW
│   │   │   └── index.js  ← NEW
│   │   ├── utils/
│   │   │   └── logger.js  ← NEW
│   │   ├── watchers/
│   │   │   └── complianceChecksWatcher.js  ← NEW
│   │   └── server.js  ← NEW
│   ├── .env.example  ← NEW
│   ├── .gitignore  ← NEW
│   ├── README.md  ← NEW
│   └── package.json  ← NEW
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   └── bootstrap.js
│   └── views/
│       ├── admin/
│       │   ├── archives.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── departments.blade.php
│       │   ├── notifications.blade.php
│       │   ├── reports.blade.php
│       │   └── users.blade.php
│       ├── auditee/
│       │   ├── archives.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── finding-details.blade.php  ← NEW
│       │   ├── help.blade.php
│       │   ├── my-findings.blade.php
│       │   ├── notifications.blade.php
│       │   └── upload.blade.php
│       ├── auditor/
│       │   ├── archives.blade.php
│       │   ├── check-create.blade.php  ← NEW
│       │   ├── check-details.blade.php  ← NEW
│       │   ├── checks.blade.php
│       │   ├── corrective-actions.blade.php
│       │   ├── dashboard.blade.php
│       │   └── notifications.blade.php
│       ├── auth/
│       │   ├── forgot-password.blade.php
│       │   ├── login.blade.php
│       │   └── profile.blade.php
│       ├── components/
│       │   ├── alert-banner.blade.php  ← NEW
│       │   ├── compliance-gauge.blade.php  ← NEW
│       │   ├── data-table.blade.php  ← NEW
│       │   ├── kpi-card.blade.php
│       │   ├── role-badge.blade.php
│       │   ├── status-badge.blade.php
│       │   ├── timeline-stepper.blade.php  ← NEW
│       │   └── upload-zone.blade.php  ← NEW
│       ├── errors/
│       │   ├── 403.blade.php  ← NEW
│       │   └── 404.blade.php  ← NEW
│       ├── layouts/
│       │   └── app.blade.php
│       ├── partials/
│       │   ├── navbar.blade.php  ← NEW
│       │   └── sidebar.blade.php  ← NEW
│       └── super-admin/
│           ├── dashboard.blade.php
│           ├── departments.blade.php
│           ├── logs.blade.php
│           ├── roles.blade.php
│           ├── settings.blade.php
│           └── users.blade.php
├── routes/
│   ├── api.php
│   ├── console.php
│   └── web.php
├── tests/
│   ├── Feature/
│   │   ├── ArchiveEncryptionTest.php  ← NEW
│   │   ├── AuthTest.php  ← NEW
│   │   ├── ComplianceCheckTest.php  ← NEW
│   │   ├── ExampleTest.php
│   │   ├── NotificationTest.php  ← NEW
│   │   └── RoleMiddlewareTest.php  ← NEW
│   ├── Unit/
│   │   └── ExampleTest.php
│   └── TestCase.php
├── .env.example
├── .gitignore
├── LICENSE
├── README.md
├── artisan
├── composer.json
├── package.json
├── phpunit.xml
└── vite.config.js
```
