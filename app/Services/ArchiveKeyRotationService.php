<?php

namespace App\Services;

use App\Models\ArchiveDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ArchiveKeyRotationService
{
    public function rotate(): int
    {
        return app(SystemOperationLock::class)->run(function () {
            $settings = app(SystemSettingsService::class);
            // Persist the new key first. Old keys remain available if a run is interrupted.
            $state = $settings->update(function ($state) {
                if (! ($state['rotationPending'] ?? false)) {
                    $id = bin2hex(random_bytes(12));
                    $state['archiveKeys'][$id] = 'base64:'.base64_encode(random_bytes(32));
                    $state['archiveKeyId'] = $id;
                }
                $state['rotationPending'] = true;

                return $state;
            });
            $encryption = app(ArchiveEncryptionService::class);
            $disk = Storage::disk('local');
            $count = 0;
            foreach (ArchiveDocument::cursor() as $archive) {
                // QMS reference records have no encrypted file to rotate.
                if (in_array($archive->recordType, ['digital-reference', 'physical-original'], true)) {
                    continue;
                }
                if ($archive->encryptionKeyId === $state['archiveKeyId']) {
                    continue;
                }
                $oldPath = $archive->encryptedPath;
                if (! preg_match('/^archives\/[A-Za-z0-9._-]+\.enc$/', $oldPath)) {
                    throw new RuntimeException('Invalid archive path. Rotation can be resumed after repair.');
                }
                $ciphertext = $disk->get($oldPath);
                if ($ciphertext === null) {
                    throw new RuntimeException('An archive file is missing. Rotation can be resumed after repair.');
                }
                $plaintext = $encryption->decrypt($ciphertext, $archive->encryptionIv, $archive->encryptionTag, $archive->encryptionKeyId);
                if (! hash_equals($archive->checksum, hash('sha256', $plaintext))) {
                    throw new RuntimeException('Archive checksum failed. Rotation can be resumed after repair.');
                }
                $encrypted = $encryption->encrypt($plaintext);
                $newPath = 'archives/'.bin2hex(random_bytes(20)).'.enc';
                if (! $disk->put($newPath, $encrypted['ciphertext'])) {
                    throw new RuntimeException('Unable to write rotated archive.');
                }
                // A single document update switches path, IV, tag and key together.
                $archive->forceFill([
                    'encryptedPath' => $newPath,
                    'encryptionIv' => $encrypted['iv'],
                    'encryptionTag' => $encrypted['tag'],
                    'encryptionKeyId' => $encrypted['keyId'],
                ])->save();
                $disk->delete($oldPath);
                $count++;
            }
            $settings->update(function ($state) {
                $state['rotationPending'] = false;
                $state['lastRotation'] = now()->toIso8601String();

                return $state;
            });

            return $count;
        });
    }
}
