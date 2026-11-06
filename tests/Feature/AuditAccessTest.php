<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuditAccessTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/audits')->assertRedirect('/login');
    }

    public function test_auditees_cannot_access_or_mutate_audit_assignments(): void
    {
        $user = new User(['roleId' => 3, 'isActive' => true]);
        $this->actingAs($user);
        $this->get('/audits')->assertForbidden();
        $this->post('/audits', [])->assertForbidden();
        $this->post('/audits/000000000000000000000001', ['action' => 'accept'])->assertForbidden();
    }
}
