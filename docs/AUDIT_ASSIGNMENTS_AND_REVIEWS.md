# Audit Assignments and Audit Reviews implementation

Implemented November 13, 2026. README.md was read completely. The audit controller/model, archive and organizational models, actual CET organizational records, RoleScopeService, AtlWorkspace, QmsArchiveAccess, authentication/role middleware, persisted notifications, existing workflow tests and ATL implementation reports were inspected. Screenshot text was used as UI guidance; illustrative names, dates and counts were not imported as application data.

## Root cause

The sidebar already distinguished `/audits` and `/audits?view=reviews`, including active-link handling. Both requests nevertheless rendered `auditor.audits`, with the same review-oriented cards and an inline creation form. The form accepted free-text audit headers without any database audit-plan authorization or independence validation. The organizational scope helper also excluded the college office from audit access.

There is no standalone audit-plan model, collection, approval controller or institutional plan approval workflow in this repository. Plans are archived documents linked to existing audits. A document's title, mock status, signature metadata or mere existence cannot establish authorization to create an assignment.

## Resulting behavior

- Assignments and Reviews now render distinct Admin views through the existing route and controller. Auditor My Assigned Audits retains its execution cards.
- Assignments show the actual paginated register: audit/plan, unit, owning auditor, schedule, scope/criteria and lifecycle status. Counts use stored audits and authorized plans. No fictional unassigned rows or counts are generated.
- A prominent New Audit Assignment button opens a large native responsive dialog. As requested, “Assign auditors to departments and offices based on authorized audit plans.” replaces the default shared-header subtitle on this page and appears only once.
- Plan selection reads permitted archive records with explicit assignment authorization. Unit selection is restricted to the authorized plan's verified active CET units. Academic units must match the five configured CET department codes/names; the Dean's office remains `college_office`. Other offices require verified organizational records and explicit plan coverage.
- Auditor choices retain the existing Admin user scope, active Auditor role and assigned-unit permission. Home-unit and recorded `conflictDepartmentIds` matches are rejected. Notes and an independence declaration under the plan's procedure reference are mandatory. These are conservative application controls, not certification that an unconfirmed institutional procedure has been fully automated.
- Schedule must fall within the authorized plan dates. Scope and criteria are inherited server-side; forged client values are ignored.
- New audit references use BSON ObjectIds for plan, department, auditor and reviewer. The original duplicate-assignment key formula, lookup, existing unique index and duplicate-error handling are retained.
- Assignments use the existing `audits` collection and notification dispatcher. Assigned auditors immediately see them through the existing scoped query; no user assignment permissions are silently expanded.
- Pre-submission amendments require the assigned active ATL, current version, a reason and a fresh independence check. They retain the original plan/unit, record the prior assignment and authorization snapshot, increment the version atomically, and notify both previous and new auditors. Prepared checklist/evidence/history is retained. Submitted, returned and accepted records cannot be reassigned; submission history also blocks reassignment if a record's status is later changed.
- Reviews default to Awaiting Review (`submitted`, including resubmissions), ordered by update time. Returned and Reviewed (`accepted`) have separate counts and tabs; invalid review-status filters are rejected.
- The existing large review dialog, authorized evidence resolution, checklist N/C/O, remarks, findings, submission snapshots, interviews and Accept/Return actions remain in use. The linked plan is resolved through archive permissions too. ATL evaluation editing stays prohibited. Existing optimistic-concurrency, audit-log and review-notification controls remain intact.
- One owning auditor is retained. Execution, evidence ownership, metrics and submission authorization depend on singular `auditorId`; a partial team array would misrepresent access. Team execution remains a separate architectural requirement.

## Database impact and actual inventory

No production database writes, migrations, indexes, seeders, account edits or permission changes were applied. Read-only inspection found one active Admin, 11 audits, 63 archive records, five canonical academic departments, a verified Dean's office and an unverified legacy demo unit. All existing records were preserved.

There are **zero archive records containing assignment authorization metadata**, and **zero plans currently selectable for the existing Admin**. Consequently new production assignments are intentionally unavailable until institutional authorization and plan scope are confirmed and recorded. Existing assignments/reviews continue to work. No existing plan was automatically declared approved.

The additive application contract on an existing permitted archive is `assignmentAuthorization`, with:

| Field | Required meaning |
| --- | --- |
| `status` | Explicit `authorized` assignment permission; not an inferred institutional approval status |
| `reviewerIds` | Authorized existing ATL account IDs; BSON or legacy string references accepted |
| `unitIds` | Explicit covered department/office IDs |
| `scope`, `criteria` | Confirmed assignment scope and criteria, each at most 3,000 characters |
| `scheduleStart`, `scheduleEnd` | Valid ISO calendar dates, ordered inclusively |
| `procedureReference` | Confirmed applicable independence/conflict procedure reference |

