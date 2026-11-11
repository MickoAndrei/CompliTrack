# CompliTrack demo kit for USTP Claveria CET

Use this kit to populate a local or staging CompliTrack instance for the Department of Information Technology, College of Engineering and Technology. It contains 27 editable Word documents and one linked JSON dataset. The sample covers BSIT course-file controls and computer laboratory records.

All included records are fictional training material. The photographed 2024 USTP Audit Plan guided the plan fields, audit team section and schedule columns. The kit records its historical reference code, `FM-USTP-007-2`, separately from the mock plan code, `DEMO-FM-USTP-007-2`. The supplied photographs establish the structure of that historical plan. The current revision, controlled DPM contents, other form codes and approval rules remain unverified.

The supplied Management Review Minutes show the USTP logo and the four fields used in the repeating control box. The supplied TCM 317a syllabus shows form `FM-USTP-ACAD-01`, course information, outcomes, an outline, grading and approval roles. The mock syllabus uses `DEMO-FM-USTP-ACAD-01`, new BSIT content and fictional role labels. The samples' names, signatures and official approval records are excluded from the demo files.

The dataset represents a snapshot on **11 September 2026**, Philippine time. It uses ISO 9001:2015 as a historical reference for the August and September scenarios. Individual checks cite the matching section of a supplied mock procedure so you can see the requirement behind each result.

## Choose a scenario

| Audit reference | What to open | What to expect |
|---|---|---|
| IQA-2026-001-DEMO | Completed audit for management of learning and laboratory controls | 7 evaluated items. 3 C, 3 N and 1 O. Three CARs are linked to nonconformities. The observation has no CAR. |
| IQA-2026-002-DEMO | In-progress spot check | 2 C and 1 pending. The pending item requests a signed original and has no compliance result. |

Open item 02 of the completed audit first. It traces a missing syllabus release entry to finding **FND-DEMO-001**, then to **CAR-DEMO-001**. Evidence **EV-03** captures the original gap. Evidence **EV-09** shows the response that was verified before closure. Open item 01 in the same audit to see a physical original reviewed with a reference and verification note. The pack contains no copy of the original.

## Academics folder

| Folder under `documents` | Records to open |
|---|---|
| `Academics` | Management of Learning DPM and Course File Index and Completeness Checklist |
| `Academics/1.2_Approved_OBE_Syllabi` | Filled BSIT syllabus, syllabus review and approval record, and syllabus release tracker |
| `Academics/1.3_Instructional_Materials_Validation` | Class delivery and assessment log, instructional materials validation, and the QUIZ-02 table of specifications |
| `Academics/1.4_Class_Attendance_and_Monitoring` | Student and faculty attendance records. This is a demo extension to the supplied folder guide. |
| `00_Audit_Management` | Opening and closing meeting attendance for the completed audit |

In `IQA-2026-001-DEMO`, item 01 links to the filled syllabus and approval record, item 03 links to class attendance and instructional materials, and item 04 links to the course-file index. The September spot check reuses the student attendance register for section BSIT-DEMO-3C.

## Corrective Action Report logs

The two editable landscape files in `documents/99_Audit_Findings_and_Dispositions` use the table shown on PDF page 62 of the supplied September 2022 Management Review Minutes. The historical source code is `FM-USTP-009-2`. Both mock files use `DEMO-FM-USTP-009-2` and the same table structure.

| Log date | State of the three existing demo CARs |
|---|---|
| 2 September 2026 | One for verification and two open |
| 11 September 2026 | One closed out, one for verification, and one open |

The rows represent two snapshots of the same cases. In the mock log, the initial submission date is the date the case was raised, and the three cases use the demo classification Minor. Pass and actual close-out date are filled only after verification and closure. The JSON stores the snapshots under `correctiveActionLogs`, with entries keyed to the existing CAR and finding IDs.

## Document types

| Code | Editable records |
|---|---|
| DPM-USTP-CET-001 and DPM-USTP-CET-002 | Management of Learning and Computer Laboratory Readiness and Maintenance |
| FM-USTP-CET-01 through FM-USTP-CET-04 | Syllabus review and release tracker, class delivery and assessment log, laboratory readiness checklist, equipment maintenance and fault log |
| FM-USTP-CET-05 | Two corrective evidence submissions for separate CARs |
| DEMO-FM-USTP-007-2 and FM-USTP-CET-CK | An audit plan and an auditor-authored checklist for each audit |
| FM-USTP-CET-AR and FM-USTP-CET-CA | One closing report and three CAR records |
| DEMO-FM-USTP-009-2 | Two dated snapshots of the CAR log |
| FM-USTP-CET-DC | Document and record master list |
| DEMO-FM-USTP-ACAD-01 | Filled BSIT outcome-based syllabus |
| DEMO-FM-USTP-CET-AUD-02 and 11–16 | Syllabus review, student and faculty attendance, materials validation, assessment specifications, course-file index, and audit meeting attendance |

