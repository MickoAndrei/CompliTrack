<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProfileImageService;
use Illuminate\Console\Command;

class MigrateProfileImages extends Command
{
    protected $signature = 'avatars:migrate';

    protected $description = 'Copy legacy profile image files into MongoDB user records';

    public function handle(ProfileImageService $images): int
    {
        $migrated = 0;
        $missing = 0;
        foreach (User::whereNotNull('avatarPath')->where('avatarPath', '!=', '')->cursor() as $user) {
            if ($images->migrateLegacy($user)) {
                $migrated++;
            } else {
                $missing++;
            }
        }
        $this->info("Profile images stored in MongoDB: {$migrated}. Unavailable legacy images: {$missing}.");

        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
