# Role-specific Help Centers

Each role now has a searchable Help & Standards page in its existing sidebar:

| Role | Route | Topics |
| --- | --- | --- |
| Super Admin | `/super-admin/help` | Accounts; permissions; departments/offices; document safeguards; activity/settings |
| Admin / ATL | `/admin/help` | Plans; assignments; submitted-audit reviews; corrective monitoring; standards/document control |
| Auditor | `/auditor/help` | Assigned audits; evidence review; N/C/O evaluation; corrective actions; standards/document control |
| Auditee | `/auditee/help` | Audit preparation; evidence presentation; findings; N/C/O; reference standards |

The shared layout keeps the existing application shell and navy/yellow/white theme. Search indexes rendered guidance, including collapsed explanations. Only the selected topic is displayed. Desktop uses topic buttons with pressed states; mobile uses a labeled native selector. Search counts are announced, no-results recovery returns focus to search, and the original Auditee topic fragment identifiers still work.

Auditee guidance retains document-owner permissions, controlled QMS safeguards, physical-original signature verification, supported upload formats/size, draft and review behavior, notification limitations, Pending semantics, and the distinction between during-audit presentation and corrective evidence. Existing ISO 9001:2015 and ARTA references remain unchanged. No external resources or institutional policies were invented. Other roles receive guidance based on existing routes and documented workflows; the Help Center grants no permissions.

## Files changed for this task

- `config/help.php`: role-specific guidance for Super Admin, Admin/ATL, and Auditor.
- `resources/views/help/index.blade.php`: shared search, topic navigation, mobile selector, empty state, and support footer.
- `resources/views/help/auditee-topics.blade.php`: preserved Auditee guidance reorganized into steps, disclosures, and decision cards.
- `resources/views/help/role-topics.blade.php`: shared rendering of the other roles' topics.
- `resources/views/auditee/help.blade.php`: retains the original view entry point through the shared Help Center.
- `resources/js/auditee-help.js`: shared Alpine search, selection, fragment handling, and focus behavior for all roles.
- `resources/js/app.js`: conditional registration of the Help Center module.
- `resources/js/icons.js`: generated registry adds the new help icons.
- `public/css/auditee-help.css`: scoped shared responsive Help Center styles.
- `resources/views/partials/sidebar.blade.php`: adds Help & Standards entries for the other three roles.
- `routes/web.php`: adds three role-guarded Help Center routes.
- `tests/Feature/AuditeeHelpViewTest.php`: guidance, structure, route middleware, role links, and permission-boundary checks.
- `tests/Fixtures/auditee-help.php`: production-view browser fixtures for all roles without database writes.
- `tests/Browser/auditee-help.cjs`: responsive, keyboard, accessibility-tree, search, fragment, and role-topic checks.
- `docs/HELP_CENTERS.md`: implementation and verification report.

Pre-existing unrelated edits in shared files were preserved. No database, Firebase, or dependency changes were made. Generated build output and browser artifacts remain in their existing ignored directories.

## Actual verification

- `npm.cmd run build`: passed (Vite production build).
- `php vendor/phpunit/phpunit/phpunit --filter 'AuditeeHelpViewTest|AuditeeResultsViewTest|AuditeeDashboardViewTest|AuditAccessTest'`: passed, 12 tests and 168 assertions.
- `node --test tests/Unit/frontend-performance.test.cjs`: passed, 5 tests. The initial missing-icon regression was fixed by regenerating the explicit registry.
- `node tests/Browser/auditee-help.cjs`: passed. Auditee: 1440, 1024, 768, 390, and 320 pixels; other three roles: 1440 and 390 pixels. Checks include one visible topic, selector synchronization, keyboard Enter/Space, native disclosures, hidden panels excluded from the accessibility tree, search/no-results/focus recovery, empty topic data, legacy fragments, overflow, and runtime errors.
- `node tests/Browser/evidence-requests.cjs`: passed at 1440, 900, 768, 390, and 320 pixels, including existing modal, draft, validation, keyboard, read-only, and notification-link regressions.
- PHP syntax checks for the new config and fixture, plus scoped `git diff --check`: passed.
- Desktop and mobile screenshots inspected: `storage/framework/testing/help-browser/`. These tests do not constitute a full assistive-technology or WCAG audit.

Qodo identity verification succeeded outside the sandbox through the Windows keychain. The review used the locally available remote base `b79bbdd235e88795501b61c6d68d1738dc22158d` and an uncommitted, path-scoped snapshot because shared files contain unrelated user changes. Operation `32ea3119-c8b6-4d3a-ba7a-a38727bcc5d4` failed: Qodo could not obtain repository credentials for the current workspace. The service instructed against an automatic retry. No completed Qodo review or clean findings are claimed; the active Qodo workspace/repository connection needs checking.
