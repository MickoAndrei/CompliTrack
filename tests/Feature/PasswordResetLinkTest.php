<?php

namespace Tests\Feature;

use Tests\TestCase;

class PasswordResetLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_bare_reset_page_requests_a_new_link_instead_of_an_empty_token_form(): void
    {
        $this->get('/reset-password')->assertRedirect('/forgot-password')->assertSessionHas('status');
    }

    public function test_malformed_reset_link_does_not_open_a_broken_form(): void
    {
        $this->get('/reset-password?email=person@example.test&token=short')->assertRedirect('/forgot-password');
    }

    public function test_complete_link_takes_precedence_over_stale_flashed_inputs(): void
    {
        $token = str_repeat('a', 64);
        $this->withSession(['_old_input' => ['token' => '', 'email' => 'old@example.test']])
            ->get('/reset-password?email=person@example.test&token='.$token)
            ->assertOk()->assertSee('name="token" value="'.$token.'"', false)
            ->assertSee('value="person@example.test"', false);
    }

    public function test_missing_posted_token_never_allows_a_password_change(): void
    {
        $this->post('/reset-password', ['email' => 'person@example.test', 'password' => 'Long-test-password', 'password_confirmation' => 'Long-test-password'])
            ->assertRedirect('/forgot-password')->assertSessionHas('status');
    }

    public function test_html_encoded_separator_copied_from_mail_log_preserves_token(): void
    {
        $token = str_repeat('b', 64);
        $this->get('/reset-password?email=person%40example.test&amp;token='.$token)
            ->assertOk()->assertSee('name="token" value="'.$token.'"', false);
        $this->get('/reset-password?token='.$token.'&amp;email=person%40example.test')
            ->assertOk()->assertSee('value="person@example.test"', false);
    }
}
