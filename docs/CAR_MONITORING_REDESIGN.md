# Corrective Action Monitoring

The Admin monitoring page remains `/admin/compliance-records?monitor=1`. It uses the existing `compliance_checks` model, corrective workflow, evidence access service, activity timeline, notifications and assignments. It does not create a second CAR module.

## Register and official log

Five compact cards show formally recorded Open, In Progress, Awaiting Verification, Overdue and Closed CARs. A recorded CAR number is required for formal totals. Legacy noncompliant checks remain readable and searchable with an explicit legacy label. Their workflow status is preserved, but they do not become official CARs through a check-code prefix, evidence submission or historical closure.

Search covers recorded form values, findings, responsible person, department and check reference. Search submits with Enter. All dropdowns and the overdue checkbox submit automatically. Reset Filter clears search and filters. Sorting supports target date, reference and newest first; pagination supports 10, 20 and 50 records. Summaries use search/department/record-type scope before workflow and overdue filters. Overdue overlaps workflow totals and uses the actual target date through the end of the configured local day. Missing dates are never inferred.

View details opens a large accessible native dialog containing all fourteen official fields, existing corrective responses and reviews, permitted evidence, and chronological activity with resolved account names. Admin monitoring has no transition controls. The existing auditor view remains the place for authorized workflow changes.

The details UI follows the supplied reference: a navy record-specific header, pale overview sidebar, Issue & Assignment / Verification / Evidence & History tabs, colored Pass/Fail/Action cards and fixed View History / Close Details footer actions. Tabs support arrow keys, Home and End. Mobile stacks the overview above the tabbed content. The register footer shows the actual record count alongside Previous / Page X of Y / Next, preserving filters and disabling unavailable navigation.

Export CAR Log is inside the register card. It opens a printable A3 landscape document with all matching formal CARs across pagination. Use **Print / Save landscape PDF** to export. The fourteen headings and their order are defined once in `CarRegister::HEADINGS`. Text is escaped; headers repeat when printed. The export uses browser PDF generation, not a server PDF dependency. Legacy records are excluded explicitly.

## Additive schema

There is no migration, backfill, reset, new collection or automatic permission grant. A read-only inspection on November 15, 2026 found eleven documents in `compliance_checks`, no separate CAR collection and no official CAR metadata. Existing production documents were not changed.

`ComplianceCheck` now permits an optional embedded `carLog` object for explicitly recorded official metadata:

| Field | Meaning |
| --- | --- |
| `carNo` | Actual issued CAR number; determines formal versus legacy |
| `dateSubmitted` | Official submission date, never inferred from check creation |
| `type`, `source`, `process` | Official form values |
| `submittedByName` | Recorded submitting person's name, never inferred from auditor |
| `issue`, `assignedTo` | Recorded issue and responsible person |
| `resultsPass`, `resultsFail`, `resultsAction` | Independent official result values |
| `remarks` | Official log remarks |

Known issue and assignee values fall back to the existing authorized finding and auditee full name. Existing `dueDate` and `closedAt` supply Target and Actual Closed-Out Date. Other missing values display **Not recorded**. Closed workflow and closure remarks do not fabricate a Pass result. The redesign introduces no metadata authoring/import endpoint; controlled capture of official values remains outside this monitoring page.

## Authorization

Register, modal, history fragment and export all use the same active Admin scope: CET units excluding unclassified units, intersected with existing explicit department/college/cluster responsibility through `QmsFolderAccess`. Archive metadata and downloads continue to use existing archive permissions. Monitoring never broadens evidence access or permits verification/closure.

The assigned active Auditor must hold both exact `cars.verify` and `cars.close` role permissions to execute the existing verify-and-close transition. Wildcards are insufficient. Permissions do not replace department assignment or check ownership. The existing Super Admin Roles page manages these grants with current password, remarks, version conflict protection and permission history. No roles receive grants automatically. Until deliberately granted, closure is denied. Other existing transitions and notifications remain intact.

## Validation and limits

Integration coverage includes official and legacy records, missing metadata, five metrics, overdue calculations, filtering, pagination, export headings/order and escaping, read preservation, history/evidence access, cross-college/department/cluster access, status transitions, explicit permissions and grant/revocation history. Tests create isolated randomly named databases and only remove their own databases.

The browser fixture harness verifies widths 1440, 1024, 768, 390 and 320, dropdown auto-submit, compact icon cards, export placement, fourteen detail fields, modal focus restoration and fourteen export headings. Fixtures come from the integration test and never authenticate against or mutate application data.

Commands:

```powershell
$env:WRITE_CAR_UI_FIXTURES='1'
php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml --filter 'CorrectiveActionsMongoTest|AtlWorkspaceMongoTest|AuditWorkflowMongoTest|AuditPlanApprovalMongoTest|CarTimelineTest'
node tests/Browser/car-register.cjs
npm.cmd run build
```

Computed evidence status currently requires loading authorized checks before filtering and pagination. This is suitable for the current small register; a large deployment will need a MongoDB aggregation or maintained status projection. Formal metadata capture and standalone verification decisions are not added: this release retains the existing combined verify-and-close workflow behind two explicit grants.

## Task files

Completed checks: 34 core CAR/ATL/audit workflow tests (1,023 assertions), one additional cluster boundary test (9 assertions), ten approval/permission tests (179 assertions), and three timeline unit tests (20 assertions). The updated register fixture test passed again after the requested UI refinements. The five-width browser harness, landscape PDF generation, Vite build and whitespace check passed. Qodo review operation `9d0d0292-d4f4-4263-8a2d-2ded2b800c92` failed because Qodo could not obtain repository credentials for the workspace; independent review is unavailable, not clean.

- Backend: `app/Services/CarRegister.php`, `app/Services/CarPermissions.php`, `app/Services/CorrectiveActionPresenter.php`, `app/Services/ComplianceCheckService.php`, `app/Models/ComplianceCheck.php`, `app/Http/Controllers/Web/AtlWorkspaceController.php`, `app/Http/Controllers/Api/V1/RolePermissionController.php`.
- Interface: `resources/views/admin/corrective-monitor.blade.php`, `resources/views/admin/partials/car-history.blade.php`, `resources/views/admin/car-log-export.blade.php`, `public/css/car-register.css`, `resources/js/car-history.js`, `resources/js/audit-plans.js`, `resources/views/super-admin/roles.blade.php`.
- Validation: `tests/Feature/CorrectiveActionsMongoTest.php`, `tests/Feature/AtlWorkspaceMongoTest.php`, `tests/Feature/AuditWorkflowMongoTest.php`, `tests/Feature/AuditPlanApprovalMongoTest.php`, `tests/Browser/car-register.cjs`, `scripts/inspect-car-schema.php`.
- Documentation: this file. Existing unrelated dashboard/review edits were preserved. Shared test/controller files contain pre-existing work as well as this task's changes.
