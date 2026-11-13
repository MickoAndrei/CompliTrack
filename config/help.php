<?php

// Guidance reflects the existing routes and documented workflows; it grants no permissions.
return [
    0 => [
        'name' => 'SUPER ADMIN',
        'intro' => 'Manage accounts, organizational access, document safeguards, and system activity.',
        'topics' => [
            'accounts' => [
                'label' => 'Manage Accounts', 'icon' => 'users',
                'intro' => 'Keep account identity, role, and organizational responsibility accurate.',
                'steps' => [
                    ['Review the account', 'Use Users to search accounts and inspect their stored details and activity.'],
                    ['Check organizational assignment', 'Confirm the role, department or office, responsibility, and active status before saving a change.'],
                    ['Use the provided security actions', 'Use the existing reset, deactivation, or reactivation actions and review their confirmation prompts.'],
                ],
                'href' => '/super-admin/users', 'action' => 'Open Users',
            ],
            'permissions' => [
                'label' => 'Roles & Permissions', 'icon' => 'shield-check',
                'intro' => 'Audit-plan approval requires the explicit audit_plans.approve permission.',
                'steps' => [
                    ['Review the role grant', 'Open Roles to inspect the audit-plan approval permission and its recorded history.'],
                    ['Check the scope of a change', 'A role-wide grant applies to active accounts with that role. Approval is never inferred from a title, username, role ID, or wildcard alone.'],
                    ['Confirm an authorized change', 'Use the existing password confirmation and remarks controls. Revocation affects subsequent decisions and preserves historical approvals.'],
                ],
                'note' => 'Managing approval permissions does not authorize the Super Admin to create compliance checks.',
                'href' => '/super-admin/roles', 'action' => 'Open Roles & Permissions',
            ],
            'organization' => [
                'label' => 'Departments & Offices', 'icon' => 'building-2',
                'intro' => 'Organizational records help determine account and document scope.',
                'steps' => [
                    ['Find the organizational record', 'Review Departments before editing a department or office.'],
                    ['Check linked responsibilities', 'Verify the intended organizational assignment when managing users.'],
                    ['Respect preservation controls', 'Use the existing validation and confirmation messages; do not delete linked records to bypass a restriction.'],
                ],
                'href' => '/super-admin/departments', 'action' => 'Open Departments',
            ],
            'documents' => [
                'label' => 'Document Safeguards', 'icon' => 'archive',
                'intro' => 'System administration does not replace document-owner and document-control permissions.',
                'steps' => [
                    ['Inspect authorized metadata', 'Use Documents to locate records available within the existing archive scope.'],
                    ['Use permitted document actions', 'Preview, download, or manage a document only through actions your account is authorized to use.'],
                    ['Protect controlled QMS content', 'Do not copy protected materials solely because an audit request exists. Confirm reproduction and storage permissions with the owner.'],
                ],
                'note' => 'No new retention period, filename rule, classification scheme, or document permission is established by this Help Center.',
                'href' => '/super-admin/archives', 'action' => 'Open Documents',
            ],
            'activity' => [
                'label' => 'Activity & System Settings', 'icon' => 'scroll-text',
                'intro' => 'Use recorded activity to investigate system actions without rewriting history.',
                'steps' => [
                    ['Review system activity', 'Open System Activity Logs and filter recorded events for the account or action you are investigating.'],
                    ['Distinguish notifications from logs', 'Notifications are an account inbox; system activity is a separate read-only audit history.'],
                    ['Review settings deliberately', 'Use Settings for authorized configuration changes and follow the existing confirmation requirements.'],
                ],
                'href' => '/super-admin/logs', 'action' => 'Open System Activity Logs',
            ],
        ],
    ],
    1 => [
        'name' => 'ADMIN / ATL',
        'intro' => 'Prepare audit plans, coordinate assignments, review submissions, and monitor corrective actions.',
        'topics' => [
            'plans' => [
                'label' => 'Prepare Audit Plans', 'icon' => 'clipboard-list',
                'intro' => 'Plan preparation and plan approval are separate responsibilities.',
                'steps' => [
                    ['Prepare the plan header', 'Use an existing controlled archive and record scope, criteria, units, dates, authorized ATLs, and the applicable independence or conflict procedure reference.'],
                    ['Submit for approval', 'Save a draft or submit the prepared plan using the existing workflow. Submitted preparation fields are locked.'],
                    ['Use an approved plan', 'Only eligible approved plans can support new assignments. A revised plan must be submitted and approved again.'],
                ],
                'note' => 'Approval requires explicit audit_plans.approve permission. ATL status alone does not grant approval authority.',
                'href' => '/audit-plans', 'action' => 'Open Audit Plans',
            ],
            'assignments' => [
                'label' => 'Coordinate Assignments', 'icon' => 'users',
                'intro' => 'Keep assignments within the approved plan and existing organizational scope.',
                'steps' => [
                    ['Review the approved plan', 'Confirm the approved scope, criteria, units, dates, and reviewer authorization.'],
                    ['Create an authorized assignment', 'Use Audit Assignments and the existing independence, assigned-unit, and duplicate-assignment controls.'],
                    ['Track the workflow', 'Open the assignment to follow preparation, submission, and review. Existing assignments retain their recorded authorization history.'],
                ],
                'href' => '/audits', 'action' => 'Open Audit Assignments',
            ],
            'reviews' => [
                'label' => 'Review Submitted Audits', 'icon' => 'clipboard-check',
                'intro' => 'Review submissions assigned to you through the ATL workspace.',
                'steps' => [
                    ['Open the submitted audit', 'Use Audit Reviews to inspect the authorized checklist decisions, evidence reviews, and submission details.'],
                    ['Review the supporting record', 'Check readiness and reviewed evidence. Restricted references and physical originals must retain their existing safeguards.'],
                    ['Accept or return the submission', 'Use the available review actions and record required remarks. Submission and acceptance lock results under the existing workflow.'],
                ],
                'note' => 'A checklist finding does not automatically open a CAR. Acceptance and permitted result sharing are separate workflow actions.',
                'href' => '/audits?view=reviews', 'action' => 'Open Audit Reviews',
            ],
            'monitor' => [
                'label' => 'Monitor Corrective Actions', 'icon' => 'triangle-alert',
                'intro' => 'Monitor corrective progress separately from during-audit evidence requests.',
                'steps' => [
                    ['Locate the corrective record', 'Open Corrective Monitoring to review nonconforming checks and recorded CAR status within your scope.'],
                    ['Review status and evidence', 'Distinguish an open or in-progress corrective action from a closed one. A submitted file does not close a CAR.'],
                    ['Follow the authorized workflow', 'Use the available monitoring and review actions; retain links to the original finding and corrective evidence.'],
                ],
                'href' => '/admin/compliance-records?monitor=1', 'action' => 'Open Corrective Monitoring',
            ],
            'standards' => [
                'label' => 'Standards & Document Control', 'icon' => 'book-open',
                'intro' => 'Use approved criteria and protect controlled materials during review.',
                'steps' => [
                    ['Confirm applicable criteria', 'Retain existing ISO 9001:2015 internal-audit and ARTA reference identifiers. Confirm the approved criteria for the process with the audit coordinator.'],
                    ['Interpret results accurately', 'N means Non-Conformance, C means Compliant, and O means Observation. Pending means unanswered, not a compliance result. These are process assessments, not employee performance ratings.'],
                    ['Respect document permissions', 'Use the read-only archive access available to your account. Protected QMS content must not be copied solely because an evidence request exists; controlled-form reproduction requires institutional confirmation.'],
                ],
                'href' => '/admin/archives', 'action' => 'Open Documents',
            ],
        ],
    ],
    2 => [
        'name' => 'AUDITOR',
        'intro' => 'Prepare assigned audits, review evidence, record decisions, and follow corrective actions.',
        'topics' => [
            'assignments' => [
                'label' => 'Prepare Assigned Audits', 'icon' => 'clipboard-list',
                'intro' => 'Start from the audits explicitly assigned to your account.',
                'steps' => [
                    ['Open the assignment', 'Use My Assigned Audits to review scope, criteria, dates, and current workflow status.'],
                    ['Prepare the checklist', 'Use the draft workspace to prepare audit questions. Question preparation and evaluation are separate.'],
                    ['Review submission readiness', 'Follow the workspace readiness guidance before submitting the evaluation to the assigned reviewer.'],
                ],
                'href' => '/audits', 'action' => 'Open My Assigned Audits',
            ],
            'evidence' => [
                'label' => 'Request & Review Evidence', 'icon' => 'file-check',
                'intro' => 'Request only the evidence needed within the assigned audit scope.',
                'steps' => [
                    ['Address the request', 'Open the assigned workspace and create a request for the intended auditee. A request does not grant document-copying permission.'],
                    ['Review the permitted presentation', 'Digital references can use permitted codes, locations, or descriptions without restricted identifiers. Physical originals can be arranged for inspection; record whether a required signature was actually verified.'],
                    ['Review authorized uploads and feedback', 'Only owner-authorized copies permitted by document-control rules may be stored. Record review feedback through the request workflow.'],
                ],
                'note' => 'A file is not mandatory. Never request protected QMS uploads solely because a request exists. During-audit presentation and corrective evidence are separate.',
                'href' => '/audits', 'action' => 'Open assigned audit workspaces',
            ],
            'decisions' => [
                'label' => 'Evaluate & Submit N/C/O', 'icon' => 'circle-check',
                'intro' => 'Base checklist decisions on reviewed evidence.',
                'steps' => [
                    ['Interpret the decision', 'N — Non-Conformance: a requirement was not met. C — Compliant: the requirement was met. O — Observation: a factual observation recorded by the auditor.'],
                    ['Keep unanswered items distinct', 'Pending means unanswered, not a compliance result. These are process assessments, not employee performance ratings. A finding and a CAR are separate from a checklist decision.'],
                    ['Submit when ready', 'Complete the existing evidence and submission requirements. Submission and acceptance lock results; follow reviewer feedback for a returned audit.'],
                ],
                'href' => '/audits', 'action' => 'Open evaluation workspaces',
            ],
            'corrective' => [
                'label' => 'Review Corrective Actions', 'icon' => 'clipboard-check',
                'intro' => 'Follow corrective evidence through the authorized CAR workflow.',
                'steps' => [
                    ['Locate the CAR', 'Use Corrective Actions to inspect the nonconforming check and its recorded corrective status within your access scope.'],
                    ['Review corrective evidence', 'Evaluate the corrective documentation separately from during-audit evidence presentation. Uploading evidence does not change N/C/O or close a CAR.'],
                    ['Record the authorized outcome', 'Use the existing status actions and required remarks. Keep the original finding, evidence, and status history connected.'],
                ],
                'href' => '/auditor/corrective-actions', 'action' => 'Open Corrective Actions',
            ],
            'standards' => [
                'label' => 'Standards & Document Control', 'icon' => 'book-open',
                'intro' => 'Apply verified criteria without extending document access.',
                'steps' => [
                    ['Confirm the approved criteria', 'Use the existing ISO 9001:2015 internal-audit and ARTA reference identifiers in the assignment. Ask the audit coordinator which approved criteria apply.'],
                    ['Inspect authorized documents', 'Open Documents for records your account is allowed to access. Omit restricted titles and identifiers from references.'],
                    ['Respect controlled-form restrictions', 'Confirm institutional permission before reproducing controlled forms or storing protected QMS content.'],
                ],
                'href' => '/auditor/archives', 'action' => 'Open Documents',
            ],
        ],
    ],
];
