<?php
namespace App\Services;

use App\Models\{ArchiveDocument, Audit, AuditLog, College, ComplianceCheck, Department, Notification, QmsCategory, User};
use App\Support\MongoId;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use MongoDB\BSON\{ObjectId, UTCDateTime};
use RuntimeException;

/** Development fixture adapter: never replaces existing accounts or institutional records. */
class QmsDevelopmentData
{
    public const KEY = 'cet-qms-development-v1';
    private array $accounts = [];
    private array $accountReport = [];
    private array $documents = [];
    private array $auditReport = [];
    private array $manifest = [];
    private string $private;
    private array $passwords = [];
    private array $unitNames = [];
    private array $categoryNames = [];

    public function run(bool $apply = false, ?string $atlEmail = null): array
    {
        if (!app()->environment(['local', 'development', 'testing'])) { throw new RuntimeException('Mock seeding is disabled outside development/testing.'); }
        $admins = User::where('roleId', 1)->where('isActive', true);
        if ($atlEmail) { $admins->where('email', $atlEmail); }
        $admins = $admins->get();
        if ($admins->count() !== 1) { throw new RuntimeException('Identify one existing active Admin/ATL with --atl-email. No Admin account will be created.'); }
        $atl = $admins->first();
        $originalAtl = $atl->getRawOriginal();
        $units = Department::where('collegeName', 'CET')->where('unitType', 'academic_department')->where('ownershipVerified', true)->where('isActive', true)->get()->keyBy('departmentCode');
        if (count($units) !== 5 || array_diff(array_keys(config('qms.departments')), $units->keys()->all())) { throw new RuntimeException('The five verified CET departments must exist before seeding.'); }
        $college = College::where('collegeCode', 'CET')->where('isActive', true)->firstOrFail();
        $office = Department::where('unitType', 'college_office')->where('collegeId', new ObjectId((string) $college->getKey()))->where('departmentName', 'Office of the Dean')->firstOrFail();
        $report = ['database' => DB::connection('mongodb')->getDatabaseName(), 'seedKey' => self::KEY,
            'atl' => ['id' => (string) $atl->getKey(), 'email' => $atl->email, 'status' => 'reused_unchanged'], 'departments' => $units->map(fn ($unit) => (string) $unit->getKey())->all(), 'applied' => false];
        if (!$apply) { return $report; }
        $this->private = config('qms.development_directory', storage_path('app/private/qms-development'));
        if (!is_dir($this->private) && !mkdir($this->private, 0700, true)) { throw new RuntimeException('Cannot create private development-data directory.'); }
        $lock = fopen($this->private.'/seed.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Another development-data seed is running.'); }
        try {
            $checkpoint = DB::connection('mongodb')->getMongoDB()->migrations->findOne(['migration' => self::KEY]);
            if ($checkpoint) {
                $saved = json_decode(file_get_contents($this->private.'/report.json'), true, flags: JSON_THROW_ON_ERROR);
                return $saved + ['alreadySeeded' => true];
            }
            $backup = $this->backup();
            $db = DB::connection('mongodb')->getMongoDB();
            foreach (['users', 'audits', 'archive_documents', 'compliance_checks'] as $collection) {
                $db->$collection->createIndex(['mockSeedKey' => 1], ['unique' => true, 'partialFilterExpression' => ['mockSeedKey' => ['$type' => 'string']]]);
            }
            $dedupeExists = false;
            if (in_array('notifications', iterator_to_array($db->listCollectionNames()), true)) {
                foreach ($db->notifications->listIndexes() as $index) { if ($index->getKey() === ['dedupeKey' => 1] && $index->isUnique()) { $dedupeExists = true; } }
            }
            if (!$dedupeExists) { $db->notifications->createIndex(['dedupeKey' => 1], ['unique' => true, 'partialFilterExpression' => ['dedupeKey' => ['$type' => 'string']], 'name' => 'qms_notification_dedupe']); }
            $credentialsPath = $this->private.'/new-account-credentials.json';
            $this->passwords = is_file($credentialsPath) ? json_decode(file_get_contents($credentialsPath), true, flags: JSON_THROW_ON_ERROR) : [];
            $this->accounts['atl'] = $atl;
            $this->accountReport[] = ['key' => 'atl', 'id' => (string) $atl->getKey(), 'email' => $atl->email, 'roleId' => 1, 'status' => 'reused_unchanged'];
            foreach ($units as $code => $unit) {
                foreach (['department_head', 'department_secretary', 'faculty_staff'] as $responsibility) {
                    $this->accounts[$code.':'.$responsibility] = $this->account($code.':'.$responsibility, $responsibility, 3, $unit);
                }
                $this->accounts[$code.':auditor'] = $this->account($code.':auditor', 'auditor', 2, $unit);
                if (!$unit->headUserId) { $unit->headUserId = $this->accounts[$code.':department_head']->getKey(); $unit->save(); }
            }
            foreach (['college_dean', 'college_secretary', 'cluster_administrator'] as $responsibility) {
                $this->accounts[$responsibility] = $this->account($responsibility, $responsibility, 3, $office, $college);
            }
            foreach (['D-MGMT' => 'College Management Reviews', 'D-BOARD' => 'Board Resolutions & Directives', 'D-PLAN' => 'College Development Plans'] as $code => $name) {
                QmsCategory::firstOrCreate(['collegeId' => new ObjectId((string) $college->getKey()), 'code' => $code], ['name' => $name, 'unitType' => 'college_office', 'parentId' => null, 'provisional' => true, 'isActive' => true]);
            }
            $kitRoot = base_path('dataset_files/CompliTrack_CET_Demo_Kit-3');
            $kit = json_decode(file_get_contents($kitRoot.'/complitrack_cet_demo.json'), true, flags: JSON_THROW_ON_ERROR);
            $super = User::where('roleId', 0)->where('isActive', true)->firstOrFail();
            app(MockArchiveImporter::class)->run($kitRoot, $super, true);
            foreach ($kit['documents'] as $source) {
                $archive = ArchiveDocument::where('sourceImportKey', $kit['datasetId'].':'.$source['documentId'])->firstOrFail();
                $this->documents[$source['documentId']] = $archive;
                $this->manifest[] = $this->manifest($archive, 'existing_kit_document');
            }
            foreach ($units as $code => $unit) { $this->departmentDocuments($code, $unit); }
            foreach (['D-MGMT', 'D-BOARD', 'D-PLAN'] as $category) {
                $this->document('office:'.$category, $this->accounts['college_secretary'], $office, $category,
                    'Synthetic '.QmsCategory::where('code', $category)->firstOrFail()->name, 'file', 'department');
            }
            $people = ['DEMO-COORD' => $atl, 'DEMO-ATL' => $atl, 'DEMO-AUD' => $this->accounts['CET-IT:auditor'],
                'DEMO-CHAIR' => $this->accounts['CET-IT:department_head'], 'DEMO-FAC' => $this->accounts['CET-IT:faculty_staff'], 'DEMO-LAB' => $this->accounts['CET-IT:department_secretary']];
            foreach ($kit['audits'] as $source) { $this->kitAudit($source, $kit, $people, $units['CET-IT']); }
            $matrix = [
                ['scheduled', 'CET-IT', 'draft', []], ['checklist-draft', 'CET-ABE', 'draft', [null, null]],
                ['evidence-request', 'CET-ENV', 'ready', [null]], ['digital-submission', 'CET-FPT', 'ready', [null]],
                ['physical-review', 'CET-HM', 'ready', ['C', null]], ['submitted', 'CET-ABE', 'submitted', ['C', 'N', 'O']],
                ['returned', 'CET-ENV', 'returned', ['C', 'N', 'O']], ['completed', 'CET-FPT', 'accepted', ['C', 'N', 'O']],
                ['resubmitted', 'CET-HM', 'submitted', ['C', 'N', 'O']],
            ];
            foreach ($matrix as [$key, $code, $status, $results]) { $this->scenarioAudit($key, $units[$code], $status, $results); }
            if (json_encode($originalAtl) !== json_encode($atl->fresh()->getRawOriginal())) { throw new RuntimeException('The existing Admin/ATL changed unexpectedly.'); }
            $report += ['backup' => $backup, 'accounts' => $this->accountReport, 'documents' => $this->manifest, 'audits' => $this->auditReport,
                'credentialsFile' => $credentialsPath, 'createdAccounts' => count(array_filter($this->accountReport, fn ($row) => $row['status'] === 'created')),
                'reusedAccounts' => count(array_filter($this->accountReport, fn ($row) => $row['status'] === 'reused_unchanged')),
                'carClosurePolicy' => 'Assigned auditor closes CARs. Existing Admin/ATL accepts/returns audit submissions. Source ATL closure remains source metadata only.'];
            $report['applied'] = true;
            $report['counts'] = $this->counts();
            file_put_contents($this->private.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
            $db->migrations->insertOne(['migration' => self::KEY, 'batch' => 1, 'appliedAt' => new UTCDateTime(), 'backupPath' => $backup, 'atlId' => new ObjectId((string) $atl->getKey())]);
            return $report;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function account(string $key, string $responsibility, int $role, Department $unit, ?College $college = null): User
    {
        $existing = User::where('isActive', true)->where('roleId', $role);
        if ($role === 2) {
            $existing->whereIn('assignedDepartmentIds', [new ObjectId((string) $unit->getKey()), (string) $unit->getKey()]);
        } elseif ($college) {
            $existing->where('archiveResponsibility', $responsibility)->whereIn('archiveCollegeIds', [new ObjectId((string) $college->getKey()), (string) $college->getKey()]);
        } else {
            $existing->whereIn('departmentId', [new ObjectId((string) $unit->getKey()), (string) $unit->getKey()]);
            if ($responsibility === 'faculty_staff') { $existing->where(fn ($query) => $query->where('archiveResponsibility', 'faculty_staff')->orWhereNull('archiveResponsibility')); }
            else { $existing->where('archiveResponsibility', $responsibility); }
        }
        $matches = $existing->get();
        if ($matches->count() > 1) { throw new RuntimeException('Multiple equivalent accounts for '.$key.'. Provide an explicit mapping before seeding.'); }
        $user = $matches->first(); $status = 'reused_unchanged';
        if (!$user) {
            $email = 'qms.'.strtolower(str_replace([':', '_'], ['.', '-'], $key)).'@example.test';
            if (User::where('email', $email)->exists()) { throw new RuntimeException('An incompatible account already uses '.$email); }
            $password = $this->passwords[$email]['password'] ?? Str::random(32);
            $this->passwords[$email] = ['password' => $password, 'responsibility' => $responsibility, 'department' => $unit->departmentName];
            $payload = json_encode($this->passwords, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($this->private.'/new-account-credentials.json', $payload, LOCK_EX) !== strlen($payload)) { throw new RuntimeException('Cannot save new-account credentials; account creation stopped.'); }
            $user = User::create(['fullName' => '[MOCK] '.($college ? 'CET ' : $unit->departmentName.' ').ucwords(str_replace('_', ' ', $responsibility)),
                'email' => $email, 'password' => Hash::make($password), 'userCode' => app(CounterService::class)->next($role === 2 ? 'AUD' : 'ATE'),
                'roleId' => $role, 'departmentId' => new ObjectId((string) $unit->getKey()), 'assignedDepartmentIds' => $role === 2 ? [new ObjectId((string) $unit->getKey())] : [],
                'isActive' => true, 'archiveResponsibility' => $role === 2 ? null : $responsibility,
                'archiveUnitIds' => $college || $role === 2 ? [] : [new ObjectId((string) $unit->getKey())],
                'archiveCollegeIds' => $college ? [new ObjectId((string) $college->getKey())] : []]);
            $user->forceFill(['mockSeedKey' => self::KEY.':user:'.$key])->save(); $status = 'created';
        }
        $this->accountReport[] = ['key' => $key, 'id' => (string) $user->getKey(), 'email' => $user->email, 'roleId' => (int) $user->roleId,
            'department' => $unit->departmentName, 'responsibility' => $responsibility, 'status' => $status];
        return $user;
    }

    private function departmentDocuments(string $code, Department $unit): void
    {
        $secretary = $this->accounts[$code.':department_secretary']; $faculty = $this->accounts[$code.':faculty_staff'];
        $public = $this->document($code.':public', $secretary, $unit, '1.0.2', 'Synthetic course-file register', 'file', 'department');
        $this->documents[$code.':public'] = $public;
        $versionKey = self::KEY.':document:'.$code.':version2';
        $version = ArchiveDocument::where('mockSeedKey', $versionKey)->first();
        if (!$version) {
            $request = $this->uploadRequest($secretary, $unit, '1.0.2', 'Synthetic course-file register — revision 2', 'file', 'department', $code.' revision 2');
            $version = ArchiveDocument::findOrFail(app(\App\Http\Controllers\Api\V1\ArchiveController::class)->version($request, (string) $public->getKey())->getData(true)['data']['_id']);
            $version->forceFill(['mockSeedKey' => $versionKey, 'sourceMetadata' => ['isMock' => true, 'revision' => '02', 'effectiveDate' => now()->toDateString(), 'effectivity' => 'Development simulation; not institutional approval']])->save();
        }
        $this->manifest[] = $this->manifest($version, 'controlled_version_2');
        $restricted = $this->document($code.':restricted', $faculty, $unit, '2.0.1', 'Synthetic faculty development record', 'file', 'restricted');
        $restricted->forceFill(['accessUserIds' => [new ObjectId((string) $this->accounts[$code.':department_head']->getKey())]])->save();
        $this->documents[$code.':physical'] = $this->document($code.':physical', $secretary, $unit, '1.0.2', 'Synthetic signed-original register', 'physical-original', 'department');
        $this->document($code.':reference', $secretary, $unit, '3.0.2', 'Synthetic document reference', 'digital-reference', 'department');
    }

    private function document(string $key, User $owner, Department $unit, string $category, string $title, string $type, string $visibility): ArchiveDocument
    {
        $seedKey = self::KEY.':document:'.$key;
        $archive = ArchiveDocument::where('mockSeedKey', $seedKey)->first();
        if (!$archive) {
            $request = $this->uploadRequest($owner, $unit, $category, $title, $type, $visibility, $key);
            $archive = ArchiveDocument::findOrFail(app(\App\Http\Controllers\Api\V1\ArchiveController::class)->upload($request)->getData(true)['data']['_id']);
            $archive->forceFill(['mockSeedKey' => $seedKey, 'tags' => ['MOCK', self::KEY], 'sourceMetadata' => ['isMock' => true, 'scenario' => $key, 'revision' => '01', 'effectiveDate' => now()->toDateString()]])->save();
        }
        $this->manifest[] = $this->manifest($archive, 'scenario_document');
        return $archive;
    }

    private function uploadRequest(User $owner, Department $unit, string $category, string $title, string $type, string $visibility, string $key): Request
    {
        $category = QmsCategory::where('collegeId', new ObjectId((string) $unit->collegeId))->where('code', $category)->firstOrFail();
        $data = ['purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => (string) $category->getKey(), 'title' => '[MOCK] '.$title,
            'description' => 'Synthetic fictional development data. No real student/personnel information, signatures, official form layout, or approval.',
            'recordType' => $type, 'visibility' => $visibility, 'copyAuthorized' => true, 'downloadAllowed' => true,
            'physicalLocation' => 'Mock '.$unit->departmentName.' cabinet A', 'documentReference' => 'SYNTHETIC-'.strtoupper(str_replace(':', '-', $key))];
        $files = [];
        if ($type === 'file') {
            $path = $this->private.'/source-'.hash('sha256', $key).'.pdf';
            file_put_contents($path, $this->pdf('SYNTHETIC DEMONSTRATION ONLY', $unit->departmentName.' | '.$title.' | '.$key.' | Not an official USTP record.'));
            $files['file'] = new UploadedFile($path, 'MOCK-'.substr(hash('sha256', $key), 0, 12).'.pdf', 'application/pdf', null, true);
            if ($key === 'CET-IT:public') {
                $path = base_path('dataset_files/CompliTrack_CET_Demo_Kit-3/documents/Academics/1.2_Approved_OBE_Syllabi/DEMO-FM-USTP-ACAD-01_Systems_Integration_BSIT_DEMO_3A.docx');
                $files['file'] = new UploadedFile($path, 'MOCK-IT-syllabus.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
                $data['title'] = '[MOCK] IT syllabus uploaded by Department Secretary';
            }
        }
        return $this->request($owner, $data, $files);
    }

    private function kitAudit(array $source, array $kit, array $people, Department $unit): void
    {
        $key = 'kit:'.$source['auditId'];
        $auditor = $people['DEMO-AUD']; $atl = $people['DEMO-ATL']; $items = []; $requests = []; $publishedFor = [];
        foreach ($kit['checklistItems'] as $item) {
            if ($item['auditId'] !== $source['auditId']) { continue; }
            $id = $this->uuid($item['checkItemId']); $evidence = [];
            foreach ($kit['evidence'] as $entry) {
                if ($entry['checkItemId'] !== $item['checkItemId'] || !empty($entry['carId'])) { continue; }
                $file = empty($entry['attachmentDocumentId']) ? null : $this->documents[$entry['attachmentDocumentId']];
                $owner = $people[$entry['submittedBy'] ?? 'DEMO-LAB'] ?? $people['DEMO-LAB'];
                $requestId = $this->uuid($entry['evidenceId']); $reviewed = $entry['reviewStatus'] === 'Reviewed';
                $method = match ($entry['presentationMode']) { 'Physical original' => 'physical-original', 'Digital file' => 'file', default => 'digital-reference' };
                $response = ['submissionKey' => $this->uuid('submission:'.$entry['evidenceId']), 'method' => $method, 'reference' => $entry['reference'], 'notes' => '[MOCK] '.$entry['locator'],
                    'author' => (string) $owner->getKey(), 'at' => $this->date($entry['submittedOn'] ?? $source['plannedDate']), 'archiveId' => $file ? (string) $file->getKey() : null, 'availability' => 'available'];
                if ($reviewed) { $response['review'] = ['decision' => 'reviewed', 'feedback' => '[MOCK] '.$entry['auditReviewNote'], 'signature' => $method === 'physical-original' ? 'verified' : null, 'author' => (string) $auditor->getKey(), 'at' => $this->date($entry['reviewedOn'])]; }
                $request = ['id' => $requestId, 'itemId' => $id, 'auditeeId' => (string) $owner->getKey(), 'description' => '[MOCK] '.$entry['title'], 'referenceStandard' => 'ISO9001-9.2',
                    'allowUpload' => $method === 'file', 'status' => $reviewed ? 'reviewed' : 'requested', 'createdAt' => $this->date($entry['requestedOn'] ?? $source['plannedDate']),
                    'responses' => empty($entry['submittedOn']) ? [] : [$response]];
                if ($reviewed) { $evidence[] = ['method' => $method === 'file' || $method === 'digital-reference' ? 'digital' : 'physical-original', 'reference' => $entry['reference'],
                    'notes' => '[MOCK] '.$entry['auditReviewNote'], 'signature' => $method === 'physical-original' ? 'verified' : null, 'archiveId' => $file ? (string) $file->getKey() : null,
                    'requestId' => $requestId, 'reviewedBy' => (string) $auditor->getKey(), 'reviewedAt' => $this->date($entry['reviewedOn'])]; }
                $publicationKey = $id.':'.$owner->getKey();
                if ($source['status'] === 'Completed' && $item['auditCompliance'] && !isset($publishedFor[$publicationKey])) {
                    $request['published'] = ['result' => $item['auditCompliance'], 'summary' => '[MOCK] '.$item['auditRemarks'], 'author' => (string) $auditor->getKey(), 'at' => $this->date($source['completedOn'])];
                    $publishedFor[$publicationKey] = true;
                }
                $requests[] = $request;
            }
            $finding = collect($kit['findings'])->firstWhere('checkItemId', $item['checkItemId']);
            $items[] = ['id' => $id, 'question' => $item['checkItem'], 'reference' => $item['procedureCode'].' section '.$item['procedureSection'],
                'result' => $item['auditCompliance'], 'remarks' => '[MOCK] '.($item['auditRemarks'] ?? ''), 'finding' => $finding ? '[MOCK] '.$finding['statement'] : null, 'evidence' => $evidence,
                'evaluatedBy' => (string) $auditor->getKey(), 'evaluatedAt' => $item['auditCompliance'] ? $this->date($item['evaluatedOn']) : null];
        }
        $audit = $this->audit($key, $unit, $source['status'] === 'Completed' ? 'accepted' : 'ready', $items, $requests, $source['auditId'], $source);
        $attachments = [];
        foreach (['planDocumentId', 'checklistDocumentId', 'reportDocumentId'] as $field) {
            if (empty($source[$field])) { continue; }
            $file = $this->documents[$source[$field]];
            $attachments[] = new ObjectId((string) $file->getKey());
            DB::connection('mongodb')->getMongoDB()->archive_documents->updateOne(['_id' => new ObjectId((string) $file->getKey())], ['$addToSet' => ['sharedAuditIds' => new ObjectId((string) $audit->getKey())]]);
        }
        $audit->forceFill(['planArchiveIds' => $attachments])->save();
        foreach ($kit['correctiveActions'] as $car) {
            if ($car['auditId'] !== $source['auditId']) { continue; }
            $finding = collect($kit['findings'])->firstWhere('findingId', $car['findingId']);
            $responses = [];
            foreach ($kit['evidence'] as $entry) {
                if (($entry['carId'] ?? null) !== $car['carId']) { continue; }
                $responses[] = ['submissionKey' => $this->uuid($entry['evidenceId']), 'method' => 'digital-reference', 'reference' => $entry['reference'],
                    'notes' => '[MOCK] '.$entry['title'], 'author' => (string) $people[$car['ownerId']]->getKey(), 'at' => $this->date($entry['submittedOn']),
                    'archiveId' => isset($this->documents[$entry['attachmentDocumentId'] ?? '']) ? (string) $this->documents[$entry['attachmentDocumentId']]->getKey() : null];
            }
            $status = match ($car['status']) { 'Closed' => 'closed', 'For Verification' => 'in_progress', default => 'open' };
            $history = array_map(fn ($event) => ['status' => match ($event['status']) { 'Closed' => 'closed', 'For Verification' => 'in_progress', default => 'open' },
                'changedByUserId' => new ObjectId((string) $auditor->getKey()), 'timestamp' => new UTCDateTime(new \DateTimeImmutable($this->date($event['date']))), 'remarks' => '[MOCK] '.$event['note']], $car['statusHistory']);
            $this->check($car['carId'], $unit, $people[$car['ownerId']], $status, $history, $responses, $audit, $this->uuid($finding['checkItemId']), $car + ['finding' => $finding]);
        }
    }

    private function scenarioAudit(string $key, Department $unit, string $status, array $results): void
    {
        $code = $unit->departmentCode; $auditor = $this->accounts[$code.':auditor']; $owner = $this->accounts[$code.':department_secretary'];
        $items = []; $requests = [];
        foreach ($results as $index => $result) {
            $id = $this->uuid($key.':item:'.$index); $requestId = $this->uuid($key.':request:'.$index);
            $file = $this->documents[$code.':public']; $physical = $key === 'physical-review' && $index === 0;
            $submitted = $result !== null || $key === 'digital-submission';
            $response = ['submissionKey' => $this->uuid($key.':response:'.$index), 'method' => $physical ? 'physical-original' : 'file', 'reference' => 'SYNTHETIC-'.$code.'-'.$index,
                'notes' => '[MOCK] Authorized demonstration evidence.', 'author' => (string) $owner->getKey(), 'at' => now()->utc()->toIso8601String(),
                'archiveId' => $physical ? null : (string) $file->getKey(), 'availability' => 'available'];
            if ($result) { $response['review'] = ['decision' => 'reviewed', 'feedback' => '[MOCK] Evidence reviewed for simulation.', 'signature' => $physical ? 'verified' : null, 'author' => (string) $auditor->getKey(), 'at' => now()->utc()->toIso8601String()]; }
            $request = ['id' => $requestId, 'itemId' => $id, 'auditeeId' => (string) $owner->getKey(), 'description' => '[MOCK] Provide '.($physical ? 'a signed original for inspection' : 'the permitted course-file record'),
                'referenceStandard' => 'ISO9001-9.2', 'allowUpload' => !$physical, 'status' => $result ? 'reviewed' : ($submitted ? 'response_submitted' : 'requested'),
                'createdAt' => now()->utc()->toIso8601String(), 'responses' => $submitted ? [$response] : []];
            if ($status === 'accepted') { $request['published'] = ['result' => $result, 'summary' => '[MOCK] '.($result === 'N' ? 'Fictional missing register entry.' : 'Fictional demonstration result.'), 'author' => (string) $auditor->getKey(), 'at' => now()->utc()->toIso8601String()]; }
            if ($status !== 'draft') { $requests[] = $request; }
            $items[] = ['id' => $id, 'question' => '[MOCK] Is synthetic control '.($index + 1).' supported by the permitted record?', 'reference' => 'ISO 9001:2015 clause 9.2 — demonstration criterion',
                'result' => $result, 'remarks' => $result ? '[MOCK] Demonstration evaluation '.$result : '', 'finding' => $result === 'N' ? '[MOCK] Fictional missing register entry; no actual departmental noncompliance is alleged.' : null,
                'evidence' => $result ? [['method' => $physical ? 'physical-original' : 'digital', 'reference' => $response['reference'], 'notes' => '[MOCK] Recorded review.',
                    'signature' => $physical ? 'verified' : null, 'archiveId' => $physical ? null : (string) $file->getKey(), 'reviewedBy' => (string) $auditor->getKey(), 'reviewedAt' => now()->utc()->toIso8601String()]] : []];
        }
        $audit = $this->audit($key, $unit, $status, $items, $requests, 'QMS-DEMO-'.strtoupper($key));
        if ($key === 'physical-review') {
            $this->documents[$code.':physical']->forceFill(['signatureStatus' => 'verified', 'signatureVerification' => ['isMock' => true,
                'auditId' => new ObjectId((string) $audit->getKey()), 'reviewedBy' => new ObjectId((string) $auditor->getKey()), 'at' => new UTCDateTime()]])->save();
        }
        foreach ($items as $item) {
            if ($item['result'] !== 'N') { continue; }
            $history = [['status' => 'open', 'changedByUserId' => new ObjectId((string) $auditor->getKey()), 'timestamp' => new UTCDateTime(), 'remarks' => '[MOCK] Fictional CAR raised.']];
            $responses = [];
            if (in_array($key, ['completed', 'resubmitted'], true)) {
                $history[] = ['status' => 'in_progress', 'changedByUserId' => new ObjectId((string) $auditor->getKey()), 'timestamp' => new UTCDateTime(), 'remarks' => '[MOCK] Awaiting verification.'];
                $responses[] = ['submissionKey' => $this->uuid($key.':corrective:1'), 'method' => 'digital-reference', 'reference' => 'SYNTHETIC-CORRECTION-1', 'notes' => '[MOCK] Revised register available.', 'author' => (string) $owner->getKey(), 'at' => now()->utc()->toIso8601String()];
                if ($key === 'resubmitted') { $responses[] = array_replace($responses[0], ['submissionKey' => $this->uuid($key.':corrective:2'), 'reference' => 'SYNTHETIC-CORRECTION-2', 'notes' => '[MOCK] Returned evidence revised and resubmitted.']); }
                if ($key === 'completed') { $history[] = ['status' => 'closed', 'changedByUserId' => new ObjectId((string) $auditor->getKey()), 'timestamp' => new UTCDateTime(), 'remarks' => '[MOCK] Assigned auditor verified the synthetic correction.']; }
            }
            $this->check('QMS-DEMO-CAR-'.$code, $unit, $owner, $history[array_key_last($history)]['status'], $history, $responses, $audit, $item['id']);
        }
    }

    private function audit(string $key, Department $unit, string $status, array $items, array $requests, string $code, array $source = []): Audit
    {
        $seedKey = self::KEY.':audit:'.$key; $auditor = $this->accounts[$unit->departmentCode.':auditor']; $atl = $this->accounts['atl'];
        if (in_array($status, ['submitted', 'accepted', 'returned'], true)) { AuditChecklist::validateSubmission($items); }
        $history = [['action' => 'assigned', 'actor' => (string) $atl->getKey(), 'at' => now()->utc()->toIso8601String(), 'notes' => '[MOCK] Seeded development assignment.']];
        if ($items) { $history[] = ['action' => $status === 'draft' ? 'add' : 'ready', 'actor' => (string) $auditor->getKey(), 'at' => now()->utc()->toIso8601String(), 'notes' => '[MOCK] Checklist fixture prepared.']; }
        if (in_array($status, ['submitted', 'accepted', 'returned'], true)) { $history[] = ['action' => 'submit', 'actor' => (string) $auditor->getKey(), 'at' => now()->utc()->toIso8601String(), 'notes' => '[MOCK] Seeded checklist submission.']; }
        if (in_array($status, ['accepted', 'returned'], true) || $key === 'resubmitted') {
            $history[] = ['action' => $status === 'accepted' ? 'accept' : 'return', 'actor' => (string) $atl->getKey(), 'at' => now()->utc()->toIso8601String(), 'notes' => '[MOCK] '.($status === 'accepted' ? 'Demonstration review accepted.' : 'Revise the demonstration evidence reference.')];
        }
        if ($key === 'resubmitted') { $history[] = ['action' => 'submit', 'actor' => (string) $auditor->getKey(), 'at' => now()->utc()->toIso8601String(), 'notes' => '[MOCK] Resubmitted after revision.']; }
        $previousStatus = 'draft';
        foreach ($history as &$event) {
            $event['previousStatus'] = $previousStatus;
            $event['previousItems'] = in_array($event['action'], ['assigned', 'add'], true) ? [] : $items;
            $event['isMockFixture'] = true;
            $previousStatus = match ($event['action']) { 'ready' => 'ready', 'submit' => 'submitted', 'accept' => 'accepted', 'return' => 'returned', default => $previousStatus };
        }
        unset($event);
        $audit = Audit::firstOrCreate(['mockSeedKey' => $seedKey], ['auditCode' => $code, 'title' => '[MOCK] '.($source['title'] ?? str_replace('-', ' ', $key)),
            'scope' => '[MOCK] Development-only process controls; no actual institutional findings.', 'criteria' => 'ISO 9001:2015 — synthetic historical scenario', 'schedule' => $source['plannedDate'] ?? now()->toDateString(),
            'departmentId' => $unit->getKey(), 'departmentName' => $unit->departmentName, 'auditorId' => $auditor->getKey(), 'reviewerId' => $atl->getKey(),
            'assignmentKey' => hash('sha256', $seedKey), 'status' => $status, 'version' => count($history), 'items' => $items, 'evidenceRequests' => $requests, 'interviews' => [],
            'history' => $history, 'sourceMetadata' => $source + ['isMock' => true, 'scenario' => $key], 'archivedAt' => $status === 'accepted' ? now()->utc()->toIso8601String() : null]);
        foreach ($requests as $request) {
            foreach ($request['responses'] as $response) {
                if (!empty($response['archiveId'])) {
                    DB::connection('mongodb')->getMongoDB()->archive_documents->updateOne(['_id' => MongoId::parse($response['archiveId'])],
                        ['$addToSet' => ['sharedAuditIds' => new ObjectId((string) $audit->getKey())]]);
                }
            }
        }
        $this->notify($auditor, $seedKey.':assigned', 'audit_assigned', $code.' assigned', $atl, '/audits/'.$audit->getKey());
        if (in_array($status, ['submitted', 'accepted', 'returned'], true)) { $this->notify($atl, $seedKey.':submitted', 'audit_submitted', $code.' submitted for ATL review', $auditor, '/audits/'.$audit->getKey()); }
        if ($status === 'returned' || $key === 'resubmitted') { $this->notify($auditor, $seedKey.':returned', 'audit_returned', $code.' returned for revision', $atl, '/audits/'.$audit->getKey()); }
        if ($key === 'resubmitted') { $this->notify($atl, $seedKey.':resubmitted', 'audit_submitted', $code.' resubmitted after revision', $auditor, '/audits/'.$audit->getKey()); }
        foreach ($requests as $request) {
            $owner = User::findOrFail($request['auditeeId']);
            $this->notify($owner, $seedKey.':request:'.$request['id'], 'evidence_requested', $request['description'], $auditor, '/auditee/evidence-requests#request-'.$request['id']);
            if ($request['responses']) { $this->notify($auditor, $seedKey.':response:'.$request['id'], 'evidence_submitted', $code.' evidence response submitted', $owner, '/audits/'.$audit->getKey()); }
            if (!empty($request['published'])) { $this->notify($owner, $seedKey.':published:'.$request['id'], 'audit_result_shared', $code.' accepted result shared', $auditor, '/auditee/my-findings'); }
        }
        $this->auditReport[] = ['key' => $key, 'id' => (string) $audit->getKey(), 'reference' => $audit->auditCode, 'department' => $unit->departmentName,
            'status' => $audit->status, 'items' => AuditChecklist::progress($audit->items ?? []), 'auditorId' => (string) $auditor->getKey(), 'atlId' => (string) $atl->getKey()];
        return $audit;
    }

    private function check(string $key, Department $unit, User $owner, string $status, array $history, array $responses, Audit $audit, string $itemId, array $source = []): void
    {
        $seedKey = self::KEY.':car:'.$key; $auditor = $this->accounts[$unit->departmentCode.':auditor'];
        $check = ComplianceCheck::where('mockSeedKey', $seedKey)->first();
        if (!$check) {
            $check = ComplianceCheck::create(['checkCode' => $key, 'departmentId' => $unit->getKey(), 'auditorId' => $auditor->getKey(), 'auditeeId' => $owner->getKey(),
                'referenceStandard' => 'ISO9001-9.2', 'result' => 'non_compliant', 'remarks' => '[MOCK] '.($source['finding']['statement'] ?? 'Fictional control gap for development testing only.'),
                'correctiveStatus' => $status, 'statusHistory' => $history, 'linkedArchiveIds' => array_values(array_unique(array_filter(array_map(fn ($response) => $response['archiveId'] ?? null, $responses)))),
                'dueDate' => $source['dueOn'] ?? now()->addDays(7), 'closedAt' => $status === 'closed' ? ($source['closedOn'] ?? now()) : null]);
            $check->forceFill(['mockSeedKey' => $seedKey, 'sourceAuditId' => new ObjectId((string) $audit->getKey()), 'sourceItemId' => $itemId, 'sourceMetadata' => $source + ['isMock' => true], 'evidenceResponses' => $responses])->save();
        }
        $this->notify($owner, $seedKey.':created', 'corrective_action_created', $key.' — '.str_replace('_', ' ', $status), $auditor, '/auditee/my-findings?detail='.$check->getKey());
        if ($responses) { $this->notify($auditor, $seedKey.':evidence', 'evidence_submitted', $key.' corrective evidence submitted', $owner, '/auditor/checks/'.$check->getKey()); }
    }

    private function notify(User $recipient, string $key, string $type, string $message, User $sender, string $destination): void
    {
        app(NotificationDispatchService::class)->persist($recipient->getKey(), $key, $type, '[MOCK] '.$message, 'Fictional development scenario. '.$message, $sender->getKey(), null, $destination);
    }
    private function request(User $actor, array $data, array $files = []): Request
    {
        $request = Request::create('/api/v1/archives/upload', 'POST', $data, [], $files); $request->setUserResolver(fn () => $actor); return $request;
    }
    private function uuid(string $value): string { $hash = hash('sha256', self::KEY.':'.$value); return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12); }
    private function date(?string $value): string { return \Carbon\CarbonImmutable::parse(($value ?: now()->toDateString()).' 09:00:00', 'Asia/Manila')->utc()->toIso8601String(); }
    private function manifest(ArchiveDocument $document, string $status): array
    {
        return ['id' => (string) $document->getKey(), 'filename' => $document->originalName, 'title' => $document->title,
            'departmentId' => (string) $document->departmentId, 'collegeId' => (string) $document->collegeId, 'categoryId' => (string) $document->categoryId,
            'department' => $this->unitNames[(string) $document->departmentId] ??= Department::find($document->departmentId)?->departmentName,
            'college' => config('qms.collegeName'),
            'category' => $this->categoryNames[(string) $document->categoryId] ??= QmsCategory::find($document->categoryId)?->name,
            'classification' => $document->visibility, 'presentation' => $document->recordType, 'version' => $document->versionNumber, 'storage' => $document->recordType === 'file' ? 'encrypted_private_file' : 'metadata_only', 'status' => $status];
    }
    public function counts(): array
    {
        $db = DB::connection('mongodb')->getMongoDB(); $counts = [];
        foreach (['users', 'audits', 'compliance_checks', 'archive_documents', 'notifications'] as $name) { $counts[$name] = $db->$name->countDocuments(); }
        return $counts;
    }
    private function backup(): string
    {
        $db = DB::connection('mongodb')->getMongoDB(); $snapshot = []; $indexes = []; $collections = iterator_to_array($db->listCollectionNames());
        foreach (['users', 'departments', 'colleges', 'qms_categories', 'audits', 'compliance_checks', 'archive_documents', 'notifications', 'audit_logs', 'counters', 'migrations', 'roles'] as $name) {
            $snapshot[$name] = [];
            foreach ($db->$name->find() as $row) { $snapshot[$name][] = \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($row)); }
            $indexes[$name] = [];
            if (in_array($name, $collections, true)) { foreach ($db->$name->listIndexes() as $index) { $indexes[$name][] = $index->__debugInfo(); } }
        }
        $plaintext = json_encode(['database' => $db->getDatabaseName(), 'documents' => $snapshot, 'indexes' => $indexes], JSON_THROW_ON_ERROR);
        $encryption = app(ArchiveEncryptionService::class); $encrypted = $encryption->encrypt($plaintext);
        $payload = json_encode(['ciphertext' => base64_encode($encrypted['ciphertext']), 'iv' => $encrypted['iv'], 'tag' => $encrypted['tag'], 'sha256' => hash('sha256', $plaintext)], JSON_THROW_ON_ERROR);
        $path = $this->private.'/before-seed-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.enc.json';
        if (file_put_contents($path, $payload, LOCK_EX) !== strlen($payload)) { throw new RuntimeException('Cannot save development-data backup.'); }
        $saved = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!hash_equals($saved['sha256'], hash('sha256', $encryption->decrypt(base64_decode($saved['ciphertext']), $saved['iv'], $saved['tag'])))) { throw new RuntimeException('Development-data backup verification failed.'); }
        return $path;
    }
    private function pdf(string $title, string $body): string
    {
        $escape = fn ($value) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
        $stream = "BT /F1 16 Tf 36 740 Td (".$escape($title).") Tj /F1 10 Tf";
        $body = preg_replace('/[^\x20-\x7e]/', '-', $body);
        foreach (explode("\n", wordwrap($body, 125)) as $line) { $stream .= ' 0 -22 Td ('.$escape($line).') Tj'; }
        $stream .= ' ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 900 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
        $pdf = "%PDF-1.4\n"; $offsets = [];
        foreach ($objects as $index => $object) { $offsets[] = strlen($pdf); $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) { $pdf .= sprintf("%010d 00000 n \n", $offset); }
        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
