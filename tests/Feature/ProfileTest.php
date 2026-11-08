<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function actor(int $role = 3): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            '_id' => new \MongoDB\BSON\ObjectId(),
            'fullName' => 'Profile Test',
            'email' => 'profile@example.test',
            'roleId' => $role,
            'isActive' => true,
            'password' => Hash::make('Original-Password-123!'),
        ]);
        $this->actingAs($user);
        return $user;
    }

    public function test_all_roles_can_open_the_imported_profile_with_edit_and_security_controls(): void
    {
        foreach ([0, 1, 2, 3] as $role) {
            $this->actor($role);
            $this->get('/profile')->assertOk()
                ->assertSee('css/profile.css')->assertSee('super-admin-profile-card')
                ->assertSee('name="fullName"', false)->assertSee('Save Profile')
                ->assertSee('Change Password')->assertSee('Switch Account')
                ->assertSee('Upload or update profile picture');
        }
    }

    public function test_guests_cannot_read_or_change_profiles(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->patch('/profile', ['fullName' => 'Other Name'])->assertRedirect('/login');
        $this->patch('/profile/password', [])->assertRedirect('/login');
    }

    public function test_name_update_only_changes_the_authenticated_users_name(): void
    {
        $user = $this->actor();
        $user->shouldReceive('save')->once()->andReturnTrue();
        $this->mock(AuditLogService::class, function ($mock) use ($user) {
            $mock->shouldReceive('record')->once()->with(Mockery::any(), 'Updated profile name', 'user', $user->getKey());
        });
        $this->from('/profile')->patch('/profile', [
            'fullName' => '  Updated Name  ', 'email' => 'other@example.test', 'roleId' => 0,
        ])->assertRedirect('/profile')->assertSessionHas('status', 'Profile details updated.');
        $this->assertSame('Updated Name', $user->fullName);
        $this->assertSame('profile@example.test', $user->email);
        $this->assertSame(3, $user->roleId);
    }

    public function test_invalid_names_are_rejected_without_saving(): void
    {
        $user = $this->actor();
        $user->shouldNotReceive('save');
        foreach (['', '   ', str_repeat('a', 256)] as $name) {
            $this->from('/profile')->patch('/profile', ['fullName' => $name])
                ->assertRedirect('/profile')->assertSessionHasErrors('fullName');
        }
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $user = $this->actor();
        $user->shouldNotReceive('save');
        $this->from('/profile')->patch('/profile/password', [
            'current_password' => 'Incorrect-Password',
            'password' => 'Replacement-Password-123!',
            'password_confirmation' => 'Replacement-Password-123!',
        ])->assertRedirect('/profile')->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Original-Password-123!', $user->password));
    }

    public function test_password_change_rejects_reused_short_and_unconfirmed_passwords(): void
    {
        $user = $this->actor();
        $user->shouldNotReceive('save');
        foreach ([
            ['Original-Password-123!', 'Original-Password-123!'],
            ['short', 'short'],
            ['Replacement-Password-123!', 'Different-Password-123!'],
        ] as [$password, $confirmation]) {
            $this->from('/profile')->patch('/profile/password', [
                'current_password' => 'Original-Password-123!',
                'password' => $password, 'password_confirmation' => $confirmation,
            ])->assertRedirect('/profile')->assertSessionHasErrors('password');
        }
    }

    public function test_password_change_hashes_the_password_and_records_a_plain_audit_event(): void
    {
        $user = $this->actor();
        $user->shouldReceive('save')->once()->andReturnTrue();
        $this->mock(AuditLogService::class, function ($mock) use ($user) {
            $mock->shouldReceive('record')->once()->with(Mockery::any(), 'Changed account password', 'user', $user->getKey());
        });
        $this->from('/profile')->patch('/profile/password', [
            'current_password' => 'Original-Password-123!',
            'password' => 'Replacement-Password-123!',
            'password_confirmation' => 'Replacement-Password-123!',
        ])->assertRedirect('/profile')->assertSessionHas('status', 'Password updated.')
            ->assertSessionMissing('test_reset_link');
        $this->assertTrue(Hash::check('Replacement-Password-123!', $user->password));
        $this->assertFalse($user->passwordChangeRequired);
    }
}
