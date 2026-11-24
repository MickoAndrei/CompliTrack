<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function authenticatedEntries(): array
    {
        $cases = [];
        foreach ([0 => '/super-admin/dashboard', 1 => '/admin/dashboard', 2 => '/auditor/dashboard', 3 => '/auditee/dashboard'] as $role => $dashboard) {
            foreach (['/', '/login'] as $entry) {
                $cases[$role.' '.$entry] = [$role, $entry, $dashboard];
            }
        }

        return $cases;
    }

    #[DataProvider('authenticatedEntries')]
    public function test_signed_in_users_open_their_dashboard_within_the_existing_session(int $role, string $entry, string $dashboard): void
    {
        $user = new User(['roleId' => $role, 'isActive' => true]);
        $this->actingAs($user, 'web');

        // Each navigation must use the existing login without rendering a login form.
        $this->get($entry)->assertRedirect($dashboard);
        $this->get($entry)->assertRedirect($dashboard);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_guests_still_see_the_styled_login_form_at_both_entries(): void
    {
        foreach (['/', '/login'] as $entry) {
            $this->get($entry)->assertOk()->assertViewIs('auth.login')
                ->assertSee('id="loginEmail"', false)
                ->assertSee('css/auth-login.css')
                ->assertDontSee('id="main-content"', false);
        }
    }
}
