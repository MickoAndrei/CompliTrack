<?php

namespace Tests\Feature;

use App\Models\User;
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class QmsDocumentRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(\App\Services\AuditLogService::class)->shouldReceive('record')->andReturnNull();
    }

    public function test_documents_pages_keep_role_access_and_leave_archives_routes_unused(): void
    {
        foreach (['super-admin', 'admin', 'auditor', 'auditee'] as $role => $portal) {
            $user = new User(['fullName' => 'Document route test', 'roleId' => $role, 'isActive' => true]);
            $user->setAttribute('_id', new ObjectId());
            $this->actingAs($user)->get('/'.$portal.'/documents')->assertOk()->assertSee('QMS Documents')
                ->assertSee('href="/'.$portal.'/documents"', false)->assertDontSee('href="/'.$portal.'/archives"', false);
            $this->get('/'.$portal.'/archives')->assertNotFound();
            $otherPortal = $role === 0 ? 'admin' : 'super-admin';
            $this->get('/'.$otherPortal.'/documents')->assertForbidden();
        }
        $this->getJson('/api/v1/archives')->assertNotFound();
        $this->getJson('/api/v1/archives/tree')->assertNotFound();
        $this->postJson('/api/v1/archives/upload')->assertNotFound();
    }

    public function test_documents_pages_and_api_require_authentication(): void
    {
        foreach (['super-admin', 'admin', 'auditor', 'auditee'] as $portal) {
            $this->get('/'.$portal.'/documents')->assertRedirect('/login');
        }
        $this->getJson('/api/v1/documents')->assertUnauthorized();
        $this->getJson('/api/v1/documents/tree')->assertUnauthorized();
        $this->getJson('/api/v1/documents/audit-folders')->assertUnauthorized();
        $this->postJson('/api/v1/documents/upload')->assertUnauthorized();
        $this->postJson('/api/v1/documents/folders')->assertUnauthorized();
        $this->patchJson('/api/v1/documents/folders/'.new ObjectId())->assertUnauthorized();
        $this->deleteJson('/api/v1/documents/folders/'.new ObjectId())->assertUnauthorized();
        $this->patchJson('/api/v1/documents/folder-grants/'.new ObjectId())->assertUnauthorized();
    }
}
