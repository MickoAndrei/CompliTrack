# Admin / Audit Team Leader workspace

Implemented on November 10, 2026, after reading README.md completely and inspecting the current Admin pages, MongoDB models and records, audit controller, role middleware, RoleScopeService, QmsArchiveAccess, compliance service, notification delivery, and existing workflow tests. Some README scaffold-status statements are outdated; current code and tested permissions were used as the implementation authority.

## Account and database preservation

The existing active Admin (`roleId = 1`) remains the ATL. No account, role, reviewer assignment, department relationship, or credential was created or changed in the application database. No schema migration or new index was necessary. Four fixed roles and the five verified CET academic departments remain intact. Existing Super Admin system-administration permissions and QMS organizational restrictions were retained. Firebase was not added.

Read-only inspection of the configured Atlas database found 30 users, 4 roles, 8 organizational units (including the five academic departments), 11 audits, 11 compliance checks, 63 archive records, 111 notifications, and 100 audit logs. The sole active Admin was the reviewer of all 11 audits. The actual scoped dashboard returned 9 active audits, 2 pending reviews, 6 open CARs, 2 overdue CARs, 6 active auditors, and 19 active auditees. These are a verification snapshot of existing records, including pre-existing clearly marked development records, not hardcoded application statistics.

All implementation-time database access to that application database was read-only. Automated write tests used randomly named isolated databases, with guarded cleanup. Browser review decisions were intercepted by a local fixture server and never forwarded to the live application.

## Dashboard and scope

- Heading: **Audit Team Leader Dashboard**.
- Active audits: assigned to this reviewer, within the existing CET department scope, in `draft`, `ready`, `submitted`, or `returned` state.
- Pending reviews: the same scope, in `submitted` state.
- Open CARs: non-compliant check records in `open` or `in_progress`, within the existing Admin/CET check scope. This existing college scope is broader than the individual audit-review assignment scope and was not silently narrowed or expanded.
- Overdue CARs: open/in-progress records with a stored deadline whose end of day has passed in the configured system timezone. Missing dates remain unknown; no deadline is inferred from record age.
- Active auditor and auditee totals remain in a secondary summary.
- Requires My Attention contains submitted audits, returned audits requiring auditor follow-up, and overdue CARs. The preview shows at most 10 audit follow-ups and 10 overdue CARs, with links to the filtered full lists.
- Assignment progress is paginated, 20 records per page, and includes reference, title, department, assigned auditor, checklist completion, and state.
- The five-department compliance comparison uses accepted audits assigned to this reviewer. It calculates `C / (C + N)`, reports O separately, and shows **Not Yet Assessed** when the applicable denominator is zero. CAR closure does not change the original audit evaluation.

## Reviews and corrective monitoring

Audit Assignments and Audit Reviews share `/audits`; review filtering uses `?view=reviews` and optional `status`. Existing audit links are enhanced into a large native responsive dialog using authorized GET `/audits/{id}?review=1`; ordinary links still work without JavaScript. No new audit detail page or separate review workflow was introduced.

The popup contains audit information, questions, N/C/O, auditor remarks, findings, evidence-review references, authorized linked files, submitted evidence responses (not private drafts), interview/process notes, and submission/review history with prior evaluation snapshots. File titles and download links are resolved through the existing QmsArchiveAccess queries; storing a file ID in an audit is not permission to expose that file.

Accept and Return for Revision post to the existing `/audits/{id}` action. Only the active assigned Admin reviewer of an in-scope submitted audit may decide. Review notes remain required. The server checks the submitted version before preparing a write and atomically rechecks scope, version, and status on update. Stale decisions receive HTTP 409. The existing audit log, version history, and persisted notification behavior are reused.

The reviewer cannot edit questions or N/C/O. Return unlocks the existing auditor revision/resubmission workflow; acceptance does not silently rewrite results, automatically create CARs, or close CARs. The popup handles loading/errors, stale refresh, duplicate-click prevention, focus restoration, and Escape with a native focus trap. The existing form field named `action` is read without allowing it to mask the form's action URL.

Corrective Monitoring reuses `/admin/compliance-records?monitor=1`, with optional status/overdue filters and `car` history selection. It shows stored deadlines, assigned parties, state, existing closure-verification remarks, status history, corrective evidence/review history, and authorized files. It provides no ATL close/reopen or evaluation controls. The assigned auditor's existing backend transition and verification requirements remain authoritative. Displayed verification information is derived from existing stored history; it does not introduce a new institutional approval stage.

## Other Admin sections

