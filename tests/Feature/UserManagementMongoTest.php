<?php

namespace Tests\Feature;

use App\Models\{Audit, College, Department, PasswordResetToken, User};
use App\Notifications\PasswordResetLink;
use Illuminate\Support\Facades\{DB, Hash, Notification};
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class UserManagementMongoTest extends TestCase
{
    private ?string $testDatabase = null;
    private User $super;
    private College $college;
    private $units;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite(); Notification::fake();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') $this->markTestSkipped('Uses a randomly named isolated MongoDB database.');
        $this->testDatabase = 'complitrack_users_test_'.bin2hex(random_bytes(6));
        config(['database.connections.mongodb.database' => $this->testDatabase]); DB::purge('mongodb');
        $this->assertSame($this->testDatabase, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        $this->super = $this->account(0);
        $this->college = College::create(['collegeName' => 'College of Engineering and Technology', 'collegeCode' => 'CET', 'isActive' => true]);
        $this->units = collect(config('qms.departments'))->map(fn ($name, $code) => Department::create(['departmentName' => $name,
            'departmentCode' => $code, 'collegeName' => 'CET', 'collegeId' => $this->college->getKey(), 'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]))->values();
        $this->actingAs($this->super);
    }

    protected function tearDown(): void
    {
        if ($this->testDatabase) {
            $database = DB::connection('mongodb')->getMongoDB();
            if ($database->getDatabaseName() === $this->testDatabase && str_starts_with($this->testDatabase, 'complitrack_users_test_')) $database->drop();
            DB::purge('mongodb');
        }
        parent::tearDown();
    }

    private function account(int $role = 3, array $extra = []): User
    {
        return User::create($extra + ['fullName' => 'Test Account', 'email' => bin2hex(random_bytes(5)).'@example.test',
            'password' => Hash::make('Existing-test-password!'), 'roleId' => $role, 'isActive' => true]);
    }

    public function test_position_indicator_uses_the_assigned_unit_or_college_instead_of_a_different_home_department(): void
    {
        $head = $this->account(3, ['departmentId' => $this->units[0]->getKey(),
            'archiveResponsibility' => 'department_head', 'archiveUnitIds' => [$this->units[1]->getKey()]]);
        $this->actingAs($head)->get('/auditee/profile')->assertOk()
            ->assertSee('Department Head &mdash; '.$this->units[1]->departmentName, false)
            ->assertDontSee('Department Head &mdash; '.$this->units[0]->departmentName, false);

        $dean = $this->account(3, ['departmentId' => $this->units[0]->getKey(),
            'archiveResponsibility' => 'college_dean', 'archiveCollegeIds' => [(string) $this->college->getKey()]]);
        $this->actingAs($dean)->get('/auditee/profile')->assertOk()
            ->assertSee('College Dean &mdash; '.$this->college->collegeName, false);

        $legacyHead = $this->account(3, ['departmentId' => (string) $this->units[0]->getKey(), 'archiveResponsibility' => 'department_head']);
        $this->assertSame($this->units[0]->departmentName, app(\App\Services\PositionScope::class)->label($legacyHead));
        $unassigned = $this->account(3, ['archiveResponsibility' => 'department_head']);
        $this->assertSame('Department / office not assigned', app(\App\Services\PositionScope::class)->label($unassigned));
    }

    public function test_cluster_administrators_can_be_created_and_edited_with_cluster_choices(): void
    {
        $this->getJson('/api/v1/users/options')->assertOk()
            ->assertJsonPath('options.clusters.0.name', 'Cluster A (Claveria Group)')
            ->assertJsonPath('options.clusters.1.name', 'Cluster B (Cagayan de Oro/Main Group)');
        $payload = $this->payload(['departmentId' => null, 'officeName' => '',
            'archiveResponsibility' => 'cluster_administrator', 'archiveUnitIds' => [],
            'archiveCollegeIds' => [], 'archiveCluster' => 'cluster_a']);
        $id = $this->postJson('/api/v1/users', $payload)->assertCreated()
            ->assertJsonPath('data.archiveCluster', 'cluster_a')
            ->assertJsonPath('data.clusterName', 'Cluster A (Claveria Group)')->json('data._id');
        $this->patchJson('/api/v1/users/'.$id, ['archiveCluster' => 'cluster_b'])->assertOk()
            ->assertJsonPath('data.clusterName', 'Cluster B (Cagayan de Oro/Main Group)');
        $this->getJson('/api/v1/users/'.$id)->assertOk()->assertJsonPath('data.archiveCluster', 'cluster_b');
        $this->assertSame('Cluster B (Cagayan de Oro/Main Group)', app(\App\Services\PositionScope::class)->label(User::findOrFail($id)));
        $this->patchJson('/api/v1/users/'.$id, ['archiveCluster' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('archiveCluster');
        $this->patchJson('/api/v1/users/'.$id, ['archiveCluster' => null])->assertUnprocessable()->assertJsonValidationErrors('archiveCluster');
        $this->patchJson('/api/v1/users/'.$id, ['archiveCollegeIds' => [(string) $this->college->getKey()]])->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$id, ['archiveResponsibility' => 'college_dean', 'archiveCluster' => null,
            'archiveCollegeIds' => [(string) $this->college->getKey()]])->assertOk()->assertJsonPath('data.archiveCluster', null);
        $this->patchJson('/api/v1/users/'.$id, ['archiveCluster' => 'cluster_a'])->assertUnprocessable();
        $legacy = $this->account(3, ['archiveResponsibility' => 'cluster_administrator',
            'departmentId' => $this->units[0]->getKey(), 'archiveCollegeIds' => [$this->college->getKey()]]);
        $this->patchJson('/api/v1/users/'.$legacy->getKey(), ['archiveCluster' => 'cluster_a', 'archiveCollegeIds' => []])->assertOk()
            ->assertJsonPath('data.departmentId', (string) $this->units[0]->getKey())
            ->assertJsonPath('data.clusterName', 'Cluster A (Claveria Group)');
        $this->actingAs($this->account(1))->patchJson('/api/v1/users/'.$id, ['archiveCluster' => 'cluster_a'])->assertForbidden();
    }

    public function test_auditor_positions_can_be_created_and_changed_without_rewriting_audit_assignments(): void
    {
        $home = (string) $this->units[0]->getKey();
        $positionUnit = (string) $this->units[1]->getKey();
        $id = $this->postJson('/api/v1/users', $this->payload(['roleId' => 2,
            'departmentId' => $home, 'assignedDepartmentIds' => [$home],
            'archiveResponsibility' => 'department_head', 'archiveUnitIds' => [$positionUnit]]))
            ->assertCreated()->assertJsonPath('data.roleId', 2)
            ->assertJsonPath('data.archiveResponsibility', 'department_head')->json('data._id');
        foreach (config('qms.responsibilities') as $position) {
            $collegePosition = in_array($position, ['college_dean', 'college_secretary'], true);
            $cluster = $position === 'cluster_administrator';
            $payload = ['archiveResponsibility' => $position,
                'archiveUnitIds' => $collegePosition || $cluster ? [] : [$positionUnit],
                'archiveCollegeIds' => $collegePosition ? [(string) $this->college->getKey()] : [],
                'archiveCluster' => $cluster ? 'cluster_b' : null];
            $this->patchJson('/api/v1/users/'.$id, $payload)->assertOk()
                ->assertJsonPath('data.roleId', 2)->assertJsonPath('data.archiveResponsibility', $position)
                ->assertJsonPath('data.departmentId', $home)->assertJsonPath('data.assignedDepartmentIds', [$home]);
            $actor = User::findOrFail($id);
            $this->assertSame([$home], app(\App\Services\QmsArchiveAccess::class)->organizationalUnits($actor)->get()
                ->map(fn ($unit) => (string) $unit->getKey())->all());
        }
        $this->patchJson('/api/v1/users/'.$id, ['archiveResponsibility' => null, 'archiveUnitIds' => [],
            'archiveCollegeIds' => [], 'archiveCluster' => null])->assertOk()->assertJsonPath('data.archiveResponsibility', null);
        $this->actingAs(User::findOrFail($id))->patchJson('/api/v1/users/'.$id, ['archiveResponsibility' => 'faculty_staff'])->assertForbidden();
    }

    public function test_auditee_creation_and_editing_do_not_require_an_office_position_name(): void
    {
        $payload = $this->payload(['archiveResponsibility' => null, 'archiveUnitIds' => []]);
        unset($payload['officeName']);
        $id = $this->postJson('/api/v1/users', $payload)->assertCreated()->assertJsonPath('data.officeName', null)->json('data._id');
        $this->patchJson('/api/v1/users/'.$id, ['fullName' => 'Updated account'])->assertOk()->assertJsonPath('data.fullName', 'Updated account');
    }

    public function test_an_additional_cet_department_can_be_created_and_used_for_positions_and_audit_eligibility(): void
    {
        $payload = ['departmentCode' => 'CET-NEW', 'departmentName' => 'Electrical Engineering', 'collegeName' => 'CET', 'campusName' => 'Claveria'];
        $id = $this->postJson('/api/v1/departments', $payload)->assertCreated()
            ->assertJsonPath('data.unitType', 'academic_department')->assertJsonPath('data.ownershipVerified', true)->json('data._id');
        $this->assertSame(6, Department::where('collegeName', 'CET')->count());
        $option = collect($this->getJson('/api/v1/users/options')->assertOk()->json('options.departments'))->firstWhere('_id', $id);
        $this->assertTrue($option['authorizedCet']);
        $this->assertTrue($option['auditEligible']);
        $head = $this->account(3, ['departmentId' => $this->units[0]->getKey()]);
        $this->patchJson('/api/v1/users/'.$head->getKey(), ['archiveResponsibility' => 'department_head', 'archiveUnitIds' => [$id]])->assertOk();
        $auditor = $this->account(2, ['departmentId' => $this->units[0]->getKey()]);
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [$id]])->assertOk();
        $this->assertTrue(app(\App\Services\AuditAssignment::class)->eligible($auditor->fresh(), Department::findOrFail($id)));
        $this->getJson('/api/v1/system/dashboard')->assertOk()->assertJsonPath('data.stats.cetDepartments', 6);
        $this->postJson('/api/v1/departments', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/departments', array_merge($payload, ['departmentCode' => 'CET-OTHER']))->assertUnprocessable();
        $this->postJson('/api/v1/departments', array_merge($payload, ['departmentCode' => 'DEAN', 'departmentName' => 'Office of the Dean']))->assertUnprocessable();
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['fullName' => 'New Department Head', 'email' => 'new@example.test', 'roleId' => 3,
            'departmentId' => (string) $this->units[0]->getKey(), 'officeName' => 'Department office',
            'archiveResponsibility' => 'department_head', 'archiveUnitIds' => [(string) $this->units[0]->getKey()], 'archiveCollegeIds' => []];
    }

    private function snapshot(User $user): string
    {
        $document = DB::connection('mongodb')->getMongoDB()->users->findOne(['_id' => new ObjectId((string) $user->getKey())]);
        return \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($document));
    }

    public function test_super_admin_can_authorize_independent_auditors_for_verified_cet_offices_without_changing_home_units(): void
    {
        $office = Department::create(['departmentName' => 'Office of the Dean', 'departmentCode' => 'CET-DEAN', 'collegeName' => 'CET',
            'collegeId' => $this->college->getKey(), 'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]);
        $auditor = $this->account(2, ['departmentId' => $this->units[0]->getKey(), 'assignedDepartmentIds' => [$this->units[0]->getKey()]]);
        $options = $this->getJson('/api/v1/users/options')->assertOk()->json('options.departments');
        $option = collect($options)->firstWhere('_id', (string) $office->getKey());
        $this->assertTrue($option['auditEligible']);
        $this->assertFalse($option['authorizedCet'], 'An office must not become a sixth academic department.');
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [(string) $office->getKey()]])->assertOk()
            ->assertJsonPath('data.departmentId', (string) $this->units[0]->getKey());
        $this->assertTrue(app(\App\Services\AuditAssignment::class)->eligible($auditor->fresh(), $office));
        $this->assertInstanceOf(ObjectId::class, $auditor->fresh()->assignedDepartmentIds[0]);
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['departmentId' => (string) $office->getKey()])->assertUnprocessable();
        $outside = Department::create(['departmentName' => 'Outside Office', 'collegeName' => 'OTHER', 'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]);
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [(string) $outside->getKey()]])->assertUnprocessable();
        $office->update(['ownershipVerified' => false]);
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [(string) $office->getKey(), (string) $this->units[1]->getKey()]])->assertUnprocessable();
        $this->assertSame((string) $this->units[0]->getKey(), (string) $auditor->fresh()->departmentId);
        $atl = $this->account(1, ['departmentId' => $this->units[0]->getKey()]);
        $this->actingAs($atl)->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => []])->assertForbidden();
    }

    public function test_super_admin_can_correct_an_auditors_legacy_home_without_rewriting_historical_eligibility(): void
    {
        $legacy = Department::create(['departmentName' => '[DEMO] Engineering', 'unitType' => 'legacy_unclassified',
            'collegeName' => 'Collage of Engineering and Technology', 'isActive' => true, 'ownershipVerified' => false]);
        $auditor = $this->account(2, ['userCode' => 'AUD-LEGACY', 'departmentId' => (string) $legacy->getKey(),
            'assignedDepartmentIds' => [(string) $legacy->getKey()]]);
        $before = $auditor->getRawOriginal();
        // Merely granting eligibility cannot validate an unclassified home department.
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [(string) $this->units[1]->getKey()]])->assertUnprocessable();
        // The actual editor sends only the changed home field, retaining hidden legacy assignments.
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['departmentId' => (string) $this->units[0]->getKey()])->assertOk()
            ->assertJsonPath('data.departmentId', (string) $this->units[0]->getKey());
        foreach (['password', 'userCode', 'assignedDepartmentIds'] as $field) {
            $this->assertEquals($before[$field], $auditor->fresh()->getRawOriginal($field));
        }
        $this->assertInstanceOf(ObjectId::class, $auditor->fresh()->departmentId);
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [
            (string) $legacy->getKey(), (string) $this->units[0]->getKey(), (string) $this->units[1]->getKey(),
        ]])->assertOk();
        $eligibility = app(\App\Services\AuditAssignment::class);
        $this->assertTrue($eligibility->eligible($auditor->fresh(), $this->units[1]));
        $this->assertFalse($eligibility->eligible($auditor->fresh(), $this->units[0]));
        $this->assertFalse($eligibility->units()->contains(fn ($unit) => (string) $unit->getKey() === (string) $legacy->getKey()));
        $anotherLegacy = Department::create(['departmentName' => 'Other legacy', 'unitType' => 'legacy_unclassified', 'isActive' => true]);
        $snapshot = $this->snapshot($auditor->fresh());
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [
            (string) $legacy->getKey(), (string) $anotherLegacy->getKey(),
        ]])->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['departmentId' => (string) $legacy->getKey()])->assertUnprocessable();
        $this->assertSame($snapshot, $this->snapshot($auditor->fresh()));
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => []])->assertOk();
        $this->patchJson('/api/v1/users/'.$auditor->getKey(), ['assignedDepartmentIds' => [(string) $legacy->getKey()]])->assertUnprocessable();
    }

    public function test_creation_uses_existing_user_collection_and_password_setup_without_changing_existing_accounts(): void
    {
        $before = $this->snapshot($this->super);
        $response = $this->postJson('/api/v1/users', $this->payload())->assertCreated()->assertJsonPath('data.roleId', 3)
            ->assertJsonPath('data.archiveResponsibility', 'department_head')->assertJsonMissingPath('data.password');
        $created = User::find($response->json('data._id'));
        $this->assertStringStartsWith('ATE-', $created->userCode);
        $this->assertSame((string) $this->units[0]->getKey(), (string) $created->archiveUnitIds[0]);
        $this->assertNotEmpty($created->password); $this->assertNotEmpty(PasswordResetToken::where('email', $created->email)->first());
        Notification::assertSentOnDemand(PasswordResetLink::class);
        $this->assertSame($before, $this->snapshot($this->super));
        $this->postJson('/api/v1/users', $this->payload())->assertUnprocessable();
    }

    public function test_college_responsibilities_are_college_scoped_and_reject_individual_departments(): void
    {
        foreach (['college_dean', 'college_secretary'] as $index => $responsibility) {
            $data = $this->payload(['email' => $responsibility.'@example.test', 'departmentId' => null,
                'archiveResponsibility' => $responsibility, 'archiveUnitIds' => [], 'archiveCollegeIds' => [(string) $this->college->getKey()]]);
            $this->postJson('/api/v1/users', $data)->assertCreated()->assertJsonPath('data.departmentId', null)->assertJsonPath('data.roleId', 3);
            $data['email'] = 'invalid-'.$index.'@example.test'; $data['departmentId'] = (string) $this->units[0]->getKey();
            $this->postJson('/api/v1/users', $data)->assertUnprocessable();
        }
    }

    public function test_invalid_organization_is_rejected_atomically_from_both_editors(): void
    {
        $person = $this->account(3, ['departmentId' => $this->units[0]->getKey()]); $before = $this->snapshot($person);
        $this->patchJson('/api/v1/users/'.$person->getKey(), ['fullName' => 'Must not save', 'archiveResponsibility' => 'department_head', 'archiveUnitIds' => [], 'archiveCollegeIds' => []])->assertUnprocessable();
        $this->assertSame($before, $this->snapshot($person));
        $legacy = Department::create(['departmentName' => 'Legacy', 'departmentCode' => 'OTHER', 'unitType' => 'academic_department', 'isActive' => true, 'ownershipVerified' => true]);
        foreach ([[], [(string) $legacy->getKey()], [(string) $this->units[0]->getKey(), (string) $this->units[1]->getKey()]] as $ids) {
            $this->patchJson('/api/v1/documents/organization/'.$person->getKey(), ['responsibility' => 'department_secretary', 'unitIds' => $ids, 'collegeIds' => []])->assertForbidden();
        }
        $this->patchJson('/api/v1/users/'.$person->getKey(), ['archiveResponsibility' => 'department_secretary', 'archiveUnitIds' => [(string) $this->units[1]->getKey()], 'archiveCollegeIds' => []])->assertOk();
        $this->assertSame(3, $person->fresh()->roleId);
    }

    public function test_counts_filters_search_and_pagination_use_database_records_without_read_side_effects(): void
    {
        $inactiveUnit = Department::create(['departmentName' => 'Inactive', 'isActive' => false]);
        $active = $this->account(2, ['fullName' => 'Needle Person', 'userCode' => 'AUD-SEARCH', 'departmentId' => $inactiveUnit->getKey()]);
        $this->account(3, ['fullName' => 'Other', 'isActive' => false, 'departmentId' => $this->units[0]->getKey(), 'archiveResponsibility' => 'department_head']);
        $this->getJson('/api/v1/users?management=1&perPage=1')->assertOk()->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.active', 2)->assertJsonPath('summary.auditors', 1)->assertJsonPath('summary.auditees', 1)
            ->assertJsonPath('meta.lastPage', 3)->assertJsonCount(1, 'data');
        foreach (['Needle', 'AUD-SEARCH', $active->email] as $search) $this->getJson('/api/v1/users?search='.urlencode($search))->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/users?roleId=0')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/users?status=inactive&responsibility=department_head&departmentId='.$this->units[0]->getKey())->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/users?roleId=2&status=inactive')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/users?roleId=99')->assertUnprocessable();
        $this->assertTrue($active->fresh()->isActive, 'Listing users must not deactivate them.');
    }

    public function test_related_query_count_stays_constant_and_mixed_bson_assignments_keep_their_meaning(): void
    {
        $people = collect();
        for ($index = 0; $index < 24; $index++) {
            $people->push($this->account($index < 2 ? 1 : 3, [
                'fullName' => sprintf('Person %02d', $index),
                'departmentId' => $index % 2 ? (string) $this->units[0]->getKey() : $this->units[0]->getKey(),
                'archiveUnitIds' => [$this->units[1]->getKey(), (string) $this->units[1]->getKey(), 'invalid-id'],
                'archiveCollegeIds' => [(string) $this->college->getKey()],
            ]));
        }
        foreach ([0, 1] as $index) {
            DB::connection('mongodb')->getMongoDB()->audits->insertOne([
                'reviewerId' => $index ? new ObjectId((string) $people[$index]->getKey()) : (string) $people[$index]->getKey(),
            ]);
        }
        $monitor = new \Tests\Support\MongoCommandCounter();
        \MongoDB\Driver\Monitoring\addSubscriber($monitor);
        try {
            $counts = [];
            foreach ([10, 20] as $size) {
                $monitor->counts = [];
                $response = $this->getJson('/api/v1/users?management=1&includeOptions=0&perPage='.$size)->assertOk()
                    ->assertJsonCount($size, 'data')->assertJsonPath('summary.total', 25)->assertJsonMissingPath('options')
                    ->assertJsonPath('data.0.isAuditTeamLeader', true)->assertJsonPath('data.0.canDeactivate', false)
                    ->assertJsonPath('data.1.isAuditTeamLeader', true)->assertJsonPath('data.2.canDeactivate', true)
                    ->assertJsonPath('data.0.departmentName', $this->units[0]->departmentName)
                    ->assertJsonPath('data.0.organizationalUnits', [$this->units[1]->departmentName])
                    ->assertJsonPath('data.0.organizationalColleges', [$this->college->collegeName]);
                $this->assertSame((string) $this->units[0]->getKey(), $response->json('data.0.departmentId'));
                $counts[] = $monitor->counts;
            }
            $this->assertSame($counts[0], $counts[1], 'Page size must not multiply database commands.');
            $this->assertSame(1, $counts[0]['find:departments'] ?? 0);
            $this->assertSame(1, $counts[0]['find:colleges'] ?? 0);
            $this->assertSame(1, $counts[0]['find:audits'] ?? 0);
            $this->assertSame(1, $counts[0]['aggregate:users'] ?? 0);
        } finally {
            \MongoDB\Driver\Monitoring\removeSubscriber($monitor);
        }
    }

    public function test_empty_relation_lists_do_not_query_related_collections(): void
    {
        $monitor = new \Tests\Support\MongoCommandCounter();
        \MongoDB\Driver\Monitoring\addSubscriber($monitor);
        try {
            $this->getJson('/api/v1/users?management=1&includeOptions=0')->assertOk()
                ->assertJsonPath('data.0.organizationalUnits', [])->assertJsonPath('data.0.organizationalColleges', [])
                ->assertJsonPath('data.0.canDeactivate', false);
            foreach (['departments', 'colleges', 'audits'] as $collection) {
                $this->assertSame(0, $monitor->counts['find:'.$collection] ?? 0);
            }
        } finally {
            \MongoDB\Driver\Monitoring\removeSubscriber($monitor);
        }
    }

    public function test_filter_options_can_be_omitted_and_are_fresh_and_authorized_when_requested(): void
    {
        $this->getJson('/api/v1/users?management=1')->assertOk()->assertJsonStructure(['options' => ['departments', 'colleges', 'responsibilities']]);
        $this->units[0]->update(['departmentName' => 'Updated department', 'isActive' => false]);
        $this->college->update(['isActive' => false]);
        $options = $this->getJson('/api/v1/users/options')->assertOk()->json('options');
        $changed = collect($options['departments'])->firstWhere('_id', (string) $this->units[0]->getKey());
        $this->assertSame('Updated department', $changed['name']);
        $this->assertFalse($changed['active']);
        $this->assertSame([], $options['colleges']);
        $this->getJson('/api/v1/users?management=1&includeOptions=0&page=2&perPage=1')->assertOk()->assertJsonMissingPath('options');
        foreach ([1, 2, 3] as $role) {
            $this->actingAs($this->account($role))->getJson('/api/v1/users/options')->assertForbidden();
        }
    }

    public function test_edit_preserves_credentials_codes_and_legacy_relationships(): void
    {
        $legacy = Department::create(['departmentName' => 'Unclassified', 'unitType' => 'legacy_unclassified', 'isActive' => true]);
        $user = $this->account(3, ['userCode' => 'ATE-OLD', 'departmentId' => (string) $legacy->getKey(), 'archiveUnitIds' => [(string) $legacy->getKey()]]);
        $before = $user->getRawOriginal();
        $this->patchJson('/api/v1/users/'.$user->getKey(), ['fullName' => 'Updated Name', 'departmentId' => (string) $legacy->getKey(), 'archiveUnitIds' => [(string) $legacy->getKey()]])->assertOk();
        foreach (['password', 'userCode', 'departmentId', 'archiveUnitIds'] as $field) $this->assertEquals($before[$field], $user->fresh()->getRawOriginal($field));
    }

    public function test_self_super_admin_and_atl_protections_apply_to_all_write_paths(): void
    {
        $this->deleteJson('/api/v1/users/'.$this->super->getKey())->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$this->super->getKey(), ['isActive' => false])->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$this->super->getKey(), ['roleId' => 3])->assertUnprocessable();
        $other = $this->account(0); $this->deleteJson('/api/v1/users/'.$other->getKey())->assertUnprocessable();
        $atl = $this->account(1, ['departmentId' => $this->units[0]->getKey()]);
        Audit::create(['reviewerId' => $atl->getKey(), 'departmentId' => $this->units[0]->getKey(), 'status' => 'draft']);
        $this->deleteJson('/api/v1/users/'.$atl->getKey())->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$atl->getKey(), ['roleId' => 2])->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$atl->getKey(), ['departmentId' => (string) $this->units[1]->getKey()])->assertUnprocessable();
        $this->assertSame(1, $atl->fresh()->roleId);
        $this->actingAs($atl)->patchJson('/api/v1/users/'.$atl->getKey(), ['isActive' => false])->assertForbidden();
    }

    public function test_deactivation_reactivation_and_inactive_department_cannot_be_bypassed_by_edit(): void
    {
        $user = $this->account(3, ['departmentId' => $this->units[0]->getKey()]);
        $this->deleteJson('/api/v1/users/'.$user->getKey())->assertOk(); $this->assertFalse($user->fresh()->isActive);
        $this->postJson('/api/v1/users/'.$user->getKey().'/reactivate')->assertOk(); $this->assertTrue($user->fresh()->isActive);
        $this->units[0]->update(['isActive' => false]); $user->update(['isActive' => false]);
        $this->postJson('/api/v1/users/'.$user->getKey().'/reactivate')->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$user->getKey(), ['isActive' => true])->assertUnprocessable();
    }

    public function test_authorization_blocks_auditors_auditees_and_admin_scope_escalation(): void
    {
        $target = $this->account(3, ['departmentId' => $this->units[0]->getKey()]);
        foreach ([2, 3] as $role) {
            $this->actingAs($this->account($role))->getJson('/api/v1/users')->assertForbidden();
            $this->postJson('/api/v1/users', $this->payload())->assertForbidden();
            $this->patchJson('/api/v1/users/'.$target->getKey(), ['fullName' => 'Denied'])->assertForbidden();
            $this->postJson('/api/v1/users/'.$target->getKey().'/reset-password')->assertForbidden();
        }
        $this->actingAs($this->account(1))->getJson('/api/v1/users?management=1')->assertForbidden();
        $this->patchJson('/api/v1/users/'.$target->getKey(), ['roleId' => 0])->assertForbidden();
        $this->patchJson('/api/v1/users/'.$target->getKey(), ['archiveResponsibility' => 'college_dean', 'archiveCollegeIds' => [(string) $this->college->getKey()]])->assertForbidden();
    }

    public function test_detail_scope_and_audit_list_never_grant_unassigned_audits_by_department_membership(): void
    {
        $user = $this->account(2, ['departmentId' => $this->units[0]->getKey(), 'assignedDepartmentIds' => [$this->units[0]->getKey()]]);
        $user->forceFill(['lastLoginAt' => now()->utc()])->save();
        Audit::create(['auditCode' => 'ASSIGNED-ONLY', 'auditorId' => $user->getKey(), 'departmentId' => $this->units[0]->getKey(), 'status' => 'draft']);
        Audit::create(['auditCode' => 'UNASSIGNED-SECRET', 'auditorId' => new ObjectId(), 'departmentId' => $this->units[0]->getKey(), 'status' => 'draft']);
        $response = $this->getJson('/api/v1/users/'.$user->getKey())->assertOk()->assertJsonCount(1, 'data.scope.assignedAudits')
            ->assertJsonPath('data.scope.assignedAudits.0.reference', 'ASSIGNED-ONLY')->assertJsonMissingPath('data.password');
        $this->assertIsString($response->json('data.lastLoginAt'));
        $this->actingAs($user)->get('/audits')->assertOk()->assertSee('ASSIGNED-ONLY')->assertDontSee('UNASSIGNED-SECRET');
        $this->actingAs($this->super)->patchJson('/api/v1/users/'.$user->getKey(), ['archiveResponsibility' => 'college_dean', 'archiveCollegeIds' => [(string) $this->college->getKey()]])->assertOk();
        $this->actingAs($user->fresh())->get('/audits')->assertOk()->assertSee('ASSIGNED-ONLY')->assertDontSee('UNASSIGNED-SECRET');
    }

    public function test_reset_link_uses_existing_flow_and_does_not_change_password_until_used(): void
    {
        $user = $this->account(); $hash = $user->password;
        $this->postJson('/api/v1/users/'.$user->getKey().'/reset-password')->assertOk();
        $this->assertSame($hash, $user->fresh()->password); $this->assertNotNull(PasswordResetToken::where('email', $user->email)->first());
        Notification::assertSentOnDemand(PasswordResetLink::class);
        $this->getJson('/api/v1/users/'.$user->getKey())->assertOk()->assertJsonFragment(['action' => 'Sent password reset link']);
    }

    public function test_password_changes_do_not_create_return_or_log_reset_credentials(): void
    {
        Hash::driver('bcrypt')->setRounds(12);
        \Illuminate\Support\Facades\Log::spy();
        $count = PasswordResetToken::count();
        $this->patchJson('/api/v1/auth/password', ['currentPassword' => 'Existing-test-password!',
            'password' => 'Replacement-password!123', 'password_confirmation' => 'Replacement-password!123'])
            ->assertOk()->assertJsonMissingPath('test_reset_link')->assertJsonMissingPath('stored_in');
        $this->assertSame($count, PasswordResetToken::count());
        $this->assertTrue(Hash::check('Replacement-password!123', $this->super->fresh()->password));
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('info');
    }

    public function test_reset_link_can_be_used_once_and_expired_links_cannot_change_credentials(): void
    {
        $user = $this->account(); $query = [];
        $this->postJson('/api/v1/users/'.$user->getKey().'/reset-password')->assertOk();
        Notification::assertSentOnDemand(PasswordResetLink::class, function ($notification, $channels, $notifiable) use (&$query) {
            parse_str(parse_url($notification->toMail($notifiable)->actionUrl, PHP_URL_QUERY), $query);
            return true;
        });
        $this->assertSame(hash('sha256', $query['token']), PasswordResetToken::where('email', $user->email)->first()->token);
        Hash::driver('bcrypt')->setRounds(12); // Existing reset controller uses the production bcrypt cost.
        $payload = $query + ['password' => 'Replacement-password!123', 'password_confirmation' => 'Replacement-password!123'];
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $hash = $user->fresh()->password;
        PasswordResetToken::create(['email' => $user->email, 'token' => hash('sha256', $query['token']), 'createdAt' => now()->subMinutes(11)]);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_ngrok_reset_link_and_original_device_completion_tracking(): void
    {
        config(['app.url' => 'https://undercook-ecosystem-detract.ngrok-free.dev']);
        $user = $this->account();
        $request = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $watch = $request->json('watch');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $watch);
        $query = [];
        Notification::assertSentOnDemand(PasswordResetLink::class, function ($notification, $channels, $notifiable) use (&$query) {
            $url = $notification->toMail($notifiable)->actionUrl;
            $this->assertStringStartsWith('https://undercook-ecosystem-detract.ngrok-free.dev/reset-password?', $url);
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            return true;
        });
        $this->assertNotSame($watch, $query['token']);
        $this->postJson('/api/v1/auth/password-reset-status', ['watch' => $watch])->assertOk()->assertJson(['status' => 'pending'])->assertJsonMissingPath('token')->assertJsonMissingPath('email');
        Hash::driver('bcrypt')->setRounds(12);
        $this->postJson('/api/v1/auth/reset-password', $query + ['password' => 'Replacement-password!123', 'password_confirmation' => 'Replacement-password!123'])->assertOk();
        $this->postJson('/api/v1/auth/password-reset-status', ['watch' => $watch])->assertOk()->assertJson(['status' => 'completed']);
        $this->assertTrue(Hash::check('Replacement-password!123', $user->fresh()->password));
    }

    public function test_email_link_reset_on_another_device_completes_the_inbox_watch(): void
    {
        $user = $this->account();
        $watch = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->json('watch');
        $query = [];
        Notification::assertSentOnDemand(PasswordResetLink::class, function ($notification, $channels, $notifiable) use (&$query) {
            parse_str(parse_url($notification->toMail($notifiable)->actionUrl, PHP_URL_QUERY), $query);
            return true;
        });
        Hash::driver('bcrypt')->setRounds(12);
        $this->post('/reset-password', $query + ['password' => 'Replacement-password!123', 'password_confirmation' => 'Replacement-password!123'])->assertRedirect('/login');
        $this->postJson('/api/v1/auth/password-reset-status', ['watch' => $watch])->assertOk()->assertJsonPath('status', 'completed');
    }

    public function test_reset_watch_does_not_disclose_accounts_or_authorize_password_changes(): void
    {
        $watch = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])->assertOk()->json('watch');
        $this->postJson('/api/v1/auth/password-reset-status', ['watch' => $watch])->assertOk()->assertExactJson(['status' => 'pending']);
        $this->postJson('/api/v1/auth/password-reset-status', ['watch' => str_repeat('0', 64)])->assertOk()->assertExactJson(['status' => 'expired']);
        $this->postJson('/api/v1/auth/password-reset-status', ['watch' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', ['email' => $this->super->email, 'token' => $watch, 'password' => 'Replacement-password!123', 'password_confirmation' => 'Replacement-password!123'])->assertUnprocessable();
        $this->travel(11)->minutes();
        $this->postJson('/api/v1/auth/password-reset-status', ['watch' => $watch])->assertOk()->assertJsonPath('status', 'expired');
        $this->travelBack();
    }

    public function test_staff_may_belong_to_the_verified_college_office_without_becoming_a_college_officer(): void
    {
        $office = Department::create(['departmentCode' => 'CET-DEAN', 'departmentName' => 'Office of the Dean', 'collegeName' => 'CET',
            'collegeId' => $this->college->getKey(), 'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]);
        $this->postJson('/api/v1/users', $this->payload(['archiveResponsibility' => 'faculty_staff', 'archiveUnitIds' => [],
            'departmentId' => (string) $office->getKey()]))->assertCreated()->assertJsonPath('data.roleId', 3)->assertJsonPath('data.archiveResponsibility', 'faculty_staff');
    }
}
