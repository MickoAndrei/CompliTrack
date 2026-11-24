# Audit Plan authorization

Implemented November 13, 2026, after reading README.md and inspecting the roles, user-management safeguards, archive permissions/versioning, audit assignments/reviews, notification/logging conventions, and MongoDB initialization/schema. Existing worktree changes were preserved.

## Previous behavior

The Roles & Permissions page was a read-only overview of four fixed roles. MongoDB role records already contained permission arrays, including Super Admin's `*`, but had no configurable audit-plan approval capability. Audit plans were archive documents; assignment eligibility required manually supplied authorization metadata, and there was no application approval workflow to produce it.

## Permission configuration

The named capability is **`audit_plans.approve`**. An active authenticated user must have this exact permission on their current MongoDB role to approve or return a plan. The service reloads the current account/role for decisions. Super Admin's wildcard is deliberately insufficient; neither Admin/ATL nor Auditor receives an implicit grant.

The existing **Super Admin → Roles & Permissions** page retains its role cards and adds an explicit permission form. Select a role, enable or disable approval, supply the current Super Admin password and a reason, then save. Grants are role-wide and apply to all active accounts with the selected role; the form explicitly states this. No new account or approving-authority role is created, and no institutional title, including VPGEQA, is hardcoded.

Only an active Super Admin can change this setting, protected by the existing authenticated/active-account/system-access middleware, a ten-per-minute limit, password confirmation, exact permission validation and optimistic concurrency. Other role permissions, including `*`, are preserved. Each change records actor ObjectId/name, UTC timestamp, previous/new grant state and remarks, plus the existing semantic activity log. Role reseeding now preserves configured permissions instead of replacing them.

Revocation takes effect for subsequent decisions, including existing sessions. It does not erase a historically issued plan approval. Removing that plan from future assignment choices requires revising the plan; this preserves the distinction between changing a user's authority and changing an already-recorded institutional decision.

## Workflow and UI

The new Audit Plans page uses the existing portal theme and existing archive records. It is available to the ATL for preparation and to explicitly permitted users for approval. Approvers receive the plan metadata needed for the decision, including units, dates, scope, criteria, authorized ATLs, procedure reference and history. This capability does not grant general archive access or file downloads; existing archive security is unchanged.

The ATL prepares a plan against an existing controlled archive, selects verified CET departments/offices and active ATL accounts authorized to create assignments, and records scope, criteria, dates and the applicable independence/conflict procedure reference. Selecting an archive explicitly designates it as a plan; no document is classified or approved based on its filename, mock category or signature alone.

| Display state | Stored state | Allowed transition |
| --- | --- | --- |
| Draft | `draft` | Save draft or submit for approval |
| Pending Approval | `submitted` | Explicit approver approves or returns; preparation fields are locked |
| Approved | `accepted` | Eligible for authorized new assignments; ATL may start a revision |
| Returned for Revision | `returned` | ATL revises and resubmits |

The stored names match the established audit lifecycle vocabulary, while the UI uses plan-specific labels. Required remarks accompany every action. Each decision/revision stores actor identity, timestamp, decision, previous status/header and prior authorization. Atomic version/status checks reject stale writes with HTTP 409. The normal archive API does not accept approval/workflow fields.

Approval derives `assignmentAuthorization` from the saved, validated plan header. Unit and reviewer references and approval identities use BSON ObjectIds in the authorization receipt. The original archive ID remains the plan ID. Assignment eligibility requires the Approved state, matching approval receipt/version/identity/time, matching approved scope/criteria/units/reviewers/dates/procedure, and matching document revision/checksum. Client-supplied scope or approval identities cannot extend the approval. Existing independence, assigned-unit and duplicate-assignment controls remain.

Revising an approved plan immediately removes it from new assignment choices. It must be submitted and approved again. Existing assignments retain their approved header/authorization snapshots and histories; they are not silently reassigned or rewritten. New archive versions do not inherit an approval.

## MongoDB changes and preservation

No additional collection, account, role, index, destructive migration or bulk backfill is needed. Existing MongoDB schemas allow the additive fields; missing workflow/version values are handled as absent/zero. Normal authorized actions write:

- `roles.permissions`: the explicit named permission only when deliberately granted; other entries are retained.
- `roles.permissionVersion` and `roles.permissionHistory`: concurrency and grant/revocation history.
- `archive_documents.auditPlan`: state, version, validated header, preparer, approved actor/time, document revision/checksum and decision history.
- `archive_documents.assignmentAuthorization`: the existing assignment contract, now issued by permission-checked approval.
- Existing `audit_logs`: permission changes and plan actions, without passwords or request bodies.

