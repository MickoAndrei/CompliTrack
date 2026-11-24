<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DirectorySummaryMongoTest extends TestCase
{
    private ?string $databaseName = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') {
            $this->markTestSkipped('Uses an isolated MongoDB database.');
        }
        $this->databaseName = 'complitrack_directory_test_'.bin2hex(random_bytes(5));
        config(['database.connections.mongodb.database' => $this->databaseName]);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
    }

    protected function tearDown(): void
    {
        if ($this->databaseName) {
            $database = DB::connection('mongodb')->getMongoDB();
            if ($database->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'complitrack_directory_test_')) {
                $database->drop();
            }
            DB::purge('mongodb');
        }
        parent::tearDown();
    }

    private function account(int $role, string $name, ?Department $department = null, bool $active = true): User
    {
        return User::create(['fullName' => $name, 'roleId' => $role, 'departmentId' => $department?->getKey(), 'isActive' => $active]);
    }

    public function test_user_cards_count_the_entire_filtered_authorized_scope(): void
    {
        $cet = Department::create(['departmentName' => 'CET department', 'collegeName' => 'CET']);
        $other = Department::create(['departmentName' => 'Other college', 'collegeName' => 'OTHER']);
        $admin = $this->account(1, 'Admin');
        $super = $this->account(0, 'Super');
        $this->account(2, 'Alice Auditor', $cet);
        $this->account(3, 'Bob Auditee', $cet, false);
        $this->account(3, 'Other college account', $other);
        $this->actingAs($admin)->getJson('/api/v1/users?includeSummary=1&perPage=1')->assertOk()
            ->assertJsonPath('summary.total', 2)->assertJsonPath('summary.active', 1)->assertJsonPath('summary.inactive', 1)
            ->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/users?includeSummary=1&search=Bob')->assertOk()
            ->assertJsonPath('summary.total', 1)->assertJsonPath('summary.active', 0)->assertJsonPath('summary.inactive', 1);
        $this->getJson('/api/v1/users')->assertJsonMissingPath('summary');
        $this->getJson('/api/v1/users?management=1&includeSummary=1')->assertForbidden();
        $this->actingAs($super)->getJson('/api/v1/users?management=1&includeSummary=1')->assertOk()
            ->assertJsonPath('summary.total', 5)->assertJsonPath('summary.auditors', 1)->assertJsonPath('summary.auditees', 2);
    }

    public function test_admin_user_sorting_applies_before_pagination_and_preserves_scope(): void
    {
        $cet = Department::create(['departmentName' => 'CET department', 'collegeName' => 'CET']);
        $other = Department::create(['departmentName' => 'Other department', 'collegeName' => 'OTHER']);
        $admin = $this->account(1, 'Admin');
        $alice = $this->account(2, 'Alice Auditor', $cet);
        $alice->createdAt = now()->subDays(2);
        $alice->save();
        $bob = $this->account(3, 'Bob Auditee', $cet);
        $bob->createdAt = now()->subDay();
        $bob->save();
        $this->account(3, 'Zoe Outside Scope', $other);

        $this->actingAs($admin)->getJson('/api/v1/users?sortBy=fullName&sortDirection=desc&perPage=1')
            ->assertOk()->assertJsonPath('data.0.fullName', 'Bob Auditee')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/users?sortBy=fullName&sortDirection=desc&perPage=1&page=2')
            ->assertOk()->assertJsonPath('data.0.fullName', 'Alice Auditor');
        $this->getJson('/api/v1/users?sortBy=fullName&sortDirection=asc')
            ->assertOk()->assertJsonPath('data.0.fullName', 'Alice Auditor');
        $this->getJson('/api/v1/users?sortBy=createdAt&sortDirection=desc')
            ->assertOk()->assertJsonPath('data.0.fullName', 'Bob Auditee');
        $this->getJson('/api/v1/users?sortBy=createdAt&sortDirection=asc')
            ->assertOk()->assertJsonPath('data.0.fullName', 'Alice Auditor');
        $this->getJson('/api/v1/users?sortBy=createdAt&sortDirection=desc&search=Alice&includeSummary=1')
            ->assertOk()->assertJsonPath('data.0.fullName', 'Alice Auditor')->assertJsonPath('summary.total', 1);
        $this->getJson('/api/v1/users?sortBy=password&sortDirection=desc')->assertUnprocessable();
        $this->getJson('/api/v1/users?sortBy=fullName&sortDirection=invalid')->assertUnprocessable();
    }

    public function test_department_cards_obey_college_scope_and_exclude_legacy_units(): void
    {
        Department::create(['departmentName' => 'Active CET', 'collegeName' => 'CET', 'isActive' => true]);
        Department::create(['departmentName' => 'Inactive CET', 'collegeName' => 'CET', 'isActive' => false]);
        Department::create(['departmentName' => 'Other department', 'collegeName' => 'OTHER', 'isActive' => true]);
        Department::create(['departmentName' => 'Legacy', 'collegeName' => 'CET', 'unitType' => 'legacy_unclassified', 'isActive' => true]);
        $admin = $this->account(1, 'Admin');
        $this->actingAs($admin)->getJson('/api/v1/departments?includeSummary=1&perPage=1')->assertOk()
            ->assertJsonPath('summary.total', 2)->assertJsonPath('summary.active', 1)->assertJsonPath('summary.inactive', 1)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/departments?includeSummary=1&search=Inactive')->assertOk()->assertJsonPath('summary.total', 1)->assertJsonPath('summary.inactive', 1);
        $this->getJson('/api/v1/departments')->assertJsonMissingPath('summary');
        $this->actingAs($this->account(0, 'Super'))->getJson('/api/v1/departments?includeSummary=1')->assertOk()
            ->assertJsonPath('summary.total', 3)->assertJsonPath('summary.active', 2);
    }
}