Missing or malformed authorization is excluded. The normal archive API does not accept this field; this implementation adds no approval-writing API. A reviewed institutional mapping and controlled metadata update are still required. The contract stores explicit authorization without claiming that this is USTP's official approval sequence.

New assignments additionally store `planId`, `planTitle`, `planAuthorizationSnapshot`, `independenceConfirmed` and `independenceNotes`. Assignment amendments append `previousAssignment` to the existing history. Legacy audits remain readable without these fields and are not silently linked to a plan.

## Files changed in this task

Existing user changes in shared files were preserved. The list below describes this task's additions to those files, not every difference already present in the worktree.

| Files | Change |
| --- | --- |
| `app/Http/Controllers/Web/AuditController.php` | Separate view dispatch; plan-scoped creation; versioned amendments; permitted plan evidence |
| `app/Services/AuditAssignment.php` | New validation and plan/unit/auditor eligibility service using existing collections |
| `app/Models/Audit.php` | Nullable ObjectId cast for `planId` |
| `app/Services/AtlWorkspace.php` | Include assigned CET office audits without changing general directory/archive permissions |
| `routes/web.php` | Amendment action within the existing authenticated role-protected audit route group |
| `resources/views/admin/audit-assignments.blade.php` | Register, header subtitle, responsive creation/change dialog |
| `resources/views/admin/audit-reviews.blade.php` | Review tabs and submitted-result links |
| `resources/views/admin/audit-review.blade.php` | Prior assignment history display |
| `resources/views/auditor/audits.blade.php` | Remove Admin creation/review rendering; retain assigned-audit cards |
| `resources/views/auditor/audit-workspace.blade.php` | Safely render both evaluation and assignment history snapshots |
| `resources/js/audit-assignments.js`; `resources/js/app.js` | Lazy-load dialog handlers, scope/eligibility filters, amendment fields and focus handling |
| `public/css/audit-assignments.css` | Responsive register/modal styles |
| `resources/js/icons.js` | Regenerate the source-derived icon registry required by frontend regression checks |
| `tests/Support/AuthorizesAuditPlan.php` | Explicit isolated plan authorization fixtures, independent home departments |
| `tests/Feature/AuditWorkflowMongoTest.php` | Update assignment inputs; test authorization, conflicts, office scope, inherited criteria, amendments, locks and separate views |
| `tests/Feature/QmsArchivesMongoTest.php` | Update existing assignment regression to use an explicitly authorized isolated plan |
| `tests/Browser/audit-assignments.cjs` | Read-only fixture browser checks for register, header, dialog controls, filters, focus and review modal |
| `scripts/inspect-assignment-plans.php` | Read-only non-personal inventory and authorization diagnostics |
| This report | Root cause, behavior, database impact, checks and blockers |

## Verification

- Full PHP and isolated MongoDB regression suite: **144 tests, 2,125 assertions passed**. Final run includes the retained Admin auditor-account scope. Randomly named databases are guarded and removed by the test harness; `.env` was not edited.
- End-to-end application HTTP tests create the ATL assignment, assert auditor receipt and ObjectId storage, prepare and evaluate a checklist with evidence, submit, return, revise, resubmit and accept. Existing records, notifications, private evidence, unauthorized actors and stale writes are covered. Additional tests exercise own-unit conflicts, recorded conflicts, unauthorized/revoked plans, schedule bounds, plan-authorized college offices, inherited scope/criteria, amendment history, both notification recipients and post-submission locks.
- Frontend Node regression suite: **35 passed**.
- Realtime service suite: **16 passed, 1 opt-in MongoDB service test skipped**. Firebase-free persisted assignment/submission/review delivery and recipient isolation tests passed.
- Browser: **1440, 768 and 390 pixels passed** using HTML rendered from isolated test records and a local read-only fixture server. Covered viewport containment, single header description, separate modules, required inputs, plan scope, auditor filtering, inherited scope/date bounds, amendment context locks, reason requirement, focus restoration, Escape and review modal containment/read-only evaluation controls. No browser runtime errors. Browser decisions were not sent to production; full writes were tested through actual Laravel routes against isolated MongoDB.
- Vite production build, relevant PHP/JavaScript syntax checks and `git diff --check` passed. Browser screenshots are private local artifacts under `storage/framework/testing/assignment-ui/`.
- Qodo review skill used: CLI authenticated and accepted operation `6a876375-c6ce-4ea0-aabf-c2987faf4f0b`. Result collection reported a managed-agent repository-connection/transport failure. No independent Qodo approval or completed review is claimed. Local inspection and the regression checks above were completed.