- Transient success, error, and warning notices across the portal use a shared navy/gold popup, including the archive ownership warning. Popups dismiss after three seconds and also have a close button. Duplicate active messages are suppressed; retry actions are preserved, and dialog notices appear in the modal top layer. Persisted notification feeds and record statuses remain part of the page.
- Sidebar groups: Workspace, Audit Coordination, CET Resources, and My Account. Existing routes are reused.
- Users, departments, and compliance records have consistent headings, search/toolbars, bordered cards, readable table wrapping, and contained mobile scrolling.
- Admin Users and Departments are read-only directories, with ten records per page and ten-row animated loading skeletons. Creation, editing, account resets, and status controls are removed; user and department write API routes require Super Admin. Scoped reads and Super Admin management remain available. Directory scrollbar tracks are hidden while scrolling remains usable.
- Notifications use a readable message feed with Read/Unread badges, search, unread/type filters, timestamps, related-record links, and the existing broadcast action. The old boolean formatting incorrectly labeled read state Active/Inactive.
- Assignment cards/form and report cards/charts share the navy/gold/white presentation. Admin archive refinements are scoped around the existing QMS explorer; its functionality and authorization are unchanged.
- Reports explicitly describe their source as recorded compliance checks/CARs, separately from the dashboard's accepted-checklist comparison. Empty department/month samples show Not Yet Assessed. Department matching now handles BSON and legacy string IDs, fixing false empty comparisons; the report API's active-audit metric now counts audit records rather than CARs. `assessedChecks` is an additive response field, not a database schema change.

## Files in this ATL pass

| Area | Files |
| --- | --- |
| Backend | `app/Services/AtlWorkspace.php`; `app/Http/Controllers/Web/AtlWorkspaceController.php`; `app/Http/Controllers/Web/AuditController.php`; `app/Services/ReportGeneratorService.php` |
| Routes | `routes/api.php`; `routes/web.php` |
| Admin views | `resources/views/admin/dashboard.blade.php`; `atl-dashboard.blade.php`; `audit-review.blade.php`; `corrective-monitor.blade.php`; `reports.blade.php` |
| Shared views | `resources/views/auditor/audits.blade.php`; `resources/views/components/atl-review-modal.blade.php`; `resource-page.blade.php`; `resources/views/layouts/app.blade.php`; `resources/views/partials/sidebar.blade.php` |
| Frontend | `resources/js/atl-workspace.js`; `resources/js/app.js`; `public/css/admin-ui.css` |
| Verification | `scripts/inspect-atl-workspace.php`; `scripts/verify-atl-workspace.php`; `tests/Feature/AtlWorkspaceMongoTest.php`; `tests/Feature/AdminPortalViewTest.php`; `tests/Browser/atl-workspace.cjs` |
| Documentation | `README.md`; this report |

Earlier workspace changes, including settings, account badges, profile/header controls, and notification dropdown work, were preserved. No commit, deployment, or live review decision was performed.

## Verification

- General PHP suite: **41 passed, 280 assertions**. Its 46 opt-in MongoDB tests are intentionally skipped in the default configuration.
- Final selected MongoDB suite: **38 passed, 1,046 assertions**, covering the ATL workspace, complete audit workflow, QMS archive access, user management, and Super Admin boundaries. This final run includes the report ID/count fix. It ran on the already-running local MongoDB server, using process-only `MONGODB_URI` override and isolated databases. `.env` was not changed.
- Earlier Atlas integration runs passed 16 ATL/audit workflow tests (538 assertions), two archive regression tests (112 assertions), and a targeted popup/report run (49 assertions).
- JavaScript regression tests: **10 passed**, covering notification counts/read actions and audit-log export.
- Browser checks: dashboard/review at 1440, 768, and 390 pixels, plus eight other Admin sections at 1440 and 390 pixels: **19 page/viewport combinations passed**. User/department dialogs fit both widths. Review tests cover required notes, conflict refresh, local Accept/Return simulations, removal of decision controls afterward, focus restoration, Escape, and absence of runtime exceptions. Screenshots are private under `storage/framework/testing/atl-live/`.
- Vite production build, PHP/JavaScript syntax checks, and whitespace checks passed. Vite retains its existing main-bundle-size warning.
- Qodo was unavailable in this environment; review was performed locally with the above regression and authorization checks.

Atlas initially supplied the real-record inventory and private browser snapshots, but later verification attempts encountered DNS timeouts and TLS handshake failures. Connection settings and TLS verification were not modified. The final report fix is covered by the local MongoDB suite; a refreshed live Atlas snapshot remains dependent on restored Atlas connectivity. Browser checks use the earlier authorized snapshots rather than claiming a live end-to-end write test.

## Remaining institutional workflow decisions

1. Confirm whether department compliance should use all accepted audits (current behavior, consistent with the existing Super Admin comparison), the latest audit, or a defined reporting cycle.
2. Confirm the formal CAR due-date cutoff, extensions, escalation recipients, and any separate verification/sign-off stage. The implementation uses stored deadlines and retains assigned-auditor closure authority.
3. Confirm whether accepted N findings should require a separate explicit CAR-issuance step and who may perform it. No automatic conversion was inferred.
4. Confirm reviewer delegation/reassignment and any second-level approval requirements. The implementation retains the designated reviewer and existing Accept/Return workflow.
