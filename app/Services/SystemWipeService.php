<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SystemWipeService
{
    public function wipe(User $actor, ?string $sessionId): void
    {
        app(SystemOperationLock::class)->run(function () use ($actor, $sessionId) {
            $connection = DB::connection('mongodb');
            $database = $connection->getMongoDB();
            $session = $connection->getMongoClient()->startSession();
            $collections = iterator_to_array($database->listCollectionNames());
            try {
                $session->startTransaction();
                foreach ($collections as $collection) {
                    if (str_starts_with($collection, 'system.') || in_array($collection, ['users', 'roles', 'change_stream_state'], true)) {
                        continue;
                    }
                    $database->selectCollection($collection)->deleteMany([], ['session' => $session]);
                }
                $actorId = \App\Support\MongoId::parse((string) $actor->getKey());
                $database->selectCollection('users')->deleteMany(['_id' => ['$ne' => $actorId]], ['session' => $session]);
                $database->selectCollection('users')->updateOne(['_id' => $actorId], ['$unset' => [
                    'avatarPath' => '', 'avatarData' => '', 'avatarMimeType' => '', 'avatarVersion' => '',
                    'fcmToken' => '', 'remember_token' => '',
                ]], ['session' => $session]);
                // Keep the retained superadmin's code reserved when other counters restart.
                preg_match('/(\d+)$/', $actor->userCode ?? '', $code);
                $database->selectCollection('counters')->updateOne(['_id' => 'SA'], ['$set' => ['seq' => (int) ($code[1] ?? 1)]], ['upsert' => true, 'session' => $session]);
                $session->commitTransaction();
            } catch (\Throwable $exception) {
                if ($session->isInTransaction()) {
                    $session->abortTransaction();
                }
                throw $exception;
            } finally {
                $session->endSession();
            }
            // Only application-owned upload directories are removed; settings and keys remain.
            foreach (['local' => 'archives', 'public' => 'avatars'] as $disk => $directory) {
                if (! Storage::disk($disk)->deleteDirectory($directory)) {
                    throw new RuntimeException('Database cleared, but some uploaded files could not be removed. Retry the wipe to finish cleanup.');
                }
            }
            $backupDirectory = app(SystemSettingsService::class)->path('backups');
            if (is_dir($backupDirectory) && ! File::deleteDirectory($backupDirectory)) {
                throw new RuntimeException('Database cleared, but backup cleanup failed. Retry the wipe.');
            }
            if (in_array(config('session.driver'), ['database', 'mongodb'], true)) {
                $query = DB::connection(config('session.connection'))->table(config('session.table'));
                if ($sessionId) {
                    $query->where('id', '!=', $sessionId);
                }
                $query->delete();
            } elseif (config('session.driver') === 'file') {
                foreach (File::files(config('session.files')) as $file) {
                    if ($file->getFilename() !== $sessionId) {
                        File::delete($file->getPathname());
                    }
                }
            }
            app(SystemSettingsService::class)->update(function ($state) {
                $state['lastBackup'] = null;
                $state['backupError'] = null;
                $state['rotationPending'] = false;

                return $state;
            });
        });
    }
}
