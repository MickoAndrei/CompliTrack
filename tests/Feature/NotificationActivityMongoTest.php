<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use MongoDB\BSON\ObjectId;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class NotificationActivityMongoTest extends TestCase
{
    private ?string $databaseName = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') {
            $this->markTestSkipped('Uses an isolated MongoDB database.');
        }
        $this->databaseName = 'complitrack_attention_test_'.bin2hex(random_bytes(5));
        config(['database.connections.mongodb.database' => $this->databaseName]);
        DB::purge('mongodb');
        $database = DB::connection('mongodb')->getMongoDB();
        $this->assertSame($this->databaseName, $database->getDatabaseName());
        $database->selectCollection('notifications')->createIndex(['dedupeKey' => 1], ['unique' => true, 'sparse' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->databaseName) {
            $database = DB::connection('mongodb')->getMongoDB();
            if ($database->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'complitrack_attention_test_')) {
                $database->drop();
            }
            DB::purge('mongodb');
        }
        parent::tearDown();
    }

    private function account(int $role = 3, string $name = 'Inbox user'): User
    {
        return User::create(['fullName' => $name, 'email' => bin2hex(random_bytes(5)).'@example.test',
            'roleId' => $role, 'isActive' => true, 'password' => Hash::make('Attention-test-password-123!')]);
    }

    public function test_broadcast_delivers_to_active_department_auditors_and_auditees_only(): void
    {
        $department = Department::create(['departmentName' => 'Information Technology', 'collegeName' => 'CET', 'isActive' => true, 'unitType' => 'academic_department']);
        $other = Department::create(['departmentName' => 'Other department', 'collegeName' => 'CET', 'isActive' => true, 'unitType' => 'academic_department']);
        $recipients = [];
        foreach ([2, 3] as $role) {
            foreach ([new ObjectId((string) $department->getKey()), (string) $department->getKey()] as $reference) {
                $user = $this->account($role);
                $user->forceFill(['departmentId' => $reference])->save();
                $recipients[] = (string) $user->getKey();
            }
            $inactive = $this->account($role);
            $inactive->forceFill(['departmentId' => $department->getKey(), 'isActive' => false])->save();
            $foreign = $this->account($role);
            $foreign->forceFill(['departmentId' => $other->getKey(), 'assignedDepartmentIds' => [$department->getKey()]])->save();
        }
        foreach ([0, 1] as $role) {
            $sender = $this->account($role);
            $sender->forceFill(['departmentId' => $department->getKey()])->save();
            $this->actingAs($sender)->postJson('/api/v1/notifications/broadcast', [
                'departmentId' => (string) $department->getKey(), 'message' => '  Department announcement  ',
            ])->assertCreated()->assertJsonPath('recipientCount', 4)->assertJsonPath('departmentName', 'Information Technology');
            $notices = Notification::where('senderUserId', new ObjectId((string) $sender->getKey()))->get();
            $this->assertEqualsCanonicalizing($recipients, $notices->map(fn ($notice) => (string) $notice->recipientUserId)->all());
            $this->assertSame(['Department announcement'], $notices->pluck('message')->unique()->values()->all());
        }
        $auditor = User::findOrFail($recipients[0]);
        $this->actingAs($auditor)->getJson('/api/v1/notifications?type=admin_broadcast')->assertOk()->assertJsonPath('meta.total', 2);
        $this->postJson('/api/v1/notifications/broadcast', ['departmentId' => (string) $department->getKey(), 'message' => 'Not allowed'])->assertForbidden();
    }

    public function test_existing_qms_notification_links_open_documents_without_restoring_archive_routes(): void
    {
        $user = $this->account();
        $notice = $this->notification($user, ['destination' => '/auditee/archives?unit=example']);
        $this->actingAs($user)->getJson('/api/v1/notifications/'.$notice->getKey())->assertOk()
            ->assertJsonPath('data.destination', '/api/v1/notifications/'.$notice->getKey().'/open');
        $this->get('/api/v1/notifications/'.$notice->getKey().'/open')->assertRedirect('/auditee/documents?unit=example');
        $this->get('/auditee/archives')->assertNotFound();
    }

    private function notification(User $user, array $attributes = []): Notification
    {
        return Notification::create(array_merge(['recipientUserId' => $user->getKey(), 'type' => 'evidence_submitted',
            'title' => 'Evidence ready for review', 'message' => 'A response is ready for your review.', 'isRead' => false], $attributes));
    }

    public function test_inbox_filters_search_pages_and_details_are_recipient_only(): void
    {
        $alice = $this->account();
        $bob = $this->account();
        $unread = $this->notification($alice);
        $read = $this->notification($alice, ['isRead' => true, 'title' => 'Audit accepted']);
        $foreign = $this->notification($bob, ['title' => 'Private update']);
        $this->actingAs($alice)->getJson('/api/v1/notifications?includeSummary=1&perPage=1')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.lastPage', 2)->assertJsonPath('meta.unreadCount', 1)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications?status=unread')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0._id', (string) $unread->getKey());
        $this->getJson('/api/v1/notifications?status=read')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0._id', (string) $read->getKey());
        $this->getJson('/api/v1/notifications?search=accepted')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/notifications?search=.*')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/notifications/'.$unread->getKey())->assertOk()->assertJsonPath('data.category', 'Evidence');
        $this->getJson('/api/v1/notifications/'.$foreign->getKey())->assertNotFound();
        $this->getJson('/api/v1/notifications/'.$foreign->getKey().'/open')->assertNotFound();
        $this->patchJson('/api/v1/notifications/'.$foreign->getKey().'/read')->assertNotFound();
        $this->getJson('/api/v1/notifications?status=invalid')->assertUnprocessable();
    }

    public function test_read_actions_preserve_other_inboxes_and_existing_audit_history(): void
    {
        $alice = $this->account();
        $bob = $this->account();
        $notice = $this->notification($alice);
        $other = $this->notification($bob);
        $log = AuditLog::create(['action' => 'Uploaded archive evidence', 'entityType' => 'archive_document']);
        $before = $log->toArray();
        $this->actingAs($alice)->patchJson('/api/v1/notifications/'.$notice->getKey().'/read')->assertOk();
        $this->patchJson('/api/v1/notifications/'.$notice->getKey().'/read')->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'Read notification')->count());
        $this->notification($alice);
        $this->patchJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertSame(0, Notification::where('recipientUserId', new ObjectId((string) $alice->getKey()))->where('isRead', false)->count());
        $this->assertFalse($other->fresh()->isRead);
        $this->assertEquals($before, $log->fresh()->toArray());
        $this->assertSame(3, Notification::count());
        $this->patchJson('/api/v1/logs/'.$log->getKey(), ['action' => 'Tampered'])->assertStatus(405);
        $this->deleteJson('/api/v1/logs/'.$log->getKey())->assertStatus(405);
        $this->assertEquals($before, $log->fresh()->toArray());
    }

    public function test_open_related_record_rechecks_assignment_and_rejects_external_targets(): void
    {
        $auditor = $this->account(2);
        $department = Department::create(['departmentName' => 'Inbox department', 'collegeName' => 'CET', 'isActive' => true]);
        $auditor->update(['assignedDepartmentIds' => [$department->getKey()]]);
        $audit = Audit::create(['auditorId' => $auditor->getKey(), 'departmentId' => $department->getKey(), 'status' => 'draft']);
        $notice = $this->notification($auditor, ['dedupeKey' => 'audit:'.$audit->getKey().':assigned']);
        $this->actingAs($auditor)->get('/api/v1/notifications/'.$notice->getKey().'/open')->assertRedirect('/audits/'.$audit->getKey());
        $auditor->update(['assignedDepartmentIds' => []]);
        $this->getJson('/api/v1/notifications/'.$notice->getKey().'/open')->assertNotFound();
        foreach (['https://example.org/', '//example.org/', '/\\example.org/', '/super-admin/settings', '/audits/../super-admin/settings'] as $destination) {
            $unsafe = $this->notification($auditor, ['destination' => $destination]);
            $this->getJson('/api/v1/notifications/'.$unsafe->getKey())->assertOk()->assertJsonPath('data.destination', null);
            $this->getJson('/api/v1/notifications/'.$unsafe->getKey().'/open')->assertNotFound();
        }
    }

    public function test_log_filters_support_legacy_records_and_authorized_real_counts(): void
    {
        $super = $this->account(0, 'Log Reviewer');
        foreach ([['Uploaded archive evidence', 'archive_document'], ['Updated QMS document permissions', 'archive_document'], ['Assigned audit', 'audit'], ['Reset password via web', 'user']] as [$action, $type]) {
            AuditLog::create(['action' => $action, 'entityType' => $type, 'userId' => $super->getKey(), 'userName' => 'Log Reviewer']);
        }
        $request = Request::create('/api/v1/logs');
        $request->setUserResolver(fn () => $super);
        app(AuditLogService::class)->record($request, 'Access denied', 'security', outcome: 'denied');
        $this->actingAs($super)->getJson('/api/v1/logs?includeSummary=1&perPage=2')->assertOk()
            ->assertJsonPath('meta.total', 5)->assertJsonPath('meta.lastPage', 3)->assertJsonPath('summary.activities', 5)
            ->assertJsonPath('summary.security', 1)->assertJsonPath('summary.denied', 1);
        foreach (['documents', 'permissions', 'audits', 'authentication', 'security'] as $category) {
            $this->getJson('/api/v1/logs?category='.$category)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.category', $category);
        }
        $this->getJson('/api/v1/logs?outcome=success')->assertJsonPath('meta.total', 4);
        $this->getJson('/api/v1/logs?outcome=denied')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/logs?actor=Reviewer&userId='.$super->getKey())->assertJsonPath('meta.total', 5);
        $this->getJson('/api/v1/logs?actor=.*')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/logs?category=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/logs?outcome=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/logs?startDate=2026-11-10&endDate=2026-11-01')->assertUnprocessable();
    }

    public function test_log_details_are_read_only_role_scoped_and_exclude_sensitive_fields(): void
    {
        $super = $this->account(0);
        $log = new AuditLog(['action' => 'Viewed QMS document metadata', 'entityType' => 'archive_document']);
        $log->forceFill(['token' => 'secret-token', 'password' => 'secret-password', 'payload' => ['documentText' => 'protected-qms-content'], 'userAgent' => 'secret-header'])->save();
        $response = $this->actingAs($super)->getJson('/api/v1/logs/'.$log->getKey())->assertOk();
        foreach (['secret-token', 'secret-password', 'protected-qms-content', 'secret-header'] as $secret) {
            $response->assertDontSee($secret);
            $this->getJson('/api/v1/logs')->assertDontSee($secret);
        }
        foreach ([1, 2, 3] as $role) {
            $this->actingAs($this->account($role))->getJson('/api/v1/logs')->assertForbidden();
            $this->getJson('/api/v1/logs/'.$log->getKey())->assertForbidden();
            $this->get('/super-admin/logs')->assertForbidden();
        }
        $this->assertSame('Viewed QMS document metadata', $log->fresh()->action);
        $this->assertGreaterThanOrEqual(9, AuditLog::where('outcome', 'denied')->count());
    }

    public function test_authentication_events_record_results_without_credentials(): void
    {
        $user = $this->account();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Attention-test-password-123!'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'Signed in')->where('category', 'authentication')->count());
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'incorrect-secret'])->assertUnauthorized();
        $this->assertSame(1, AuditLog::where('outcome', 'failed')->count());
        $encoded = AuditLog::all()->toJson();
        foreach (['Attention-test-password-123!', 'incorrect-secret', $user->email] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    public function test_additive_index_installer_is_idempotent_and_preserves_records(): void
    {
        $notice = $this->notification($this->account());
        $before = $notice->toArray();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $process = new Process([PHP_BINARY, 'scripts/apply-attention-indexes.php'], base_path(), [
                'MONGODB_DATABASE' => $this->databaseName,
            ]);
            $process->setTimeout(30);
            $process->mustRun();
            $this->assertStringContainsString('audit_logs_category_outcome_created', $process->getOutput());
        }
        $indexes = iterator_to_array(DB::connection('mongodb')->getMongoDB()->selectCollection('notifications')->listIndexes());
        $this->assertContains('notifications_recipient_created_id', array_map(fn ($index) => $index->getName(), $indexes));
        $this->assertEquals($before, $notice->fresh()->toArray());
        $this->assertSame(1, Notification::count());
    }
}