For reproduction in PowerShell, set `$env:MONGODB_URI='mongodb://127.0.0.1:27017/?directConnection=true'` for the current test process and run `php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml`. This selects the local server, while tests create their own isolated database names. Use `node --test tests/Unit/*.test.cjs`, `npm.cmd run build`, and `npm.cmd test` in `realtime` for frontend/service checks. To regenerate browser fixtures, set `$env:AUDIT_ASSIGNMENT_BROWSER_FIXTURES='1'` and run the `test_admin_assignment_and_review` PHPUnit filter before `node tests/Browser/audit-assignments.cjs`.

## Remaining blockers

1. Confirm institutional plan approval/authorization rules, authorized plan versions, dates, unit coverage, authorized ATL IDs and the applicable independence/conflict procedure. Populate reviewed metadata on the actual plan archives afterward. The current application database has none; the UI explicitly explains why new assignment saving is unavailable.
2. Confirm any additional conflict rules, declarations, reviewer segregation or exceptions beyond the conservative home-unit/recorded-conflict checks. No exception or institutional approval was invented.
3. Multiple auditors require coordinated changes to execution/evidence permissions and notifications; this pass correctly supports the existing singular-owner architecture.
4. Existing production audits lack structured plan links. Mapping or amending those records requires a confirmed non-destructive institutional mapping; existing submissions/reviews were preserved.
5. Qodo must be able to access this repository before an independent service review can finish.

## Review popup follow-up

The review form's Accept/Return controls are named `action`, which shadows the native `form.action` property. The tab-session MutationObserver compared that shadowed property to a URL string and repeatedly rewrote the action attribute, keeping the browser in a mutation loop after inserting the review HTML. `public/js/tab-session.js` now reads and compares the action attribute directly and sets it only when different. Account/tab isolation and authorization are retained.

The real-browser tab-session regression now inserts this exact named-control pattern and verifies that URL scoping settles after one mutation. Assignment/review popup browser tests now run with the actual tab-session script enabled, rather than stripping it from the fixture. All three viewport widths passed, as did 9 targeted PHP session/review tests (147 assertions) and 37 frontend tests. The review description was also moved into the shared header subtitle and removed from below the header. No production records or permissions were changed.

## Auditor eligibility follow-up

Read-only diagnostics found seven active Auditors, six within the existing ATL account scope, and one selectable approved plan. For each academic department, the only preauthorized auditor belonged to that same department and was excluded by independence checks; the other five scoped auditors lacked that unit authorization. None was authorized for the Dean's office. The empty dropdown reflected these stored permissions, rather than missing account records.

The assignment dialog now explains whether the user must first select a plan, select a unit, or obtain independent auditor authorization. It disables the empty auditor selector and assignment submission when no eligible auditor exists. Browser coverage verifies these states and that options become available when an independent authorized auditor exists.

Super Admin Users now offers verified CET offices alongside academic departments in **Eligible audit departments and offices**. The backend permits explicit college-office audit eligibility for an independent auditor while retaining its home department, rejecting home-office placement, unverified/outside college offices and non-Super-Admin edits. The office remains an office, not a sixth academic department. No production user or permission was automatically changed. Use **Super Admin → Users → Edit user → Eligible audit departments and offices** to authorize a selected independent auditor, then reopen New Audit Assignment.

Changes are in the assignment view/JavaScript, UserManagementPresenter, UserController, the existing user editor, the browser regression and UserManagementMongoTest. `scripts/inspect-assignment-auditors.php` provides read-only count diagnostics. The frontend suite has 37 passing tests; browser checks pass at 1440, 768 and 390 pixels. The complete PHP/MongoDB regression result is recorded in `storage/framework/testing/auditor-eligibility-regressions.txt`.

### Correcting a legacy auditor home department

The editor preserves eligibility references that are absent from its selectable options. Previously, changing an auditor's home department revalidated every retained eligibility reference and rejected an existing `legacy_unclassified` reference, preventing correction of the home department itself. Super Admin updates to an existing Auditor now preserve such historical eligibility references while validating the home department and all other selected units. New legacy eligibility references, legacy home selections and role changes do not receive this exception. Removing a legacy reference does not permit adding it again. Historical references remain excluded from authorized assignment units, and home-unit independence checks still apply. No production records are migrated or automatically edited.

The isolated UserManagementMongoTest regression reproduces the editor's home-only PATCH, verifies unchanged credentials and historical eligibility, checks subsequent independent authorization and rejects newly introduced or reintroduced legacy references.