A form code identifies a document type. The JSON `documentId` identifies a particular record. For example, the two FM-USTP-CET-05 submissions share a form code and have different document IDs and evidence references.

## Populate a demo instance

1. **Set up the scope and accounts.** Use the IT department under CET Claveria in your demo instance. Map the `people` IDs to existing demo accounts or create accounts through the app's usual user-management flow. The names in the dataset identify roles. They carry no login credentials.
2. **Create the two audits and their assignments.** Use `audits` for the plans and `assignments` for participants. The `planTeam` field holds the leader, team members and secretary. Each `schedule` row links its time and process to the assigned auditor, auditee and applicable mock procedures. Use the supplied DOCX files where the UI supports plan or reference attachments. Match fixture statuses to the actual application states.
3. **Open each audit as the assigned auditor.** Enter `checklistItems` in item-number order. Use `checkItem` as the question and `procedureCode` plus `procedureSection` as the reference. `auditCompliance` is C, N, O or null. Null is pending.
4. **Add evidence by presentation method.** For Digital file, resolve `attachmentDocumentId` in `documents` and upload the referenced DOCX through the normal application workflow. For Digital reference, enter the permitted reference and locator. For Physical original, enter the reference, locator and actual mock verification state without an attachment. The September original remains requested. A document listed for more than one evidence entry can be reused if the app supports linking an existing file.
5. **Record results and follow-up.** Link `findings` to evaluated items. Link the three CARs to their N findings. Keep initial audit evidence separate from corrective-action evidence. If the app automatically opens a CAR for an N result, map the fixture to the generated CAR. Use the normal submit and verify actions, or the equivalent test fixture services, to reproduce the supplied history without creating duplicate CARs.

For automated setup, use `complitrack_cet_demo.json` as the input to a local seed adapter. Inspect your branch's README, models and seeders first. The JSON is a portable fixture and needs field, role and status mapping for your branch. Keep fixture keys stable, resolve real database IDs as you create each record, and use the normal upload service for attachments. If status transitions or approval history are stored separately, map `statusHistory` to that workflow. Treat attachments for which the app has no equivalent feature as manual demo steps. The CompliTrack branch and import interface were unavailable for an end-to-end import test.

## Record links and expected results

| Dataset section | Stable key | Main links |
|---|---|---|
| `departments` | `departmentId` | Department scope |
| `people` | `personId` | Department when applicable |
| `audits` | `auditId` | Department, plan team, scheduled auditor and auditee, and document IDs |
| `assignments` | `assignmentId` | Audit and person |
| `checklistItems` | `checkItemId` | Audit, procedure document and auditee |
| `evidence` | `evidenceId` | Audit, check item, optional attachment and optional CAR |
| `findings` | `findingId` | Audit, check item, initial evidence and optional CAR |
| `correctiveActions` | `carId` | Audit, finding, owner, corrective evidence and CAR document |
| `documents` | `documentId` | Relative DOCX path and SHA-256 checksum |

At the snapshot, both audits together have **10 checklist items**, **9 evaluated**, **5 C**, **3 N**, **1 O**, **1 pending** and **21 evidence records**. The three CAR states are **Open**, **For Verification** and **Closed**. If a dashboard groups every outstanding CAR under Open, it should show **2 outstanding** and **1 closed**. These totals apply to the full demo scope. Each role's visible records depend on its assignments and permissions.

## Public references and basis for the mock records

- College name and department scope. [USTP Claveria CET](https://www.ustp.edu.ph/claveria/cet/)
- Claveria IQA context and examples of audited topics. [USTP Claveria IQA](https://www.ustp.edu.ph/ustp-claveria-undergoes-system-wide-iso-90012015-internal-quality-audit-for-recertification/) · [USTP first 2025 IQA](https://www.ustp.edu.ph/ustp-conducts-first-internal-quality-audit-for-cy-2025/)
- Published reference to a Documented Procedures Manual on Management of Learning, on printed page 54. [USTP 2019 semiannual report](https://www.ustp.edu.ph/wp-content/uploads/2020/05/SEMI-ANNUAL-REPORT-05.pdf)
- Example of form document-control metadata. [USTP Registrar re-admission form](https://www.ustp.edu.ph/wp-content/uploads/2022/08/FM-USTP-RGTR-05-RE-ADMISSION-FORM.pdf)
- Syllabus and review resources and CAR terminology. [USTP CiTL faculty resources](https://citl.ustp.edu.ph/our-services/new-faculty-resources) · [USTP ISO awareness orientation](https://www.ustp.edu.ph/ustp-conducts-iso-awareness-orientation/)
- Edition used for the dated mock audit. [ISO 9001:2015](https://www.iso.org/standard/62085.html)

The May 2024 audit plan photos show `DPM-USTP-ACAD-001` as the Management of Learning reference for syllabus approval and implementation. The demo records this historical code alongside the relevant schedule rows and applies the separate mock DPM to its checklist results. The checklist fields and digital-first evidence model follow the CompliTrack project notes based on the Claveria auditor interviews.
