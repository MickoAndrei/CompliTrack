<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SystemBackupService
{
    public function create(): string
    {
        return app(SystemOperationLock::class)->run(function () {
            $settings = app(SystemSettingsService::class);
            $directory = $settings->path('backups');
            File::ensureDirectoryExists($directory, 0700);
            $id = bin2hex(random_bytes(16));
            $name = now()->utc()->format('Ymd-His').'-'.$id.'.ctbackup';
            $partial = $directory.DIRECTORY_SEPARATOR.$name.'.partial';
            $stream = fopen($partial, 'xb');
            if (! $stream) {
                throw new RuntimeException('Unable to create backup file.');
            }
            chmod($partial, 0600);
            $sequence = 0;
            $write = function (string $type, array $data) use ($stream, $id, &$sequence) {
                $record = Crypt::encryptString(json_encode([
                    'id' => $id, 'sequence' => $sequence++, 'type' => $type, 'data' => $data,
                ], JSON_THROW_ON_ERROR))."\n";
                if (fwrite($stream, $record) !== strlen($record)) {
                    throw new RuntimeException('Unable to finish writing backup.');
                }
            };
            $session = null;
            try {
                $connection = DB::connection('mongodb');
                $database = $connection->getMongoDB();
                $write('header', ['version' => 1, 'database' => $database->getDatabaseName(), 'createdAt' => now()->toIso8601String()]);
                $write('settings', ['settings' => $settings->read(), 'legacyArchiveKey' => config('services.complitrack.encryption_key')]);
                // Snapshot reads preserve BSON types and a consistent database view across collections.
                $session = $connection->getMongoClient()->startSession(['snapshot' => true]);
                foreach ($database->listCollections() as $info) {
                    $collection = $info->getName();
                    if (str_starts_with($collection, 'system.') || $collection === 'change_stream_state') {
                        continue;
                    }
                    if ($info->getType() !== 'collection') {
                        throw new RuntimeException('Backup does not support database views.');
                    }
                    $write('collection', ['name' => $collection]);
                    foreach ($database->selectCollection($collection)->listIndexes() as $index) {
                        $write('index', ['collection' => $collection, 'json' => \MongoDB\BSON\Document::fromPHP($index->__debugInfo())->toCanonicalExtendedJSON()]);
                    }
                    foreach ($database->selectCollection($collection)->find([], ['session' => $session]) as $document) {
                        $write('document', ['collection' => $collection, 'json' => \MongoDB\BSON\Document::fromPHP($document)->toCanonicalExtendedJSON()]);
                    }
                }
                foreach (['local' => 'archives', 'public' => 'avatars'] as $diskName => $folder) {
                    $disk = Storage::disk($diskName);
                    foreach ($disk->allFiles($folder) as $path) {
                        $input = $disk->readStream($path);
                        if (! is_resource($input)) {
                            throw new RuntimeException('Unable to read an uploaded file for backup.');
                        }
                        try {
                            $write('file', ['disk' => $diskName, 'path' => $path]);
                            while (! feof($input)) {
                                $chunk = fread($input, 524288);
                                if ($chunk === false) {
                                    throw new RuntimeException('Unable to read backup file chunk.');
                                }
                                $write('chunk', ['contents' => base64_encode($chunk)]);
                            }
                        } finally {
                            fclose($input);
                        }
                    }
                }
                $write('end', ['records' => $sequence]);
                fflush($stream);
                fclose($stream);
                $stream = null;
                $this->verify($partial);
                if (! rename($partial, $directory.DIRECTORY_SEPARATOR.$name)) {
                    throw new RuntimeException('Unable to finalize backup.');
                }
                $settings->update(function ($state) use ($name) {
                    $state['lastBackup'] = ['file' => $name, 'completedAt' => now()->toIso8601String()];
                    $state['backupError'] = null;

                    return $state;
                });

                return $name;
            } catch (Throwable $exception) {
                $settings->update(function ($state) {
                    $state['backupError'] = 'Backup failed. Check server logs and MongoDB replica-set availability.';

                    return $state;
                });
                throw $exception;
            } finally {
                $session?->endSession();
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (is_file($partial)) {
                    unlink($partial);
                }
            }
        });
    }

    public function records(string $path): \Generator
    {
        $stream = fopen($path, 'rb');
        if (! $stream) {
            throw new RuntimeException('Backup file cannot be opened.');
        }
        $sequence = 0;
        $id = null;
        $ended = false;
        try {
            while (($line = fgets($stream)) !== false) {
                $record = json_decode(Crypt::decryptString(trim($line)), true, 512, JSON_THROW_ON_ERROR);
                $id ??= $record['id'];
                if ($ended || $record['id'] !== $id || $record['sequence'] !== $sequence++) {
                    throw new RuntimeException('Backup record order is invalid.');
                }
                if ($sequence === 1 && ($record['type'] !== 'header' || $record['data']['version'] !== 1)) {
                    throw new RuntimeException('Unsupported backup format.');
                }
                $ended = $record['type'] === 'end';
                if ($ended && $record['data']['records'] !== $sequence - 1) {
                    throw new RuntimeException('Backup record count is invalid.');
                }
                yield $record;
            }
            if (! $ended) {
                throw new RuntimeException('Backup is incomplete.');
            }
        } finally {
            fclose($stream);
        }
    }

    public function verify(string $path): int
    {
        $count = 0;
        foreach ($this->records($path) as $record) {
            $count++;
        }

        return $count;
    }

    public function restore(string $path, string $databaseName, string $directory): void
    {
        // Restore only into a new database and new folder; never overwrite live data.
        if (! preg_match('/^[A-Za-z0-9_-]{1,63}$/', $databaseName)) {
            throw new RuntimeException('Invalid restore database name.');
        }
        $client = DB::connection('mongodb')->getMongoClient();
        if ($databaseName === config('database.connections.mongodb.database')
            || in_array($databaseName, iterator_to_array($client->listDatabaseNames()), true)
            || file_exists($directory)) {
            throw new RuntimeException('Restore requires a new database name and a new output directory.');
        }
        $this->verify($path);
        File::ensureDirectoryExists($directory, 0700);
        $database = $client->selectDatabase($databaseName);
        $file = null;
        try {
            foreach ($this->records($path) as $record) {
                $data = $record['data'];
                switch ($record['type']) {
                    case 'settings':
                        File::put($directory.'/recovery-settings.enc', Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)));
                        break;
                    case 'collection':
                        $database->createCollection($data['name']);
                        break;
                    case 'index':
                        $index = \MongoDB\BSON\Document::fromJSON($data['json'])->toPHP(['root' => 'array', 'document' => 'array']);
                        if ($index['name'] !== '_id_') {
                            $keys = $index['key'];
                            unset($index['key'], $index['v'], $index['ns']);
                            $database->selectCollection($data['collection'])->createIndex($keys, $index);
                        }
                        break;
                    case 'document':
                        $database->selectCollection($data['collection'])->insertOne(\MongoDB\BSON\Document::fromJSON($data['json'])->toPHP());
                        break;
                    case 'file':
                        if (is_resource($file)) {
                            fclose($file);
                        }
                        if (! in_array($data['disk'], ['local', 'public'], true)
                            || ! preg_match('#^(archives|avatars)/[A-Za-z0-9._-]+$#', $data['path'])) {
                            throw new RuntimeException('Invalid backup file path.');
                        }
                        $destination = $directory.'/'.$data['disk'].'/'.$data['path'];
                        File::ensureDirectoryExists(dirname($destination), 0700);
                        $file = fopen($destination, 'xb');
                        break;
                    case 'chunk':
                        $chunk = base64_decode($data['contents'], true);
                        if (! is_resource($file) || $chunk === false || fwrite($file, $chunk) !== strlen($chunk)) {
                            throw new RuntimeException('Unable to restore file contents.');
                        }
                }
            }
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }
    }
}
