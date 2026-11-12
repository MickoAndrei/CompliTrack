<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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
        foreach ([0 => 'super-admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'] as $role => $portal) {
            $this->actor($role);
            $this->get('/profile')->assertRedirect('/'.$portal.'/profile');
            $this->get('/'.$portal.'/profile')->assertOk()
                ->assertSee('css/profile.css')->assertSee('super-admin-profile-card')
                ->assertSee('href="/'.$portal.'/profile"', false)
                ->assertSee('name="fullName"', false)->assertSee('Save Profile')
                ->assertSee('Change Password')->assertSee('Switch Account')
                ->assertSee('Upload or update profile picture');
        }
    }

    public function test_guests_cannot_read_or_change_profiles(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        foreach (['super-admin', 'admin', 'auditor', 'auditee'] as $portal) {
            $this->get('/'.$portal.'/profile')->assertRedirect('/login');
        }
        $this->patch('/profile', ['fullName' => 'Other Name'])->assertRedirect('/login');
        $this->patch('/profile/password', [])->assertRedirect('/login');
    }

    public function test_users_cannot_open_another_roles_profile_url(): void
    {
        $portals = ['super-admin', 'admin', 'auditor', 'auditee'];
        foreach ($portals as $role => $portal) {
            $this->actor($role);
            foreach ($portals as $otherRole => $otherPortal) {
                if ($otherRole !== $role) {
                    $this->get('/'.$otherPortal.'/profile')->assertForbidden();
                }
            }
        }
    }

    public function test_name_update_only_changes_the_authenticated_users_name(): void
    {
        $user = $this->actor();
        $user->shouldReceive('save')->once()->andReturnTrue();
        $this->mock(AuditLogService::class, function ($mock) use ($user) {
            $mock->shouldReceive('record')->once()->with(Mockery::any(), 'Updated profile name', 'user', $user->getKey());
        });
        $this->from('/auditee/profile')->patch('/profile', [
            'fullName' => '  Updated Name  ', 'email' => 'other@example.test', 'roleId' => 0,
        ])->assertRedirect('/auditee/profile')->assertSessionHas('status', 'Profile details updated.');
        $this->assertSame('Updated Name', $user->fullName);
        $this->assertSame('profile@example.test', $user->email);
        $this->assertSame(3, $user->roleId);
    }

    public function test_avatar_uploads_and_reads_remain_isolated_when_switching_accounts(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.public.root' => Storage::disk('public')->path('')]);
        $first = $this->actor(0);
        $first->shouldReceive('save')->twice()->andReturnTrue();
        $firstImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
        $firstUpload = $this->post('/api/v1/auth/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('first.png', $firstImage),
        ])->assertOk();
        $firstUrl = $firstUpload->json('avatarUrl');
        $firstData = $first->avatarData->getData();
        $this->assertNull($first->avatarPath);
        $this->assertSame($firstImage, $firstData);
        $this->assertArrayNotHasKey('avatarData', $first->toArray());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($first->avatarUrl(), $firstUrl);
        $response = $this->get($firstUrl)->assertOk();
        $this->assertContains('Cookie', $response->baseResponse->getVary());
        $this->assertContains('Authorization', $response->baseResponse->getVary());

        $second = $this->actor(0);
        $second->shouldReceive('save')->once()->andReturnTrue();
        $this->assertNull($second->avatarUrl());
        // Even an old account's URL must resolve only the authenticated account's photo.
        $this->get($firstUrl)->assertNotFound();
        $secondImage = "\xff\xd8\xff".'second-account-photo';
        $secondUpload = $this->post('/api/v1/auth/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('second.jpg', $secondImage),
            'user' => (string) $first->getKey(),
        ])->assertOk();
        $this->assertNotSame($firstUrl, $secondUpload->json('avatarUrl'));
        $this->assertSame($firstData, $first->avatarData->getData());
        $response = $this->get($secondUpload->json('avatarUrl'))->assertOk();
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertSame('3600', (string) $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame($secondImage, $response->getContent());

        $this->actingAs($first);
        $response = $this->get($firstUrl)->assertOk();
        $this->assertSame($firstImage, $response->getContent());
        $this->get('/super-admin/profile')->assertOk()->assertSee($firstUrl);
        $updated = $this->post('/api/v1/auth/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('replacement.jpg', $secondImage),
        ])->assertOk();
        $this->assertNotSame($firstUrl, $updated->json('avatarUrl'));
        $this->assertSame($secondImage, $first->avatarData->getData());
        $this->assertSame($secondImage, $second->avatarData->getData());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_avatar_urls_differ_by_account_even_if_the_stored_path_matches(): void
    {
        $first = $this->actor(0);
        $second = $this->actor(0);
        $first->avatarPath = $second->avatarPath = 'avatars/shared.png';
        $this->assertNotSame($first->avatarUrl(), $second->avatarUrl());
    }

    public function test_avatar_cache_revalidates_and_only_caches_the_current_account_and_version(): void
    {
        $user = $this->actor(0);
        $user->shouldReceive('save')->twice()->andReturnTrue();
        $first = "\xff\xd8\xff".'first-photo';
        $second = "\xff\xd8\xff".'replacement-photo';
        app(\App\Services\ProfileImageService::class)->store($user, $first, 'image/jpeg');
        $url = $user->avatarUrl();
        $etag = $this->get($url)->assertOk()->headers->get('ETag');
        $this->get($url, ['If-None-Match' => $etag])->assertStatus(304)->assertContent('');
        $this->get('/api/v1/auth/avatar')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($url.'&user=another-account')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        app(\App\Services\ProfileImageService::class)->store($user, $second, 'image/jpeg');
        $this->assertNotSame($url, $user->avatarUrl());
        $this->get($user->avatarUrl(), ['If-None-Match' => $etag])->assertOk()->assertContent($second);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_legacy_profile_photo_is_copied_into_the_database_before_serving(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.public.root' => Storage::disk('public')->path('')]);
        $user = $this->actor(0);
        $user->shouldReceive('save')->once()->andReturnTrue();
        $contents = "\xff\xd8\xff".'legacy-photo';
        Storage::disk('public')->put('avatars/legacy.jpg', $contents);
        $user->avatarPath = 'avatars/legacy.jpg';

        $this->get('/api/v1/auth/avatar')->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertContent($contents);
        $this->assertNull($user->avatarPath);
        $this->assertSame($contents, $user->avatarData->getData());
        // Serving the migrated image no longer depends on the local file.
        Storage::disk('public')->delete('avatars/legacy.jpg');
        $this->get($user->avatarUrl())->assertOk()->assertContent($contents);
    }

    public function test_invalid_and_oversized_avatar_uploads_do_not_save(): void
    {
        $user = $this->actor(0);
        $user->shouldNotReceive('save');
        $this->postJson('/api/v1/auth/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('invalid.png', 'not-an-image'),
        ])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->postJson('/api/v1/auth/avatar', [
            'avatar' => UploadedFile::fake()->create('large.png', 2049, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('avatar');
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
