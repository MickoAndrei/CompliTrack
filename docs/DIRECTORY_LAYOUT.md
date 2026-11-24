# Users and Departments layout

Super Admin and Admin Users/Departments now share the System Activity Logs
presentation: one rounded white panel, dashboard-style statistic cards with colored
icons, compact filters, light table separators, pill-shaped actions, and responsive
record cards on mobile. Descriptions appear in the portal header.

The existing Super Admin user-management filters, per-page selector, keyboard
action menu, details, editor, password-reset confirmation, and activation controls
are retained. Department management and the organizational responsibilities panel
are retained. Admin directories remain read-only under the same backend rules.

Super Admin Users keeps its existing management summary. The other directories
request optional, server-side Total/Active/Inactive counts for their filtered and
authorized scope. The counts cover all matching records, independently of page
size. Requests send `includeSummary=1`, matching Laravel's boolean query validation.
Requests without that flag retain the existing response format.

Main changes:

- `resources/css/directories.css` and its import in `resources/css/app.css`.
- `resources/views/components/resource-page.blade.php` and
  `resources/views/super-admin/partials/user-management.blade.php`.
- Admin/Super Admin department header subtitles.
- `resources/js/pages.js`, with opt-in directory summary requests.
- Existing User and Department API controllers, with scoped optional summaries.
- `tests/Feature/DirectorySummaryMongoTest.php`, updated Admin portal assertions,
  and browser fixture coverage for all four directory pages.

Validation includes 21 MongoDB directory/user-management tests (230 assertions),
eight view/entry tests, frontend unit tests, the existing user-management browser
regression at 1440/1024/768/390/320px, and shared page checks at 1440/390/320px.
The user-management error assertion follows the existing notification popup and
uses its Retry button. Integration tests use isolated temporary databases.
