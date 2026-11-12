<?php

namespace App\Services;

use App\Models\User;
use MongoDB\BSON\Binary;

class ProfileImageService
{
    public function store(User $user, string $contents, string $mimeType): void
    {
        $user->avatarData = new Binary($contents, Binary::TYPE_GENERIC);
        $user->avatarMimeType = $mimeType;
        $user->avatarVersion = hash('sha256', $contents);
        $user->avatarPath = null;
        if (! $user->save()) {
            throw new \RuntimeException('The profile photo could not be saved to MongoDB.');
        }
    }

    public function migrateLegacy(User $user): bool
    {
        if ($user->avatarData instanceof Binary) {
            return true;
        }

        $path = $user->avatarPath;
        if (! is_string($path) || preg_match('/^avatars\/[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $path, $matches) !== 1) {
            return false;
        }

        $absolutePath = rtrim(config('filesystems.disks.public.root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (! is_file($absolutePath) || filesize($absolutePath) > 2 * 1024 * 1024) {
            return false;
        }
        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            return false;
        }

        $this->store($user, $contents, match (strtolower($matches[1])) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        });

        return true;
    }
}
