# CompliTrack — File-by-File Structure

This listing includes every Git-tracked source file in the repository, including platform assets and the CI workflow files.

```text
CompliTrack/
├── .github/
│   └── workflows/
│       ├── build.yml
│       └── deploy-backend.yml
├── app/
│   ├── Casts/
│   │   ├── NullableObjectId.php
│   │   └── ObjectIdArray.php
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
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── ProfileController.php
│   │   │   └── Controller.php
│   │   └── Middleware/
│   │       ├── AuditLogMiddleware.php
│   │       ├── EnsureUserIsActive.php
│   │       └── RoleMiddleware.php
│   ├── Models/
│   │   ├── ArchiveDocument.php
│   │   ├── AuditLog.php
│   │   ├── ComplianceCheck.php
│   │   ├── Department.php
│   │   ├── Notification.php
│   │   ├── PasswordResetToken.php
│   │   ├── PersonalAccessToken.php
│   │   ├── Role.php
│   │   └── User.php
│   ├── Notifications/
│   │   └── PasswordResetLink.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── RateLimitServiceProvider.php
│   ├── Services/
│   │   ├── ArchiveEncryptionService.php
│   │   ├── AuditLogService.php
│   │   ├── ComplianceCheckService.php
│   │   ├── CounterService.php
│   │   ├── NotificationDispatchService.php
│   │   ├── PasswordResetService.php
│   │   ├── ReportGeneratorService.php
│   │   └── RoleScopeService.php
│   └── Support/
│       └── MongoId.php
├── bootstrap/
│   ├── cache/
│   │   ├── .gitignore
│   │   ├── packages.php
│   │   └── services.php
│   ├── app.php
│   └── providers.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── cors.php
│   ├── database.php
│   ├── filesystems.php
│   ├── jwt.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── sanctum.php
│   ├── services.php
│   └── session.php
├── database/
│   ├── factories/
│   │   └── UserFactory.php
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_framework_support_tables.php
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   └── 2026_01_01_000001_create_mongo_indexes.php
│   ├── seeders/
│   │   ├── ArchiveDemoSeeder.php
│   │   ├── DatabaseSeeder.php
│   │   ├── DemoDataSeeder.php
│   │   ├── DepartmentSeeder.php
│   │   ├── RoleSeeder.php
│   │   └── SuperAdminSeeder.php
│   └── .gitignore
├── mobile/
│   ├── android/
│   │   ├── app/
│   │   │   ├── src/
│   │   │   │   ├── debug/
│   │   │   │   │   └── AndroidManifest.xml
│   │   │   │   ├── main/
│   │   │   │   │   ├── kotlin/
│   │   │   │   │   │   └── ph/
│   │   │   │   │   │       └── edu/
│   │   │   │   │   │           └── ustp/
│   │   │   │   │   │               └── complitrack/
│   │   │   │   │   │                   └── MainActivity.kt
│   │   │   │   │   ├── res/
│   │   │   │   │   │   ├── drawable/
│   │   │   │   │   │   │   └── launch_background.xml
│   │   │   │   │   │   ├── drawable-v21/
│   │   │   │   │   │   │   └── launch_background.xml
│   │   │   │   │   │   ├── mipmap-hdpi/
│   │   │   │   │   │   │   └── ic_launcher.png
│   │   │   │   │   │   ├── mipmap-mdpi/
│   │   │   │   │   │   │   └── ic_launcher.png
│   │   │   │   │   │   ├── mipmap-xhdpi/
│   │   │   │   │   │   │   └── ic_launcher.png
│   │   │   │   │   │   ├── mipmap-xxhdpi/
│   │   │   │   │   │   │   └── ic_launcher.png
│   │   │   │   │   │   ├── mipmap-xxxhdpi/
│   │   │   │   │   │   │   └── ic_launcher.png
│   │   │   │   │   │   ├── values/
│   │   │   │   │   │   │   └── styles.xml
│   │   │   │   │   │   └── values-night/
│   │   │   │   │   │       └── styles.xml
│   │   │   │   │   └── AndroidManifest.xml
│   │   │   │   └── profile/
│   │   │   │       └── AndroidManifest.xml
│   │   │   └── build.gradle.kts
│   │   ├── gradle/
│   │   │   └── wrapper/
│   │   │       └── gradle-wrapper.properties
│   │   ├── .gitignore
│   │   ├── build.gradle.kts
│   │   ├── gradle.properties
│   │   └── settings.gradle.kts
│   ├── assets/
│   │   ├── fonts/
│   │   │   └── .gitkeep
│   │   └── images/
│   │       ├── .gitkeep
│   │       └── ustp-logo-mark.png
│   ├── ios/
│   │   ├── Flutter/
│   │   │   ├── AppFrameworkInfo.plist
│   │   │   ├── Debug.xcconfig
│   │   │   └── Release.xcconfig
│   │   ├── Runner/
│   │   │   ├── Assets.xcassets/
│   │   │   │   ├── AppIcon.appiconset/
│   │   │   │   │   ├── Contents.json
│   │   │   │   │   ├── Icon-App-1024x1024@1x.png
│   │   │   │   │   ├── Icon-App-20x20@1x.png
│   │   │   │   │   ├── Icon-App-20x20@2x.png
│   │   │   │   │   ├── Icon-App-20x20@3x.png
│   │   │   │   │   ├── Icon-App-29x29@1x.png
│   │   │   │   │   ├── Icon-App-29x29@2x.png
│   │   │   │   │   ├── Icon-App-29x29@3x.png
│   │   │   │   │   ├── Icon-App-40x40@1x.png
│   │   │   │   │   ├── Icon-App-40x40@2x.png
│   │   │   │   │   ├── Icon-App-40x40@3x.png
│   │   │   │   │   ├── Icon-App-60x60@2x.png
│   │   │   │   │   ├── Icon-App-60x60@3x.png
│   │   │   │   │   ├── Icon-App-76x76@1x.png
│   │   │   │   │   ├── Icon-App-76x76@2x.png
│   │   │   │   │   └── Icon-App-83.5x83.5@2x.png
│   │   │   │   └── LaunchImage.imageset/
│   │   │   │       ├── Contents.json
│   │   │   │       ├── LaunchImage.png
│   │   │   │       ├── LaunchImage@2x.png
│   │   │   │       ├── LaunchImage@3x.png
│   │   │   │       └── README.md
│   │   │   ├── Base.lproj/
│   │   │   │   ├── LaunchScreen.storyboard
│   │   │   │   └── Main.storyboard
│   │   │   ├── AppDelegate.swift
│   │   │   ├── Info.plist
│   │   │   ├── Runner-Bridging-Header.h
│   │   │   └── SceneDelegate.swift
│   │   ├── Runner.xcodeproj/
│   │   │   ├── project.xcworkspace/
│   │   │   │   ├── xcshareddata/
│   │   │   │   │   ├── IDEWorkspaceChecks.plist
│   │   │   │   │   └── WorkspaceSettings.xcsettings
│   │   │   │   └── contents.xcworkspacedata
│   │   │   ├── xcshareddata/
│   │   │   │   └── xcschemes/
│   │   │   │       └── Runner.xcscheme
│   │   │   └── project.pbxproj
│   │   ├── Runner.xcworkspace/
│   │   │   ├── xcshareddata/
│   │   │   │   ├── IDEWorkspaceChecks.plist
│   │   │   │   └── WorkspaceSettings.xcsettings
│   │   │   └── contents.xcworkspacedata
│   │   ├── RunnerTests/
│   │   │   └── RunnerTests.swift
│   │   ├── .gitignore
│   │   └── Podfile
│   ├── lib/
│   │   ├── core/
│   │   │   ├── constants/
│   │   │   │   ├── api_constants.dart
│   │   │   │   ├── app_colors.dart
│   │   │   │   ├── app_icons.dart
│   │   │   │   └── app_strings.dart
│   │   │   ├── network/
│   │   │   │   ├── api_client.dart
│   │   │   │   ├── api_response.dart
│   │   │   │   └── auth_interceptor.dart
│   │   │   ├── services/
│   │   │   │   ├── fcm_service.dart
│   │   │   │   ├── secure_storage_service.dart
│   │   │   │   └── socket_service.dart
│   │   │   ├── theme/
│   │   │   │   └── ustp_theme.dart
│   │   │   └── utils/
│   │   │       ├── date_formatter.dart
│   │   │       ├── file_validator.dart
│   │   │       ├── role_helper.dart
│   │   │       └── validators.dart
│   │   ├── data/
│   │   │   ├── models/
│   │   │   │   ├── archive_document_model.dart
│   │   │   │   ├── audit_log_model.dart
│   │   │   │   ├── compliance_check_model.dart
│   │   │   │   ├── department_model.dart
│   │   │   │   ├── model_json.dart
│   │   │   │   ├── notification_model.dart
│   │   │   │   ├── role_model.dart
│   │   │   │   ├── super_admin_dashboard_model.dart
│   │   │   │   └── user_model.dart
│   │   │   └── repositories/
│   │   │       ├── archive_repository.dart
│   │   │       ├── audit_log_repository.dart
│   │   │       ├── auth_repository.dart
│   │   │       ├── compliance_repository.dart
│   │   │       ├── department_repository.dart
│   │   │       ├── notification_repository.dart
│   │   │       ├── report_repository.dart
│   │   │       ├── super_admin_repository.dart
│   │   │       ├── system_health_repository.dart
│   │   │       └── user_repository.dart
│   │   ├── presentation/
│   │   │   ├── navigation/
│   │   │   │   ├── admin_shell.dart
│   │   │   │   ├── app_router.dart
│   │   │   │   ├── auditee_shell.dart
│   │   │   │   ├── auditor_shell.dart
│   │   │   │   └── super_admin_shell.dart
│   │   │   ├── providers/
│   │   │   │   └── auth_provider.dart
│   │   │   ├── screens/
│   │   │   │   ├── admin/
│   │   │   │   │   ├── admin_dashboard_screen.dart
│   │   │   │   │   ├── admin_notifications_screen.dart
│   │   │   │   │   ├── admin_user_management_screen.dart
│   │   │   │   │   ├── archive_repository_screen.dart
│   │   │   │   │   ├── broadcast_notification_dialog.dart
│   │   │   │   │   ├── department_management_screen.dart
│   │   │   │   │   └── reports_view_screen.dart
│   │   │   │   ├── auditee/
│   │   │   │   │   ├── auditee_dashboard_screen.dart
│   │   │   │   │   ├── finding_detail_screen.dart
│   │   │   │   │   ├── help_reference_screen.dart
│   │   │   │   │   ├── my_archives_screen.dart
│   │   │   │   │   ├── my_findings_screen.dart
│   │   │   │   │   ├── notifications_screen.dart
│   │   │   │   │   └── upload_evidence_screen.dart
│   │   │   │   ├── auditor/
│   │   │   │   │   ├── archive_review_screen.dart
│   │   │   │   │   ├── auditor_dashboard_screen.dart
│   │   │   │   │   ├── auditor_notifications_screen.dart
│   │   │   │   │   ├── check_detail_screen.dart
│   │   │   │   │   ├── checks_list_screen.dart
│   │   │   │   │   ├── corrective_board_screen.dart
│   │   │   │   │   └── create_check_screen.dart
│   │   │   │   ├── auth/
│   │   │   │   │   ├── forgot_password_screen.dart
│   │   │   │   │   ├── landing_screen.dart
│   │   │   │   │   └── login_screen.dart
│   │   │   │   ├── common/
│   │   │   │   │   ├── forbidden_403_screen.dart
│   │   │   │   │   └── profile_screen.dart
│   │   │   │   └── super_admin/
│   │   │   │       ├── role_management_screen.dart
│   │   │   │       ├── settings_readonly_screen.dart
│   │   │   │       ├── super_admin_dashboard_screen.dart
│   │   │   │       ├── system_logs_screen.dart
│   │   │   │       └── user_management_screen.dart
│   │   │   └── widgets/
│   │   │       ├── admin_page_header.dart
│   │   │       ├── auditor_page_header.dart
│   │   │       ├── compliance_gauge.dart
│   │   │       ├── empty_state.dart
│   │   │       ├── encryption_indicator.dart
│   │   │       ├── kpi_card.dart
│   │   │       ├── manage_on_web_badge.dart
│   │   │       ├── role_badge.dart
│   │   │       ├── status_badge.dart
│   │   │       ├── timeline_stepper.dart
│   │   │       ├── ustp_app_bar.dart
│   │   │       └── ustp_logo.dart
│   │   ├── app.dart
│   │   └── main.dart
│   ├── test/
│   │   ├── file_validator_test.dart
│   │   ├── models_test.dart
│   │   ├── role_helper_test.dart
│   │   └── widget_test.dart
│   ├── web/
│   │   ├── icons/
│   │   │   ├── Icon-192.png
│   │   │   ├── Icon-512.png
│   │   │   ├── Icon-maskable-192.png
│   │   │   └── Icon-maskable-512.png
│   │   ├── favicon.png
│   │   ├── index.html
│   │   └── manifest.json
│   ├── .gitignore
│   ├── .metadata
│   ├── analysis_options.yaml
│   ├── devtools_options.yaml
│   ├── pubspec.lock
│   ├── pubspec.yaml
│   └── README.md
├── public/
│   ├── css/
│   │   ├── admin-portal.css
│   │   ├── auditee-portal.css
│   │   ├── auditor-portal.css
│   │   ├── auth-login.css
│   │   └── super-admin-portal.css
│   ├── fonts/
│   │   └── .gitkeep
│   ├── images/
│   │   ├── .gitkeep
│   │   ├── DALE.jpg
│   │   └── ustp-claveria-logo.jpg
│   ├── .htaccess
│   ├── favicon.ico
│   ├── index.php
│   └── robots.txt
├── realtime/
│   ├── src/
│   │   ├── config/
│   │   │   ├── db.js
│   │   │   ├── env.js
│   │   │   └── firebase.js
│   │   ├── models/
│   │   │   └── notification.js
│   │   ├── services/
│   │   │   ├── fcmService.js
│   │   │   └── notificationService.js
│   │   ├── sockets/
│   │   │   ├── auth.js
│   │   │   └── index.js
│   │   ├── utils/
│   │   │   └── logger.js
│   │   ├── watchers/
│   │   │   └── complianceChecksWatcher.js
│   │   └── server.js
│   ├── test/
│   │   └── watchers.test.js
│   ├── .env.example
│   ├── .gitignore
│   ├── package-lock.json
│   ├── package.json
│   └── README.md
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   ├── bootstrap.js
│   │   └── pages.js
│   └── views/
│       ├── admin/
│       │   ├── archives.blade.php
│       │   ├── compliance-records.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── departments.blade.php
│       │   ├── notifications.blade.php
│       │   ├── reports.blade.php
│       │   └── users.blade.php
│       ├── auditee/
│       │   ├── archives.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── finding-details.blade.php
│       │   ├── help.blade.php
│       │   ├── my-findings.blade.php
│       │   ├── notifications.blade.php
│       │   └── upload.blade.php
│       ├── auditor/
│       │   ├── archives.blade.php
│       │   ├── check-create.blade.php
│       │   ├── check-details.blade.php
│       │   ├── checks.blade.php
│       │   ├── corrective-actions.blade.php
│       │   ├── dashboard.blade.php
│       │   └── notifications.blade.php
│       ├── auth/
│       │   ├── brand-panel.blade.php
│       │   ├── forgot-password.blade.php
│       │   ├── login.blade.php
│       │   ├── profile.blade.php
│       │   └── reset-password.blade.php
│       ├── components/
│       │   ├── alert-banner.blade.php
│       │   ├── compliance-gauge.blade.php
│       │   ├── dashboard-page.blade.php
│       │   ├── data-table.blade.php
│       │   ├── kpi-card.blade.php
│       │   ├── resource-page.blade.php
│       │   ├── role-badge.blade.php
│       │   ├── status-badge.blade.php
│       │   ├── timeline-stepper.blade.php
│       │   └── upload-zone.blade.php
│       ├── emails/
│       │   └── reset-password.blade.php
│       ├── errors/
│       │   ├── 403.blade.php
│       │   └── 404.blade.php
│       ├── layouts/
│       │   └── app.blade.php
│       ├── partials/
│       │   ├── navbar.blade.php
│       │   └── sidebar.blade.php
│       ├── super-admin/
│       │   ├── archives.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── departments.blade.php
│       │   ├── logs.blade.php
│       │   ├── roles.blade.php
│       │   ├── settings.blade.php
│       │   └── users.blade.php
│       └── welcome.blade.php
├── routes/
│   ├── api.php
│   ├── console.php
│   └── web.php
├── scripts/
│   ├── bump-version.js
│   └── bump-version.test.js
├── storage/
│   ├── app/
│   │   ├── private/
│   │   │   └── .gitignore
│   │   ├── public/
│   │   │   └── .gitignore
│   │   └── .gitignore
│   ├── framework/
│   │   ├── cache/
│   │   │   ├── data/
│   │   │   │   └── .gitignore
│   │   │   └── .gitignore
│   │   ├── sessions/
│   │   │   └── .gitignore
│   │   ├── testing/
│   │   │   └── .gitignore
│   │   ├── views/
│   │   │   └── .gitignore
│   │   └── .gitignore
│   └── logs/
│       └── .gitignore
├── tests/
│   ├── Feature/
│   │   └── ExampleTest.php
│   ├── Unit/
│   │   └── ExampleTest.php
│   └── TestCase.php
├── .editorconfig
├── .env.example
├── .gitattributes
├── .gitignore
├── artisan
├── CHANGELOG.md
├── composer.json
├── composer.lock
├── docker-compose.yml
├── FILE_STRUCTURE.md
├── LICENSE
├── package-lock.json
├── package.json
├── phpunit.xml
├── README.md
├── start-complitrack.ps1
├── stop-complitrack.ps1
└── vite.config.js
```
