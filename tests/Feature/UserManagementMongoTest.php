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
            $this->patchJson('/api/v1/archives/organization/'.$person->getKey(), ['responsibility' => 'department_secretary', 'unitIds' => $ids, 'collegeIds' => []])->assertForbidden();
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
        $this->actingAs($atl)->patchJson('/api/v1/users/'.$atl->getKey(), ['isActive' => false])->assertNotFound();
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
        $this->actingAs($this->super)->patchJson('/api/v1/users/'.$user->getKey(), ['archiveResponsibility' => 'college_dean', 'archiveCollegeIds' => [(string) $this->college->getKey()]])->assertUnprocessable();
    }

    public function test_reset_link_uses_existing_flow_and_does_not_change_password_until_used(): void
    {
        $user = $this->account(); $hash = $user->password;
        $this->postJson('/api/v1/users/'.$user->getKey().'/reset-password')->assertOk();
        $this->assertSame($hash, $user->fresh()->password); $this->assertNotNull(PasswordResetToken::where('email', $user->email)->first());
        Notification::assertSentOnDemand(PasswordResetLink::class);
        $this->getJson('/api/v1/users/'.$user->getKey())->assertOk()->assertJsonFragment(['action' => 'Sent password reset link']);
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

    public function test_staff_may_belong_to_the_verified_college_office_without_becoming_a_college_officer(): void
    {
        $office = Department::create(['departmentCode' => 'CET-DEAN', 'departmentName' => 'Office of the Dean', 'collegeName' => 'CET',
            'collegeId' => $this->college->getKey(), 'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]);
        $this->postJson('/api/v1/users', $this->payload(['archiveResponsibility' => 'faculty_staff', 'archiveUnitIds' => [],
            'departmentId' => (string) $office->getKey()]))->assertCreated()->assertJsonPath('data.roleId', 3)->assertJsonPath('data.archiveResponsibility', 'faculty_staff');
    }
}
