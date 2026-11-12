<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProfileImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use MongoDB\BSON\Binary;
use Tests\TestCase;

class ProfileImageDatabaseTest extends TestCase
{
    private ?string $databaseName = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') {
            $this->markTestSkipped('Uses an isolated MongoDB database.');
        }
        $this->databaseName = 'complitrack_avatar_test_'.bin2hex(random_bytes(6));
        config(['database.connections.mongodb.database' => $this->databaseName]);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
    }

    protected function tearDown(): void
    {
        if ($this->databaseName !== null && str_starts_with($this->databaseName, 'complitrack_avatar_test_')) {
            DB::connection('mongodb')->getMongoClient()->dropDatabase($this->databaseName);
        }
        parent::tearDown();
    }

    public function test_photo_bytes_survive_a_database_round_trip_without_local_files(): void
    {
        Storage::fake('public');
        $user = User::create(['email' => 'avatar@example.test', 'fullName' => 'Avatar Test', 'roleId' => 0, 'isActive' => true]);
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
        app(ProfileImageService::class)->store($user, $contents, 'image/png');

        $stored = DB::connection('mongodb')->getMongoDB()->selectCollection('users')->findOne(['email' => $user->email]);
        $this->assertInstanceOf(Binary::class, $stored['avatarData']);
        $this->assertSame($contents, $stored['avatarData']->getData());
        $user = User::findOrFail($user->getKey());
        $this->assertArrayNotHasKey('avatarData', $user->toArray());
        $this->actingAs($user)->get($user->avatarUrl())->assertOk()->assertContent($contents);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_legacy_migration_command_persists_images_and_can_be_rerun(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.public.root' => Storage::disk('public')->path('')]);
        $user = User::create(['email' => 'legacy@example.test', 'fullName' => 'Legacy Test', 'roleId' => 0, 'isActive' => true]);
        $user->avatarPath = 'avatars/legacy.jpg';
        $user->save();
        $contents = "\xff\xd8\xff".'legacy-photo';
        Storage::disk('public')->put('avatars/legacy.jpg', $contents);

        $this->artisan('avatars:migrate')->expectsOutput('Profile images stored in MongoDB: 1. Unavailable legacy images: 0.')->assertSuccessful();
        $user->refresh();
        $this->assertNull($user->avatarPath);
        $this->assertSame($contents, $user->avatarData->getData());
        $this->artisan('avatars:migrate')->expectsOutput('Profile images stored in MongoDB: 0. Unavailable legacy images: 0.')->assertSuccessful();
    }
}