Production inspection was read-only: **30 users, 11 audits, 63 archives, four existing roles, zero workflow plans and zero legacy authorization records**. All four roles had explicit approval disabled; Super Admin retained `*`. No production permission, plan, assignment or account was changed. Existing plans remain archives and can be explicitly prepared/submitted through the new workflow; none was automatically approved. Legacy metadata without a matching approved workflow is retained but is insufficient for new assignments.

## Modified files

| Files | Purpose |
| --- | --- |
| `app/Services/AuditPlanPermissions.php` | Exact opt-in capability evaluation; fresh backend account/role reads |
| `app/Services/AuditPlanWorkflow.php` | Preparation validation, transitions, receipts, history and atomic writes |
| `app/Services/AuditAssignment.php` | Require matching approval and approved document revision |
| `app/Http/Controllers/Api/V1/RolePermissionController.php` | Secure role permission configuration and history |
| `app/Http/Controllers/Api/V1/AuditPlanController.php` | Scoped plan metadata and transition endpoints |
| `database/seeders/RoleSeeder.php` | Preserve explicitly configured role permissions on reseed |
| `routes/api.php`; `routes/web.php` | Role permission API and plan page/API routes |
| `resources/views/super-admin/roles.blade.php` | Existing Roles page extended with permission controls |
| `resources/views/audit-plans/index.blade.php` | Plan preparation, review actions and history |
| `resources/views/partials/sidebar.blade.php` | Plan navigation for ATL and explicitly permitted approvers |
| `resources/views/admin/audit-assignments.blade.php` | Direct users to the application approval workflow when no plan is eligible |
| `resources/js/audit-plans.js`; `resources/js/app.js` | Lazy-loaded permission/workflow controls using existing Axios/tab/CSRF handling |
| `public/css/audit-plans.css` | Responsive plan and permission forms |
| `tests/Feature/AuditPlanApprovalMongoTest.php` | Approval/permission/backend/eligibility regression coverage |
| `tests/Support/AuthorizesAuditPlan.php`; `tests/Feature/AuditWorkflowMongoTest.php` | Update isolated legacy workflow fixtures to represent explicit historical approvals |
| `tests/Browser/audit-assignments.cjs` | Permission and workflow UI checks alongside existing assignment/review checks |
| `scripts/inspect-audit-plan-authorization.php` | Read-only deployment inventory |
| `README.md`; this report | Permission exception, workflow and verification documentation |

## Verification

- Full PHP/MongoDB regression suite: **153 tests passed, 2,290 assertions**. The final run is recorded at `storage/framework/testing/plan-full-regressions.txt`.
- Nine new integration tests cover denial for all ungranted roles and Super Admin's wildcard, explicit approval by a permitted existing role, secure grant/revocation, bad-password/unknown-permission rejection, stale writes, reseeding preservation, recorded approval identity/time/remarks, return/revision/resubmission, inactive accounts, plan scope and date validation, assignment eligibility and auditor receipt, document/scope integrity, unchanged existing assignments, and restricted archive access.
- Frontend regression suite: **37 passed**.
- Browser checks passed at **1440, 768 and 390 pixels**, including role grant display, wildcard denial in the UI, required password/reason, plan editor containment, pending-approval actions and required decision remarks. Existing assignment/review modal checks also passed with the real tab-session script enabled. Browser fixtures use isolated database snapshots and a local read-only server; no browser decision was sent to production.
- Vite production build, relevant PHP/JS syntax checks and whitespace checks passed.
- Database-writing tests used randomly named isolated local MongoDB databases with guarded cleanup. `.env` was not modified. No live approval or permission grant was used for testing.
- Qodo's earlier review attempt in this session failed because its managed reviewer could not reach/clone the repository. That unchanged connection failure was not repeatedly retried; no independent Qodo approval is claimed.

## Operational steps

1. USTP confirms the approving responsibility and intended role membership.
2. An authorized Super Admin explicitly enables `audit_plans.approve` for the intended existing role through Roles & Permissions, with password and reason. All defaults remain disabled until this deliberate action.
3. The ATL prepares the actual controlled plan, authorized unit scope, authorized ATL accounts, dates, criteria and applicable procedure, then submits it.
4. A permitted user records approval or returns it with remarks. Only an approved matching plan becomes available in New Audit Assignment.

No unconfirmed second-level approval, mandatory self-approval prohibition, automatic institutional designation or special institutional title was invented. Institutions requiring those additional controls must confirm the policy before it is enforced. The existing singular-auditor execution architecture remains unchanged.
