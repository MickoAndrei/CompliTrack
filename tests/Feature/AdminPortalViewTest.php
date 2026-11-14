<?php

namespace Tests\Feature;

use App\Models\User;
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class AdminPortalViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(\App\Services\AuditLogService::class)->shouldReceive('record')->andReturnNull();
        $user = new User(['fullName' => 'Admin Test', 'roleId' => 1, 'isActive' => true]);
        $user->setAttribute('_id', new ObjectId());
        $this->actingAs($user);
    }

    public function test_admin_dashboard_has_summary_cards_without_quick_actions(): void
    {
        $this->get('/admin/dashboard')->assertOk()
            ->assertSee('admin-dashboard')->assertSee('admin-stat-heading')
            ->assertSee('admin-stat-number')->assertSee('css/admin-ui.css')
            ->assertSee('Audit Team Leader Dashboard')->assertSee('Active Audits')->assertSee('Pending Audit Reviews')
            ->assertSee('Overdue Corrective Actions')->assertSee('Requires My Attention')
            ->assertSee('Audit Assignment Progress')->assertSee('Not Yet Assessed')
            ->assertSee('Total Auditors')->assertSee('Total Auditees')
            ->assertDontSee('Audit Coordination')->assertDontSee('CET Resources')
            ->assertDontSee('My Account')->assertDontSee('atl-sidebar-group')
            ->assertSee('Audit Reviews')->assertSee('Corrective Monitoring')
            ->assertDontSee('Quick actions');
    }

    public function test_admin_header_has_profile_and_account_notification_dropdown(): void
    {
        $this->get('/admin/dashboard')->assertOk()
            ->assertSee('Open my profile')->assertSee('header-user-name')
            ->assertSee('Admin Test')->assertSee('header-notification-panel')
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('href="/admin/notifications"', false)
            ->assertDontSee('href="/auditee/notifications"', false);
    }

    public function test_admin_profile_keeps_edit_password_and_photo_controls(): void
    {
        $this->get('/admin/profile')->assertOk()->assertSee('Save Profile')
            ->assertSee('Change Password')->assertSee('Upload or update profile picture')
            ->assertSee('css/profile.css')->assertSee('header-user-badge');
    }

    public function test_other_admin_sections_have_scoped_styling_and_readable_notifications(): void
    {
        foreach (['users', 'departments', 'compliance-records', 'notifications'] as $page) {
            if ($page === 'notifications') {
                $this->get('/admin/notifications')->assertOk()->assertSee('notification-inbox')->assertSee('Mark all read');
                continue;
            }
            $response = $this->get('/admin/'.$page)->assertOk()->assertSee('admin-resource-page');
            if (in_array($page, ['users', 'departments'], true)) {
                $response->assertDontSee('admin-resource-heading')
                    ->assertSee('attention-page directory-page');
            } else {
                $response->assertSee('admin-resource-heading');
            }
        }
        foreach (['users', 'departments'] as $page) {
            $this->get('/admin/'.$page)->assertOk()->assertSee('admin-directory-table')
                ->assertSee('css/user-management.css')->assertSee('Active')
                ->assertSee('admin-directory-loading')->assertSee('perPage: 10', false)
                ->assertDontSee('userModalTitle')->assertDontSee('departmentModalTitle')
                ->assertDontSee('@click="startUserEdit(row)"', false)->assertDontSee('@click="startDepartmentEdit(row)"', false)
                ->assertDontSee('user-create-trigger')->assertDontSee('department-create-trigger');
        }
        $this->get('/admin/notifications')->assertOk()->assertSee('notification-list')
            ->assertSee('Unread')->assertSee('Read')->assertSee('View details');
        $this->get('/admin/reports')->assertOk()->assertSee('admin-reports')->assertSee('Recorded Check Compliance');
        $this->get('/admin/archives')->assertOk()->assertSee('qms-explorer')->assertSee('QMS Archives');
    }

    public function test_admin_cannot_write_users_or_departments_through_the_api(): void
    {
        $id = (string) new ObjectId();
        foreach (['users', 'departments'] as $resource) {
            $this->postJson('/api/v1/'.$resource, [])->assertForbidden();
            $this->patchJson('/api/v1/'.$resource.'/'.$id, [])->assertForbidden();
            $this->putJson('/api/v1/'.$resource.'/'.$id, [])->assertForbidden();
            $this->deleteJson('/api/v1/'.$resource.'/'.$id)->assertForbidden();
            $this->postJson('/api/v1/'.$resource.'/'.$id.'/reactivate')->assertForbidden();
        }
        $this->postJson('/api/v1/users/'.$id.'/reset-password')->assertForbidden();
    }
}
