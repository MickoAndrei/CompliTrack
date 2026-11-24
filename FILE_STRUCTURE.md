# CompliTrack — File-by-File Structure

This listing contains every Git-tracked file in the repository.

```text
CompliTrack/
├── .editorconfig
├── .env.example
├── .gitattributes
├── .github
│   └── workflows
│       ├── build.yml
│       ├── deploy-backend.yml
│       └── main.yml
├── .gitignore
├── app
│   ├── Casts
│   │   ├── NullableObjectId.php
│   │   └── ObjectIdArray.php
│   ├── Console
│   │   └── Commands
│   │       ├── CheckDocumentServer.php
│   │       ├── CheckResetMail.php
│   │       └── MigrateProfileImages.php
│   ├── Helpers
│   │   └── RoleHelper.php
│   ├── Http
│   │   ├── Controllers
│   │   │   ├── Api
│   │   │   │   └── V1
│   │   │   │       ├── ArchiveController.php
│   │   │   │       ├── AuditLogController.php
│   │   │   │       ├── AuditPlanController.php
│   │   │   │       ├── AuthController.php
│   │   │   │       ├── ComplianceCheckController.php
│   │   │   │       ├── DatabaseStatsController.php
│   │   │   │       ├── DepartmentController.php
│   │   │   │       ├── DocumentEditorController.php
│   │   │   │       ├── NotificationController.php
│   │   │   │       ├── QmsArchiveController.php
│   │   │   │       ├── QmsFolderController.php
│   │   │   │       ├── ReportController.php
│   │   │   │       ├── RolePermissionController.php
│   │   │   │       ├── SettingsController.php
│   │   │   │       ├── SuperAdminDashboardController.php
│   │   │   │       └── UserController.php
│   │   │   ├── Controller.php
│   │   │   └── Web
│   │   │       ├── AtlWorkspaceController.php
│   │   │       ├── AuditController.php
│   │   │       ├── AuditeeController.php
│   │   │       ├── AuthController.php
│   │   │       ├── DashboardController.php
│   │   │       ├── HistoricalComplianceController.php
│   │   │       └── ProfileController.php
│   │   └── Middleware
│   │       ├── AuditLogMiddleware.php
│   │       ├── EnsureUserIsActive.php
│   │       ├── RoleMiddleware.php
│   │       ├── SystemAccess.php
│   │       ├── UseTabSession.php
│   │       └── UseTunnelAssets.php
│   ├── Mail
│   │   ├── MicrosoftSmtpOAuth.php
│   │   └── PhpMailerTransport.php
│   ├── Models
│   │   ├── ArchiveDocument.php
│   │   ├── Audit.php
│   │   ├── AuditLog.php
│   │   ├── Campus.php
│   │   ├── College.php
│   │   ├── ComplianceCheck.php
│   │   ├── Department.php
│   │   ├── Notification.php
│   │   ├── PasswordResetToken.php
│   │   ├── PersonalAccessToken.php
│   │   ├── QmsCategory.php
│   │   ├── Role.php
│   │   └── User.php
│   ├── Notifications
│   │   └── PasswordResetLink.php
│   ├── Providers
│   │   ├── AppServiceProvider.php
│   │   └── RateLimitServiceProvider.php
│   ├── Services
│   │   ├── AnnualAuditReport.php
│   │   ├── ArchiveEncryptionService.php
│   │   ├── ArchiveKeyRotationService.php
│   │   ├── AtlWorkspace.php
│   │   ├── AuditAssignment.php
│   │   ├── AuditChecklist.php
│   │   ├── AuditeeEvidenceService.php
│   │   ├── AuditLogService.php
│   │   ├── AuditorAuditListing.php
│   │   ├── AuditPlanDocument.php
│   │   ├── AuditPlanPermissions.php
│   │   ├── AuditPlanWorkflow.php
│   │   ├── AuditReviewQueue.php
│   │   ├── CarPermissions.php
│   │   ├── CarRegister.php
│   │   ├── ComplianceCheckService.php
│   │   ├── CorrectiveActionPresenter.php
│   │   ├── CounterService.php
│   │   ├── DatabaseSwitchPermission.php
│   │   ├── DatabaseSwitchService.php
│   │   ├── DocumentEditorAccess.php
│   │   ├── DocumentEditorToken.php
│   │   ├── MockArchiveImporter.php
│   │   ├── MongoConnectionProbe.php
│   │   ├── NotificationDispatchService.php
│   │   ├── PasswordResetService.php
│   │   ├── PositionScope.php
│   │   ├── ProfileImageService.php
│   │   ├── QmsArchiveAccess.php
│   │   ├── QmsArchiveMigration.php
│   │   ├── QmsArchivePresenter.php
│   │   ├── QmsAuditArchive.php
│   │   ├── QmsBrowser.php
│   │   ├── QmsCampusReferences.php
│   │   ├── QmsCycleFolders.php
│   │   ├── QmsCycleRepository.php
│   │   ├── QmsDevelopmentData.php
│   │   ├── QmsDocumentActions.php
│   │   ├── QmsFolderAccess.php
│   │   ├── QmsOversightService.php
│   │   ├── QmsRecentDocuments.php
│   │   ├── QmsRepositoryPolicy.php
│   │   ├── QmsRequestReferences.php
│   │   ├── ReportGeneratorService.php
│   │   ├── RoleScopeService.php
│   │   ├── SuperAdminDashboard.php
│   │   ├── SystemBackupService.php
│   │   ├── SystemOperationLock.php
│   │   ├── SystemSettingsService.php
│   │   ├── SystemWipeService.php
│   │   ├── UserManagementPresenter.php
│   │   └── UserOrganization.php
│   └── Support
│       ├── AuditLogFields.php
│       ├── AuditStatus.php
│       ├── CarStatus.php
│       ├── CarTimeline.php
│       ├── EvidenceRequestStatus.php
│       ├── IndependenceProcedure.php
│       ├── MongoId.php
│       └── PortalDate.php
├── artisan
├── bootstrap
│   ├── app.php
│   ├── cache
│   │   ├── .gitignore
│   │   ├── packages.php
│   │   └── services.php
│   └── providers.php
├── CHANGELOG.md
├── composer.json
├── composer.lock
├── config
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── cors.php
│   ├── database.php
│   ├── documents.php
│   ├── filesystems.php
│   ├── help.php
│   ├── jwt.php
│   ├── logging.php
│   ├── mail.php
│   ├── qms.php
│   ├── queue.php
│   ├── sanctum.php
│   ├── services.php
│   ├── session.php
│   └── system.php
├── database
│   ├── .gitignore
│   ├── factories
│   │   └── UserFactory.php
│   ├── migrations
│   │   ├── 2026_11_14_150000_index_qms_user_subfolders.php
│   │   ├── 2026_11_14_160000_add_qms_campus_references.php
│   │   ├── 2026_11_16_000000_remove_department_secretary_position.php
│   │   ├── 2026_11_16_190000_index_manual_qms_repository_folders.php
│   │   └── 2026_11_18_120000_create_qms_cycle_folders.php
│   ├── mongodb
│   │   ├── audit-indexes.js
│   │   ├── init.js
│   │   └── seed.js
│   └── seeders
│       ├── ArchiveDemoSeeder.php
│       ├── DatabaseSeeder.php
│       ├── DemoDataSeeder.php
│       ├── DepartmentSeeder.php
│       ├── QmsDevelopmentSeeder.php
│       ├── RoleSeeder.php
│       └── SuperAdminSeeder.php
├── dataset_files
│   └── CompliTrack_CET_Demo_Kit-3
│       ├── complitrack_cet_demo.json
│       ├── documents
│       │   ├── 00_Audit_Management
│       │   │   └── DEMO-FM-USTP-CET-AUD-16_Opening_and_Closing_Attendance.docx
│       │   ├── 99_Audit_Findings_and_Dispositions
│       │   │   ├── DEMO-FM-USTP-009-2_CAR_Log_2026-09-02.docx
│       │   │   └── DEMO-FM-USTP-009-2_CAR_Log_2026-09-11.docx
│       │   ├── Academics
│       │   │   ├── 1.2_Approved_OBE_Syllabi
│       │   │   │   ├── DEMO-FM-USTP-ACAD-01_Systems_Integration_BSIT_DEMO_3A.docx
│       │   │   │   ├── DEMO-FM-USTP-CET-AUD-02_Syllabus_Review_and_Approval.docx
│       │   │   │   └── FM-USTP-CET-01_Syllabus_Review_and_Release.docx
│       │   │   ├── 1.3_Instructional_Materials_Validation
│       │   │   │   ├── DEMO-FM-USTP-CET-AUD-13_Instructional_Materials_Validation.docx
│       │   │   │   ├── DEMO-FM-USTP-CET-AUD-14_Assessment_Table_of_Specifications_Quiz_02.docx
│       │   │   │   └── FM-USTP-CET-02_Class_Delivery_and_Assessment.docx
│       │   │   ├── 1.4_Class_Attendance_and_Monitoring
│       │   │   │   ├── DEMO-FM-USTP-CET-AUD-11_Student_Class_Attendance_Register.docx
│       │   │   │   └── DEMO-FM-USTP-CET-AUD-12_Faculty_Attendance_and_Class_Monitoring.docx
│       │   │   ├── DEMO-FM-USTP-CET-AUD-15_Course_File_Index_and_Completeness.docx
│       │   │   └── DPM-USTP-CET-001_Management_of_Learning.docx
│       │   ├── CAR-DEMO-001_Corrective_Action_Request.docx
│       │   ├── CAR-DEMO-002_Corrective_Action_Request.docx
│       │   ├── CAR-DEMO-003_Corrective_Action_Request.docx
│       │   ├── CET_Document_and_Record_Master_List.docx
│       │   ├── DPM-USTP-CET-002_Computer_Laboratory_Readiness.docx
│       │   ├── FM-USTP-CET-03_Laboratory_Readiness.docx
│       │   ├── FM-USTP-CET-04_Maintenance_and_Fault_Log.docx
│       │   ├── FM-USTP-CET-05_CE-001_Corrective_Evidence.docx
│       │   ├── FM-USTP-CET-05_CE-002_Corrective_Evidence.docx
│       │   ├── IQA-2026-001-DEMO_Audit_Plan.docx
│       │   ├── IQA-2026-001-DEMO_Audit_Report.docx
│       │   ├── IQA-2026-001-DEMO_Auditor_Checklist.docx
│       │   ├── IQA-2026-002-DEMO_Audit_Plan.docx
│       │   └── IQA-2026-002-DEMO_Auditor_Checklist.docx
│       └── START_HERE.md
├── docker-compose.documents.yml
├── docker-compose.yml
├── docs
│   ├── ADMIN_AUDIT_REVIEWS_REDESIGN.md
│   ├── ATL_WORKSPACE_IMPLEMENTATION.md
│   ├── AUDIT_ASSIGNMENTS_AND_REVIEWS.md
│   ├── AUDIT_PLAN_AUTHORIZATION.md
│   ├── AUDITEE_IMPLEMENTATION_REPORT.md
│   ├── AUDITOR_ASSIGNMENT_INVESTIGATION.md
│   ├── AUDITOR_AUDIT_WORKSPACE.md
│   ├── AUDITOR_CORRECTIVE_ACTIONS.md
│   ├── AUDITOR_DATABASE_UPDATE_REPORT.md
│   ├── AUDITOR_EVALUATION_CONSOLIDATION.md
│   ├── AUDITOR_EXECUTION_REPORT.md
│   ├── AUDITOR_IMPLEMENTATION_REPORT.md
│   ├── BROWSER_TAB_SESSIONS.md
│   ├── CAR_MONITORING_REDESIGN.md
│   ├── DATABASE_SWITCH_MAINTENANCE.md
│   ├── DEMO_IT_COLLEGE_CORRECTION_REPORT.md
│   ├── DIRECTORY_LAYOUT.md
│   ├── DOCUMENT_PREVIEW_AND_EDITING.md
│   ├── HELP_CENTERS.md
│   ├── JWT_MECHANISM_REVIEW.md
│   ├── MY_ASSIGNED_AUDITS_UI.md
│   ├── NOTIFICATIONS_AND_ACTIVITY_LOGS.md
│   ├── ONLYOFFICE_INTEGRATION.md
│   ├── PASSWORD_RESET_EMAIL.md
│   ├── PERFORMANCE_OPTIMIZATION.md
│   ├── PERFORMANCE_PHASE_2.md
│   ├── QMS_ARCHIVES_IMPLEMENTATION.md
│   ├── QMS_AUDIT_PLAN_ARCHIVE.md
│   ├── QMS_AUTOMATIC_ACCESS_REDESIGN.md
│   ├── QMS_DEVELOPMENT_DATA.md
│   ├── QMS_DOCUMENT_BROWSER_REDESIGN.md
│   ├── QMS_DOCUMENT_MANAGER_IMPROVEMENTS.md
│   ├── QMS_DOCUMENTS_PERFORMANCE.md
│   ├── QMS_FILE_STRUCTURE.md
│   ├── QMS_FOLDER_MANAGEMENT.md
│   ├── REALTIME_FIREBASE_REMOVAL_REPORT.md
│   ├── SUPER_ADMIN_DASHBOARD.md
│   ├── USER_MANAGEMENT_IMPLEMENTATION.md
│   └── WALKTHROUGH_FIXES.md
├── DOCUMENTATION.md
├── FILE_STRUCTURE.md
├── LICENSE
├── mobile
│   ├── .flutter-plugins-dependencies
│   ├── .gitignore
│   ├── .metadata
│   ├── .vscode
│   │   └── launch.json
│   ├── analysis_options.yaml
│   ├── android
│   │   ├── .gitignore
│   │   ├── app
│   │   │   ├── build.gradle.kts
│   │   │   └── src
│   │   │       ├── debug
│   │   │       │   └── AndroidManifest.xml
│   │   │       ├── main
│   │   │       │   ├── AndroidManifest.xml
│   │   │       │   ├── kotlin
│   │   │       │   │   └── ph
│   │   │       │   │       └── edu
│   │   │       │   │           └── ustp
│   │   │       │   │               └── complitrack
│   │   │       │   │                   └── MainActivity.kt
│   │   │       │   └── res
│   │   │       │       ├── drawable
│   │   │       │       │   └── launch_background.xml
│   │   │       │       ├── drawable-v21
│   │   │       │       │   └── launch_background.xml
│   │   │       │       ├── mipmap-hdpi
│   │   │       │       │   └── ic_launcher.png
│   │   │       │       ├── mipmap-mdpi
│   │   │       │       │   └── ic_launcher.png
│   │   │       │       ├── mipmap-xhdpi
│   │   │       │       │   └── ic_launcher.png
│   │   │       │       ├── mipmap-xxhdpi
│   │   │       │       │   └── ic_launcher.png
│   │   │       │       ├── mipmap-xxxhdpi
│   │   │       │       │   └── ic_launcher.png
│   │   │       │       ├── values
│   │   │       │       │   └── styles.xml
│   │   │       │       └── values-night
│   │   │       │           └── styles.xml
│   │   │       └── profile
│   │   │           └── AndroidManifest.xml
│   │   ├── build.gradle.kts
│   │   ├── gradle
│   │   │   └── wrapper
│   │   │       └── gradle-wrapper.properties
│   │   ├── gradle.properties
│   │   └── settings.gradle.kts
│   ├── assets
│   │   ├── fonts
│   │   │   └── .gitkeep
│   │   └── images
│   │       ├── .gitkeep
│   │       └── ustp-logo-mark.png
│   ├── devtools_options.yaml
│   ├── ios
│   │   ├── .gitignore
│   │   ├── Flutter
│   │   │   ├── AppFrameworkInfo.plist
│   │   │   ├── Debug.xcconfig
│   │   │   └── Release.xcconfig
│   │   ├── Podfile
│   │   ├── Runner
│   │   │   ├── AppDelegate.swift
│   │   │   ├── Assets.xcassets
│   │   │   │   ├── AppIcon.appiconset
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
│   │   │   │   └── LaunchImage.imageset
│   │   │   │       ├── Contents.json
│   │   │   │       ├── LaunchImage.png
│   │   │   │       ├── LaunchImage@2x.png
│   │   │   │       ├── LaunchImage@3x.png
│   │   │   │       └── README.md
│   │   │   ├── Base.lproj
│   │   │   │   ├── LaunchScreen.storyboard
│   │   │   │   └── Main.storyboard
│   │   │   ├── Info.plist
│   │   │   ├── Runner-Bridging-Header.h
│   │   │   └── SceneDelegate.swift
│   │   ├── Runner.xcodeproj
│   │   │   ├── project.pbxproj
│   │   │   ├── project.xcworkspace
│   │   │   │   ├── contents.xcworkspacedata
│   │   │   │   └── xcshareddata
│   │   │   │       ├── IDEWorkspaceChecks.plist
│   │   │   │       └── WorkspaceSettings.xcsettings
│   │   │   └── xcshareddata
│   │   │       └── xcschemes
│   │   │           └── Runner.xcscheme
│   │   ├── Runner.xcworkspace
│   │   │   ├── contents.xcworkspacedata
│   │   │   └── xcshareddata
│   │   │       ├── IDEWorkspaceChecks.plist
│   │   │       └── WorkspaceSettings.xcsettings
│   │   └── RunnerTests
│   │       └── RunnerTests.swift
│   ├── lib
│   │   ├── app.dart
│   │   ├── core
│   │   │   ├── constants
│   │   │   │   ├── api_constants.dart
│   │   │   │   ├── app_colors.dart
│   │   │   │   ├── app_icons.dart
│   │   │   │   └── app_strings.dart
│   │   │   ├── network
│   │   │   │   ├── api_client.dart
│   │   │   │   ├── api_response.dart
│   │   │   │   └── auth_interceptor.dart
│   │   │   ├── services
│   │   │   │   ├── avatar_cache.dart
│   │   │   │   ├── secure_storage_service.dart
│   │   │   │   └── socket_service.dart
│   │   │   ├── theme
│   │   │   │   └── ustp_theme.dart
│   │   │   └── utils
│   │   │       ├── car_status.dart
│   │   │       ├── date_formatter.dart
│   │   │       ├── file_validator.dart
│   │   │       ├── role_helper.dart
│   │   │       └── validators.dart
│   │   ├── data
│   │   │   ├── models
│   │   │   │   ├── archive_document_model.dart
│   │   │   │   ├── audit_log_model.dart
│   │   │   │   ├── compliance_check_model.dart
│   │   │   │   ├── department_model.dart
│   │   │   │   ├── model_json.dart
│   │   │   │   ├── notification_model.dart
│   │   │   │   ├── role_model.dart
│   │   │   │   ├── super_admin_dashboard_model.dart
│   │   │   │   └── user_model.dart
│   │   │   └── repositories
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
│   │   ├── main.dart
│   │   └── presentation
│   │       ├── navigation
│   │       │   ├── admin_shell.dart
│   │       │   ├── app_router.dart
│   │       │   ├── auditee_shell.dart
│   │       │   ├── auditor_shell.dart
│   │       │   └── super_admin_shell.dart
│   │       ├── providers
│   │       │   └── auth_provider.dart
│   │       ├── screens
│   │       │   ├── admin
│   │       │   │   ├── admin_dashboard_screen.dart
│   │       │   │   ├── admin_notifications_screen.dart
│   │       │   │   ├── admin_user_management_screen.dart
│   │       │   │   ├── archive_repository_screen.dart
│   │       │   │   ├── broadcast_notification_dialog.dart
│   │       │   │   ├── department_edit_form.dart
│   │       │   │   ├── department_management_screen.dart
│   │       │   │   └── reports_view_screen.dart
│   │       │   ├── auditee
│   │       │   │   ├── auditee_dashboard_screen.dart
│   │       │   │   ├── finding_detail_screen.dart
│   │       │   │   ├── help_reference_screen.dart
│   │       │   │   ├── my_archives_screen.dart
│   │       │   │   ├── my_findings_screen.dart
│   │       │   │   ├── notifications_screen.dart
│   │       │   │   └── upload_evidence_screen.dart
│   │       │   ├── auditor
│   │       │   │   ├── archive_review_screen.dart
│   │       │   │   ├── auditor_dashboard_screen.dart
│   │       │   │   ├── auditor_notifications_screen.dart
│   │       │   │   ├── check_detail_screen.dart
│   │       │   │   ├── checks_list_screen.dart
│   │       │   │   ├── corrective_board_screen.dart
│   │       │   │   └── create_check_screen.dart
│   │       │   ├── auth
│   │       │   │   ├── forgot_password_screen.dart
│   │       │   │   ├── landing_screen.dart
│   │       │   │   ├── login_screen.dart
│   │       │   │   └── reset_password_screen.dart
│   │       │   ├── common
│   │       │   │   ├── forbidden_403_screen.dart
│   │       │   │   └── profile_screen.dart
│   │       │   └── super_admin
│   │       │       ├── role_management_screen.dart
│   │       │       ├── settings_readonly_screen.dart
│   │       │       ├── super_admin_dashboard_screen.dart
│   │       │       ├── super_admin_user_edit_form.dart
│   │       │       ├── system_logs_screen.dart
│   │       │       └── user_management_screen.dart
│   │       └── widgets
│   │           ├── admin_page_header.dart
│   │           ├── auditor_page_header.dart
│   │           ├── auth_brand_header.dart
│   │           ├── compliance_gauge.dart
│   │           ├── current_user_avatar.dart
│   │           ├── empty_state.dart
│   │           ├── encryption_indicator.dart
│   │           ├── kpi_card.dart
│   │           ├── manage_on_web_badge.dart
│   │           ├── profile_picture_dialog.dart
│   │           ├── role_badge.dart
│   │           ├── status_badge.dart
│   │           ├── timeline_stepper.dart
│   │           ├── ustp_app_bar.dart
│   │           └── ustp_logo.dart
│   ├── pubspec.lock
│   ├── pubspec.yaml
│   ├── README.md
│   ├── test
│   │   ├── admin_directory_access_test.dart
│   │   ├── auditor_help_topics_test.dart
│   │   ├── avatar_cache_test.dart
│   │   ├── corrective_board_test.dart
│   │   ├── department_edit_form_test.dart
│   │   ├── department_repository_test.dart
│   │   ├── file_validator_test.dart
│   │   ├── models_test.dart
│   │   ├── role_helper_test.dart
│   │   ├── super_admin_repository_test.dart
│   │   ├── system_health_repository_test.dart
│   │   ├── walkthrough_rules_test.dart
│   │   └── widget_test.dart
│   └── web
│       ├── favicon.png
│       ├── icons
│       │   ├── Icon-192.png
│       │   ├── Icon-512.png
│       │   ├── Icon-maskable-192.png
│       │   └── Icon-maskable-512.png
│       ├── index.html
│       └── manifest.json
├── package-lock.json
├── package.json
├── phpunit.audit.xml
├── phpunit.xml
├── public
│   ├── .htaccess
│   ├── css
│   │   ├── admin-portal.css
│   │   ├── admin-ui.css
│   │   ├── audit-assignments.css
│   │   ├── audit-plans.css
│   │   ├── audit-workspace.css
│   │   ├── auditee-evidence.css
│   │   ├── auditee-help.css
│   │   ├── auditee-portal.css
│   │   ├── auditor-portal.css
│   │   ├── auditor-ui.css
│   │   ├── auth-login.css
│   │   ├── car-details.css
│   │   ├── car-register.css
│   │   ├── corrective-actions.css
│   │   ├── fonts.css
│   │   ├── my-assigned-audits.css
│   │   ├── page-notices.css
│   │   ├── pagination.css
│   │   ├── portal-header.css
│   │   ├── portal-typography.css
│   │   ├── profile.css
│   │   ├── qms-archives.css
│   │   ├── sidebar.css
│   │   ├── super-admin-dashboard.css
│   │   ├── super-admin-portal.css
│   │   ├── system-settings.css
│   │   └── user-management.css
│   ├── favicon.ico
│   ├── fonts
│   │   ├── .gitkeep
│   │   ├── inter
│   │   │   ├── inter-1be3448e292f.woff2
│   │   │   ├── inter-3100e775e861.woff2
│   │   │   ├── inter-34b9c504cab7.woff2
│   │   │   ├── inter-5c66f9e07e90.woff2
│   │   │   ├── inter-6e9e020a25f9.woff2
│   │   │   ├── inter-71d5ee93cc1e.woff2
│   │   │   ├── inter-ca157063339a.woff2
│   │   │   └── OFL.txt
│   │   ├── plusjakartasans
│   │   │   ├── OFL.txt
│   │   │   ├── plusjakartasans-153fc85b7029.woff2
│   │   │   ├── plusjakartasans-38e3b8fd8045.woff2
│   │   │   ├── plusjakartasans-b275d1258601.woff2
│   │   │   └── plusjakartasans-c46a510ab439.woff2
│   │   ├── poppins
│   │   │   ├── OFL.txt
│   │   │   ├── poppins-58dba357cb1e.woff2
│   │   │   ├── poppins-9338e65fc077.woff2
│   │   │   ├── poppins-bb1f2d582e7f.woff2
│   │   │   ├── poppins-ccfd87f69ef0.woff2
│   │   │   ├── poppins-de10eb6f1f45.woff2
│   │   │   └── poppins-f4e80d9dfd37.woff2
│   │   └── sources.json
│   ├── images
│   │   ├── .gitkeep
│   │   ├── DALE.jpg
│   │   ├── qms-folder.svg
│   │   └── ustp-claveria-logo.jpg
│   ├── index.php
│   ├── js
│   │   └── tab-session.js
│   └── robots.txt
├── README.md
├── realtime
│   ├── .env.example
│   ├── .gitignore
│   ├── NGROK.md
│   ├── package-lock.json
│   ├── package.json
│   ├── README.md
│   ├── src
│   │   ├── config
│   │   │   ├── db.js
│   │   │   └── env.js
│   │   ├── devProxy.js
│   │   ├── models
│   │   │   └── notification.js
│   │   ├── server.js
│   │   ├── sockets
│   │   │   ├── auth.js
│   │   │   └── index.js
│   │   ├── staticAssets.js
│   │   ├── utils
│   │   │   └── logger.js
│   │   └── watchers
│   │       └── complianceChecksWatcher.js
│   └── test
│       ├── devProxy.test.js
│       ├── mongo.test.js
│       ├── socket.test.js
│       └── watchers.test.js
├── resources
│   ├── css
│   │   ├── app.css
│   │   ├── archive-preview.css
│   │   ├── attention.css
│   │   └── directories.css
│   ├── js
│   │   ├── annual-report.js
│   │   ├── app.js
│   │   ├── archive-comparison.js
│   │   ├── atl-workspace.js
│   │   ├── audit-assignments.js
│   │   ├── audit-plans.js
│   │   ├── audit-status.js
│   │   ├── audit-workspace.js
│   │   ├── auditee-evidence-preview.js
│   │   ├── auditee-evidence-requests.js
│   │   ├── auditee-help.js
│   │   ├── auditee-result-modal.js
│   │   ├── auditee-results.js
│   │   ├── auditor-overview.js
│   │   ├── bootstrap.js
│   │   ├── car-history.js
│   │   ├── corrective-actions.js
│   │   ├── document-editor.js
│   │   ├── dom-ready.js
│   │   ├── icons.js
│   │   ├── my-assigned-audits.js
│   │   ├── notification-data.js
│   │   ├── page-notices.js
│   │   ├── page-work.js
│   │   ├── pages.js
│   │   ├── portal-date.js
│   │   ├── qms-archives.js
│   │   ├── super-admin-dashboard.js
│   │   ├── system-settings.js
│   │   └── user-management.js
│   └── views
│       ├── admin
│       │   ├── archives.blade.php
│       │   ├── atl-dashboard.blade.php
│       │   ├── audit-assignments.blade.php
│       │   ├── audit-review.blade.php
│       │   ├── audit-reviews.blade.php
│       │   ├── car-log-export.blade.php
│       │   ├── compliance-records.blade.php
│       │   ├── corrective-monitor.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── departments.blade.php
│       │   ├── notifications.blade.php
│       │   ├── partials
│       │   │   └── car-history.blade.php
│       │   ├── reports.blade.php
│       │   └── users.blade.php
│       ├── audit-plans
│       │   └── index.blade.php
│       ├── auditee
│       │   ├── archives.blade.php
│       │   ├── car-timeline.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── evidence-preview-modal.blade.php
│       │   ├── evidence-request-detail.blade.php
│       │   ├── evidence-requests.blade.php
│       │   ├── evidence-response-form.blade.php
│       │   ├── help.blade.php
│       │   ├── my-findings.blade.php
│       │   ├── notifications.blade.php
│       │   ├── organizational-oversight.blade.php
│       │   ├── result-detail-content.blade.php
│       │   └── result-modal.blade.php
│       ├── auditor
│       │   ├── archives.blade.php
│       │   ├── audit-feedback.blade.php
│       │   ├── audit-workspace.blade.php
│       │   ├── audits.blade.php
│       │   ├── check-create.blade.php
│       │   ├── check-details.blade.php
│       │   ├── checks.blade.php
│       │   ├── corrective-actions.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── evidence-requests.blade.php
│       │   ├── notifications.blade.php
│       │   ├── partials
│       │   │   ├── assigned-audit-fragment.blade.php
│       │   │   ├── assigned-audit-results.blade.php
│       │   │   └── assigned-audit-summary.blade.php
│       │   ├── profile.blade.php
│       │   └── workspace-modals.blade.php
│       ├── auth
│       │   ├── brand-panel.blade.php
│       │   ├── forgot-password.blade.php
│       │   ├── login.blade.php
│       │   ├── profile.blade.php
│       │   └── reset-password.blade.php
│       ├── components
│       │   ├── activity-logs.blade.php
│       │   ├── alert-banner.blade.php
│       │   ├── atl-overview-skeleton.blade.php
│       │   ├── atl-review-modal.blade.php
│       │   ├── car-details-skeleton.blade.php
│       │   ├── compliance-gauge.blade.php
│       │   ├── dashboard-page.blade.php
│       │   ├── dashboard-skeleton.blade.php
│       │   ├── data-table.blade.php
│       │   ├── kpi-card.blade.php
│       │   ├── notification-dropdown.blade.php
│       │   ├── notification-inbox.blade.php
│       │   ├── organizational-responsibility.blade.php
│       │   ├── pagination-controls.blade.php
│       │   ├── partials
│       │   │   ├── attention-pagination.blade.php
│       │   │   └── notification-broadcast.blade.php
│       │   ├── qms-archives.blade.php
│       │   ├── qms-file-upload.blade.php
│       │   ├── qms-folder-row-heading.blade.php
│       │   ├── qms-folder-row-metadata.blade.php
│       │   ├── qms-folder-shortcuts.blade.php
│       │   ├── qms-loading-skeleton.blade.php
│       │   ├── resource-page.blade.php
│       │   ├── role-badge.blade.php
│       │   ├── status-badge.blade.php
│       │   ├── timeline-stepper.blade.php
│       │   └── upload-zone.blade.php
│       ├── emails
│       │   └── reset-password.blade.php
│       ├── errors
│       │   ├── 403.blade.php
│       │   └── 404.blade.php
│       ├── help
│       │   ├── auditee-topics.blade.php
│       │   ├── index.blade.php
│       │   └── role-topics.blade.php
│       ├── layouts
│       │   └── app.blade.php
│       ├── partials
│       │   ├── navbar.blade.php
│       │   └── sidebar.blade.php
│       ├── super-admin
│       │   ├── archives.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── departments.blade.php
│       │   ├── logs.blade.php
│       │   ├── notifications.blade.php
│       │   ├── partials
│       │   │   └── user-management.blade.php
│       │   ├── roles.blade.php
│       │   ├── settings.blade.php
│       │   └── users.blade.php
│       └── welcome.blade.php
├── routes
│   ├── api.php
│   ├── console.php
│   └── web.php
├── scripts
│   ├── apply-attention-indexes.php
│   ├── apply-audit-indexes.php
│   ├── apply-auditee-evidence-index.php
│   ├── apply-notification-indexes.php
│   ├── apply-user-list-index.php
│   ├── bump-version.js
│   ├── bump-version.test.js
│   ├── correct-demo-it-college.php
│   ├── database-switch.ps1
│   ├── download-fonts.mjs
│   ├── generate-icon-registry.mjs
│   ├── import-mock-archives.php
│   ├── inspect-assignment-auditors.php
│   ├── inspect-assignment-plans.php
│   ├── inspect-atl-workspace.php
│   ├── inspect-audit-assignments.php
│   ├── inspect-audit-plan-authorization.php
│   ├── inspect-car-schema.php
│   ├── inspect-notification-setup.php
│   ├── inspect-qms-schema.php
│   ├── inspect-scenario-data.php
│   ├── inspect-super-admin-dashboard.php
│   ├── inspect-user-management.php
│   ├── inspect-walkthrough-observations.php
│   ├── migrate-qms-archives.php
│   ├── performance
│   │   ├── browser.cjs
│   │   ├── concurrency.cjs
│   │   ├── http.cjs
│   │   ├── RequestProfile.php
│   │   ├── router.php
│   │   └── storage.php
│   ├── php-development-server.ps1
│   ├── seed-qms-development.php
│   ├── sync-qms-cycle-folders.php
│   ├── verify-atl-workspace.php
│   ├── verify-qms-development.php
│   └── verify-qms-schema.php
├── start-complitrack-documents.ps1
├── start-complitrack-ngrok.ps1
├── start-complitrack.ps1
├── stop-complitrack.ps1
├── storage
│   ├── app
│   │   ├── .gitignore
│   │   ├── private
│   │   │   └── .gitignore
│   │   └── public
│   │       └── .gitignore
│   ├── framework
│   │   ├── .gitignore
│   │   ├── cache
│   │   │   ├── .gitignore
│   │   │   └── data
│   │   │       └── .gitignore
│   │   ├── sessions
│   │   │   └── .gitignore
│   │   ├── testing
│   │   │   └── .gitignore
│   │   └── views
│   │       └── .gitignore
│   └── logs
│       └── .gitignore
├── tests
│   ├── Browser
│   │   ├── admin-dashboard.cjs
│   │   ├── atl-workspace.cjs
│   │   ├── attention.cjs
│   │   ├── audit-assignments.cjs
│   │   ├── audit-log-export.cjs
│   │   ├── audit-reviews.cjs
│   │   ├── audit-workspace.cjs
│   │   ├── auditee-help.cjs
│   │   ├── car-register.cjs
│   │   ├── car-timeline.cjs
│   │   ├── corrective-actions.cjs
│   │   ├── evidence-preview.cjs
│   │   ├── evidence-requests.cjs
│   │   ├── live-retest.cjs
│   │   ├── my-assigned-audits.cjs
│   │   ├── notification-badge.cjs
│   │   ├── onlyoffice-live.cjs
│   │   ├── password-reset-smtp.cjs
│   │   ├── qms-archives.cjs
│   │   ├── qms-development-live.cjs
│   │   ├── super-admin-dashboard.cjs
│   │   ├── tab-session.cjs
│   │   ├── user-management.cjs
│   │   └── walkthrough.cjs
│   ├── Feature
│   │   ├── AdminPortalViewTest.php
│   │   ├── AssignedAuditsViewTest.php
│   │   ├── AtlWorkspaceMongoTest.php
│   │   ├── AuditAccessTest.php
│   │   ├── AuditeeDashboardViewTest.php
│   │   ├── AuditeeHelpViewTest.php
│   │   ├── AuditeeResultsViewTest.php
│   │   ├── AuditPlanApprovalMongoTest.php
│   │   ├── AuditWorkflowMongoTest.php
│   │   ├── CorrectiveActionsMongoTest.php
│   │   ├── DatabaseSwitchRequestTest.php
│   │   ├── DirectorySummaryMongoTest.php
│   │   ├── ExampleTest.php
│   │   ├── LiveRetestReportTest.php
│   │   ├── LoginEntryTest.php
│   │   ├── MongoConnectivityCommandTest.php
│   │   ├── NotificationActivityMongoTest.php
│   │   ├── OnlyOfficeMongoTest.php
│   │   ├── PasswordResetLinkTest.php
│   │   ├── PasswordResetMailTest.php
│   │   ├── ProfileImageDatabaseTest.php
│   │   ├── ProfileTest.php
│   │   ├── QmsArchivesMongoTest.php
│   │   ├── QmsAuditArchiveMongoTest.php
│   │   ├── QmsDocumentRoutesTest.php
│   │   ├── SuperAdminDashboardMongoTest.php
│   │   ├── SystemSettingsTest.php
│   │   ├── TabSessionTest.php
│   │   ├── TunnelAssetsTest.php
│   │   ├── UserManagementMongoTest.php
│   │   └── WalkthroughRegressionTest.php
│   ├── Fixtures
│   │   ├── attention.php
│   │   ├── auditee-help.php
│   │   ├── car-timeline.php
│   │   ├── corrective-actions.php
│   │   ├── evidence-requests.php
│   │   ├── onlyoffice-live-router.php
│   │   ├── onlyoffice-live.php
│   │   ├── password-reset-smtp.php
│   │   ├── qms-archives.php
│   │   ├── super-admin-dashboard.php
│   │   └── walkthrough-ui.php
│   ├── Support
│   │   ├── AuthorizesAuditPlan.php
│   │   ├── MongoCommandCounter.php
│   │   └── QmsQueryProbe.php
│   ├── TestCase.php
│   └── Unit
│       ├── attention.test.cjs
│       ├── audit-assignment-filter.test.cjs
│       ├── audit-log-export.test.cjs
│       ├── audit-workspace.test.cjs
│       ├── AuditChecklistTest.php
│       ├── AuditLogDateRangeTest.php
│       ├── AuditorAuditListingTest.php
│       ├── AuditReviewSubmissionTest.php
│       ├── CarTimelineTest.php
│       ├── corrective-actions.test.cjs
│       ├── dev-router.test.cjs
│       ├── DocumentEditorTokenTest.php
│       ├── ExampleTest.php
│       ├── folder-upload.cjs
│       ├── frontend-performance.test.cjs
│       ├── my-assigned-audits.test.cjs
│       ├── notification-badge.test.cjs
│       ├── page-work.test.cjs
│       ├── password-reset-watch.test.cjs
│       ├── portal-date.test.cjs
│       ├── qms-details.test.cjs
│       ├── QmsCycleFoldersTest.php
│       └── WalkthroughRulesTest.php
└── vite.config.js
```
