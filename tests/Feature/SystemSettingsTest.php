<?php

namespace Tests\Feature;

use App\Models\ArchiveDocument;
use App\Models\User;
use App\Services\ArchiveEncryptionService;
use App\Services\ArchiveKeyRotationService;
use App\Services\SystemBackupService;
use App\Services\SystemSettingsService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    private ?string $databaseName = null;
    private ?string $directory = null;
    private array $restoreDatabases = [];
    private string $password = 'Settings-test-only-928!';

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Uses an isolated MongoDB database.'); }
        $this->databaseName = 'complitrack_test_'.bin2hex(random_bytes(6));
        $this->directory = storage_path('framework/testing/'.$this->databaseName);
        config([
            'database.connections.mongodb.database' => $this->databaseName,
            'system.path' => $this->directory.'/system',
            'filesystems.disks.local.root' => $this->directory.'/private',
            'filesystems.disks.public.root' => $this->directory.'/public',
            'services.complitrack.encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
        DB::purge('mongodb');
        Storage::forgetDisk(['local', 'public']);
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        // Test cleanup is restricted to this run's randomly named databases and directory.
        foreach ([$this->databaseName, ...$this->restoreDatabases] as $name) {
            if ($name !== null && str_starts_with($name, 'complitrack_test_')) {
                DB::connection('mongodb')->getMongoClient()->dropDatabase($name);
            }
        }
        if ($this->directory !== null) { File::deleteDirectory($this->directory); }
        parent::tearDown();
    }

    private function user(int $role = 0): User
    {
        return User::create([
            'email' => bin2hex(random_bytes(4)).'@example.test', 'fullName' => 'Settings Test',
            'password' => Hash::make($this->password), 'roleId' => $role,
            'roleSlug' => $role === 0 ? 'super_admin' : 'auditee', 'isActive' => true, 'userCode' => 'SA-0001',
        ]);
    }

    private function archive(string $contents = 'private archive contents'): ArchiveDocument
    {
        $encrypted = app(ArchiveEncryptionService::class)->encrypt($contents);
        $path = 'archives/'.bin2hex(random_bytes(6)).'.enc';
        Storage::disk('local')->put($path, $encrypted['ciphertext']);

        return ArchiveDocument::create([
            'originalName' => 'test.pdf', 'encryptedPath' => $path,
            'checksum' => hash('sha256', $contents), 'encryptionIv' => $encrypted['iv'],
            'encryptionTag' => $encrypted['tag'], 'encryptionKeyId' => $encrypted['keyId'], 'isEncrypted' => true,
        ]);
    }

    public function test_settings_page_restores_controls_without_firebase(): void
    {
        $this->withoutVite();
        $this->actingAs($this->user());
        $this->get('/super-admin/settings')->assertOk()
            ->assertSee('Automated Backup')->assertSee('Encryption Key Rotation')
            ->assertSee('Maintenance Mode')->assertSee('Wipe All System Data')
            ->assertDontSee('FCM Configuration')->assertDontSee('Firebase');
    }

    public function test_settings_require_superadmin_and_secrets_are_never_returned(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
        $this->actingAs($this->user(3));
        foreach (['backup', 'rotate-key', 'wipe'] as $action) {
            $this->postJson('/api/v1/settings/'.$action, [])->assertForbidden();
        }
        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->patchJson('/api/v1/settings', ['backupsEnabled' => true])->assertForbidden();
        $this->actingAs($this->user());
        app(SystemSettingsService::class)->update(fn ($s) => ['archiveKeys' => ['secret' => 'private-key-material']]);
        $this->getJson('/api/v1/settings')->assertOk()->assertDontSee('private-key-material');
        $this->patchJson('/api/v1/settings', ['backupsEnabled' => true])->assertOk()->assertJsonPath('backupsEnabled', true);
        $this->assertTrue(app(SystemSettingsService::class)->read()['backupsEnabled']);
        $this->assertStringNotContainsString('private-key-material', file_get_contents(app(SystemSettingsService::class)->path('settings.enc')));
    }

    public function test_maintenance_blocks_login_and_existing_users_but_superadmin_can_turn_it_off(): void
    {
        $admin = $this->user();
        $auditee = $this->user(3);
        app(SystemSettingsService::class)->update(fn ($s) => ['maintenanceEnabled' => true]);
        $this->postJson('/api/v1/auth/login', ['email' => $auditee->email, 'password' => $this->password])->assertStatus(503);
        $this->actingAs($auditee)->getJson('/api/v1/auth/me')->assertStatus(503);
        $this->get('/auditee/dashboard')->assertStatus(503);
        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => $this->password])->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/settings')->assertOk();
        $this->patchJson('/api/v1/settings', ['maintenanceEnabled' => false])->assertOk();
        $this->actingAs($auditee)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_rotation_requires_password_and_preserves_archive_contents(): void
    {
        $this->actingAs($this->user());
        $archive = $this->archive();
        ArchiveDocument::create(['recordType' => 'digital-reference', 'title' => 'Reference only']);
        ArchiveDocument::create(['recordType' => 'physical-original', 'title' => 'Paper document']);
        $oldPath = $archive->encryptedPath;
        $this->postJson('/api/v1/settings/rotate-key', ['password' => 'wrong'])->assertUnprocessable();
        $this->assertSame($oldPath, $archive->fresh()->encryptedPath);
        $this->postJson('/api/v1/settings/rotate-key', ['password' => $this->password])->assertOk();
        $archive->refresh();
        $this->assertNotSame($oldPath, $archive->encryptedPath);
        $this->assertNotNull($archive->encryptionKeyId);
        $this->assertSame('private archive contents', app(ArchiveEncryptionService::class)->decrypt(
            Storage::disk('local')->get($archive->encryptedPath), $archive->encryptionIv, $archive->encryptionTag, $archive->encryptionKeyId,
        ));
        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $newArchive = $this->archive('uploaded after rotation');
        $this->assertSame($archive->encryptionKeyId, $newArchive->encryptionKeyId);
    }

    public function test_interrupted_rotation_can_resume_without_losing_old_keys(): void
    {
        $archive = $this->archive();
        $contents = Storage::disk('local')->get($archive->encryptedPath);
        Storage::disk('local')->delete($archive->encryptedPath);
        try {
            app(ArchiveKeyRotationService::class)->rotate();
            $this->fail('Missing file should stop rotation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('missing', $exception->getMessage());
        }
        $pending = app(SystemSettingsService::class)->read();
        $this->assertTrue($pending['rotationPending']);
        Storage::disk('local')->put($archive->encryptedPath, $contents);
        app(ArchiveKeyRotationService::class)->rotate();
        $this->assertSame($pending['archiveKeyId'], $archive->fresh()->encryptionKeyId);
        $this->assertFalse(app(SystemSettingsService::class)->read()['rotationPending']);
    }

    public function test_backup_round_trip_preserves_bson_files_and_detects_truncation(): void
    {
        $user = $this->user();
        $archive = $this->archive();
        Storage::disk('public')->put('avatars/test.png', random_bytes(1100000));
        $database = DB::connection('mongodb')->getMongoDB();
        $database->selectCollection('users')->createIndex(['email' => 1], ['unique' => true]);
        $service = app(SystemBackupService::class);
        $name = $service->create();
        $path = app(SystemSettingsService::class)->path('backups').'/'.$name;
        $this->assertGreaterThan(5, $service->verify($path));
        $this->assertStringNotContainsString($user->email, file_get_contents($path));
        $target = $this->databaseName.'_restore';
        $this->restoreDatabases[] = $target;
        $service->restore($path, $target, $this->directory.'/restored');
        $restored = DB::connection('mongodb')->getMongoClient()->selectDatabase($target);
        $document = $restored->selectCollection('users')->findOne(['email' => $user->email]);
        $this->assertInstanceOf(\MongoDB\BSON\ObjectId::class, $document->_id);
        $this->assertSame((string) $user->getKey(), (string) $document->_id);
        $this->assertSame(Storage::disk('local')->get($archive->encryptedPath), file_get_contents($this->directory.'/restored/local/'.$archive->encryptedPath));
        $this->assertSame(Storage::disk('public')->get('avatars/test.png'), file_get_contents($this->directory.'/restored/public/avatars/test.png'));
        $lines = file($path);
        array_pop($lines);
        file_put_contents($path, implode('', $lines));
        $this->expectException(\RuntimeException::class);
        $service->verify($path);
    }

    public function test_wipe_requires_confirmation_and_preserves_only_current_admin_and_configuration(): void
    {
        $admin = $this->user();
        $this->user(3);
        $this->archive();
        Storage::disk('public')->put('avatars/test.png', 'avatar');
        Storage::disk('local')->put('keep-outside-uploads.txt', 'keep');
        $database = DB::connection('mongodb')->getMongoDB();
        $database->selectCollection('departments')->insertOne(['name' => 'Test']);
        $database->selectCollection('roles')->insertOne(['roleId' => 0]);
        $settings = app(SystemSettingsService::class);
        $settings->update(fn ($s) => ['backupsEnabled' => true]);
        File::ensureDirectoryExists($settings->path('backups'));
        File::put($settings->path('backups').'/test.ctbackup', 'old backup');
        $this->actingAs($admin);
        $this->postJson('/api/v1/settings/wipe', ['password' => $this->password, 'confirmation' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/settings/wipe', ['password' => 'wrong', 'confirmation' => 'WIPE ALL DATA'])->assertUnprocessable();
        $this->assertSame(2, User::count());
        $this->postJson('/api/v1/settings/wipe', ['password' => $this->password, 'confirmation' => 'WIPE ALL DATA'])->assertOk();
        $this->assertSame(1, User::count());
        $this->assertNotNull(User::find($admin->getKey()));
        $this->assertSame(0, $database->selectCollection('departments')->countDocuments());
        $this->assertSame(1, $database->selectCollection('roles')->countDocuments());
        $this->assertSame([], Storage::disk('local')->allFiles('archives'));
        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
        $this->assertTrue(Storage::disk('local')->exists('keep-outside-uploads.txt'));
        $this->assertFalse(is_dir($settings->path('backups')));
        $this->assertTrue($settings->read()['backupsEnabled']);
    }

    public function test_busy_system_rejects_a_second_operation_without_mutating_settings(): void
    {
        $this->actingAs($this->user());
        app(\App\Services\SystemOperationLock::class)->run(function () {
            $this->patchJson('/api/v1/settings', ['backupsEnabled' => true])->assertStatus(423);
        });
        $this->assertFalse(app(SystemSettingsService::class)->publicState()['backupsEnabled']);
    }
}
