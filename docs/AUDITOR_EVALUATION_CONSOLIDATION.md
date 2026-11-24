# Auditor evaluation consolidation

Auditors evaluate assigned work through **My Assigned Audits → Audit Workspace → Audit Checklist**. The Auditor sidebar contains Dashboard, My Assigned Audits, Documents, Corrective Actions, and Notifications. Existing explicit Audit Plans permission can still add that entry; this change does not revoke permissions.

`AuditController::update`, `AuditChecklist`, and the workspace view already support draft question creation/editing/reordering, reviewed evidence, N/C/O decisions, remarks, and findings. Decisions require evidence reviews, N findings require descriptions, and submission validates every item. Assignment scope, optimistic versions, submission locks, and ATL review remain enforced.

Historical Compliance Records is linked from My Assigned Audits and the dashboard. `/auditor/historical-records` uses the existing scoped compliance-check API and has no creation or status-edit controls. `/auditor/checks` redirects there while preserving query parameters. `/auditor/checks/{id}` remains available for existing notifications, dashboard activity, and evidence links; it now checks the same auditor ownership scope before rendering, and shows retained status history and permitted linked evidence. Archive visibility and download authorization remain separate. The old `/auditor/check-create` page redirects to `/audits`, preserving the tab session parameter.

## Records and authorization

No migration or data conversion runs. `audits.items`, `audits.history`, and standalone `compliance_checks` remain separate. CARs continue to be noncompliant compliance-check records with `correctiveStatus`, `statusHistory`, `linkedArchiveIds`, and optional `sourceAuditId`/`sourceItemId` relationships. Evidence responses and notification `relatedCheckId`/destination fields are retained. Historical reads use `RoleScopeService::checks` (auditor ownership), while CAR verification retains its existing ownership and assigned-department checks. File access still uses QMS archive permissions. Other roles cannot enter the Auditor historical pages.

Corrective Actions remains a separate module. Its existing verification, closure, reopening, evidence, and notification behavior is unchanged. Existing legacy detail URLs and API endpoints remain supported, including notifications which link to the old detail routes.

## Functionality that is not interchangeable

The standalone workflow has dedicated ARTA 3/7/20-day or ISO 9.2 classification, explicit auditee targeting, two-state compliant/noncompliant results, automatic CAR opening for noncompliance, and immediate auditee notification. Workspace evaluations use N/C/O, audit-level assignment/review, and item evidence/finding descriptions; saving an N finding does **not** automatically create a CAR. Workspace result sharing follows the existing evidence-request publication flow.

For those reasons, the standalone creation service and authenticated API remain available for compatibility. The normal Auditor web navigation and creation page no longer offer that action. This consolidation does not claim that automatic CAR issuance or direct auditee notifications have been replaced, and it never turns legacy checks into workspace items.

## Regression coverage

`CorrectiveActionsMongoTest` covers the consolidated sidebar/dashboard, historical navigation and redirect, owner/role boundaries, compliant and noncompliant legacy access, unchanged records/relationships, permitted evidence, notification destinations, and the separate CAR workflow. Existing audit workflow, checklist, notification, and archive regressions cover evaluation persistence, evidence requirements, findings, submission/revision, CAR transitions, notifications, and private evidence. MongoDB integration tests use isolated temporary databases.

Validation completed: 44 JavaScript tests passed and the production Vite build passed. The audit workflow/checklist/notification/access run passed 31 tests and found one obsolete assertion expecting the old list to render rather than redirect. After updating that expectation, the targeted CAR, legacy access, and archive regression run passed all 8 tests (219 assertions), including the corrected test. PHP syntax and scoped whitespace checks passed. Qodo review was unavailable because its installed CLI is not signed in; no Qodo review is claimed. No production data migration, commit, or push was performed.
