<?php

namespace App\Services;

use RuntimeException;

class ArchiveEncryptionService
{
    public function encrypt(string $plaintext): array
    {
        $keyId = app(SystemSettingsService::class)->read()['archiveKeyId'] ?? null;
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key($keyId),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16,
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Archive encryption failed.');
        }

        return [
            'ciphertext' => $ciphertext,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'keyId' => $keyId,
        ];
    }

    public function decrypt(string $ciphertext, string $iv, string $tag, ?string $keyId = null): string
    {
        $decodedIv = base64_decode($iv, true);
        $decodedTag = base64_decode($tag, true);
        if ($decodedIv === false || strlen($decodedIv) !== 12
            || $decodedTag === false || strlen($decodedTag) !== 16) {
            throw new RuntimeException('Archive encryption metadata is invalid.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key($keyId),
            OPENSSL_RAW_DATA,
            $decodedIv,
            $decodedTag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Archive authentication failed.');
        }

        return $plaintext;
    }

    private function key(?string $keyId = null): string
    {
        $configured = $keyId === null
            ? config('services.complitrack.encryption_key')
            : (app(SystemSettingsService::class)->read()['archiveKeys'][$keyId] ?? null);
        if (!is_string($configured) || $configured === '') {
            throw new RuntimeException('ENCRYPTION_KEY is not configured.');
        }

        $key = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : base64_decode($configured, true);
        if ($key === false) {
            $key = $configured;
        }
        if (strlen($key) !== 32) {
            throw new RuntimeException('ENCRYPTION_KEY must encode exactly 32 bytes.');
        }

        return $key;
    }
}
